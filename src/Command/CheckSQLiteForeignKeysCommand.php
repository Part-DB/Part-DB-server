<?php
/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2026 Jan Böhmer (https://github.com/jbtronics)
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU Affero General Public License as published
 *  by the Free Software Foundation, either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU Affero General Public License for more details.
 *
 *  You should have received a copy of the GNU Affero General Public License
 *  along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

declare(strict_types=1);


namespace App\Command;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Checks an existing SQLite database for rows that already violate a foreign key constraint (e.g.
 * referencing a parent row that has since been deleted), using SQLite's "PRAGMA foreign_key_check".
 *
 * This is useful before turning on DATABASE_SQLITE_ENFORCE_FOREIGN_KEYS (see
 * App\Doctrine\Middleware\SQLiteForeignKeysMiddlewareDriver) on an existing installation - SQLite does
 * not enforce foreign keys by default, so such inconsistent rows can accumulate silently over time and
 * would otherwise only surface as a confusing "FOREIGN KEY constraint failed" error the first time
 * something touches them after enforcement is enabled.
 *
 * With --fix, it also tries to resolve the violations it found:
 *  - If the foreign key is declared "ON DELETE CASCADE", the affected row itself is deleted - this is what
 *    would have happened automatically had enforcement been on when the (now missing) parent row was
 *    deleted, so this just carries out that cascade retroactively.
 *  - Otherwise, if the foreign key column is nullable, it is set to NULL on the affected row (the same
 *    thing "ON DELETE SET NULL" / the application's own cleanup logic would have done).
 *  - Rows that are neither covered by an ON DELETE CASCADE foreign key nor have a nullable foreign key
 *    column cannot be fixed automatically (there is no correct parent to reassign them to, and deleting
 *    them was not requested by the schema) and are reported for manual handling.
 */
#[AsCommand('partdb:database:check-sqlite-foreign-keys', 'Checks a SQLite database for existing foreign key constraint violations')]
class CheckSQLiteForeignKeysCommand extends Command
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setHelp('This command runs SQLite\'s "PRAGMA foreign_key_check" against the configured database and '
            .'reports any rows that currently violate a foreign key constraint. Run it before enabling '
            .'DATABASE_SQLITE_ENFORCE_FOREIGN_KEYS on an existing installation, to find out whether doing so would '
            .'immediately start rejecting operations on data that is already inconsistent.')
            ->addOption('fix', null, InputOption::VALUE_NONE, 'Try to automatically fix the found violations: rows '
                .'referenced by an "ON DELETE CASCADE" foreign key are deleted, other rows with a nullable foreign '
                .'key column have that column set to NULL. Rows that are neither are reported separately for manual '
                .'handling. This modifies your database - make sure you have a backup first!');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $connection = $this->entityManager->getConnection();

        if (!$connection->getDatabasePlatform() instanceof SQLitePlatform) {
            $io->error('This command only works with SQLite databases. The configured DATABASE_URL uses a different database platform.');
            return Command::INVALID;
        }

        $violations = $connection->fetchAllAssociative('PRAGMA foreign_key_check');

        if ($violations === []) {
            $io->success('No foreign key constraint violations found. It should be safe to enable DATABASE_SQLITE_ENFORCE_FOREIGN_KEYS.');
            return Command::SUCCESS;
        }

        $io->warning(sprintf('Found %d row(s) that violate a foreign key constraint:', count($violations)));
        $io->table(
            ['Table', 'Row ID', 'Referenced table', 'Foreign key ID'],
            array_map(static fn (array $row): array => [
                $row['table'],
                $row['rowid'] ?? 'NULL',
                $row['parent'],
                $row['fkid'],
            ], $violations)
        );

        if (!$input->getOption('fix')) {
            $io->note('These rows reference a parent row that does not exist. Enabling DATABASE_SQLITE_ENFORCE_FOREIGN_KEYS does '
                .'not fix or remove them, but any future write touching them may then be rejected with a "FOREIGN KEY '
                .'constraint failed" error. Fix or remove the offending rows, or re-run this command with --fix to try '
                .'to resolve them automatically.');

            return Command::FAILURE;
        }

        $io->warning('You are about to modify and/or delete rows in your database. Make sure you have a backup '
            .'before proceeding (see "partdb:backup")! This action can not be undone.');

        return $this->fix($io, $connection, $violations);
    }

    /**
     * @param  list<array<string, mixed>>  $violations
     */
    private function fix(SymfonyStyle $io, Connection $connection, array $violations): int
    {
        $unfixable = [];
        // Resolved violations, grouped by table and row, so that we can decide per row whether any of its
        // violations calls for the whole row to be deleted (ON DELETE CASCADE) before looking at the rest.
        $resolvedByRow = [];

        $foreignKeyListsByTable = [];
        $tableInfosByTable = [];

        foreach ($violations as $violation) {
            $table = $violation['table'];
            $rowId = $violation['rowid'] ?? null;

            if ($rowId === null) {
                $unfixable[] = [$violation, 'the affected row could not be identified'];
                continue;
            }

            $foreignKeyListsByTable[$table] ??= $connection->fetchAllAssociative(
                sprintf('PRAGMA foreign_key_list(%s)', $connection->quoteIdentifier($table))
            );

            $foreignKey = null;
            foreach ($foreignKeyListsByTable[$table] as $candidate) {
                if ((int) $candidate['id'] === (int) $violation['fkid']) {
                    $foreignKey = $candidate;
                    break;
                }
            }

            if ($foreignKey === null) {
                $unfixable[] = [$violation, 'the affected foreign key column could not be determined'];
                continue;
            }

            $column = $foreignKey['from'];

            $tableInfosByTable[$table] ??= $connection->fetchAllAssociative(
                sprintf('PRAGMA table_info(%s)', $connection->quoteIdentifier($table))
            );

            $notNull = false;
            foreach ($tableInfosByTable[$table] as $columnInfo) {
                if ($columnInfo['name'] === $column) {
                    $notNull = (bool) $columnInfo['notnull'];
                    break;
                }
            }

            $resolvedByRow[$table][$rowId][] = [
                'violation' => $violation,
                'column' => $column,
                'notNull' => $notNull,
                'onDelete' => strtoupper((string) ($foreignKey['on_delete'] ?? 'NO ACTION')),
            ];
        }

        $rowsToDelete = [];
        $columnsToNull = [];

        foreach ($resolvedByRow as $table => $rows) {
            foreach ($rows as $rowId => $entries) {
                $cascadeEntry = null;
                foreach ($entries as $entry) {
                    if ($entry['onDelete'] === 'CASCADE') {
                        $cascadeEntry = $entry;
                        break;
                    }
                }

                if ($cascadeEntry !== null) {
                    $rowsToDelete[] = [
                        'table' => $table,
                        'rowid' => $rowId,
                        'reason' => sprintf('%s -> %s (ON DELETE CASCADE)', $cascadeEntry['column'], $cascadeEntry['violation']['parent']),
                    ];
                    continue;
                }

                foreach ($entries as $entry) {
                    if (!$entry['notNull']) {
                        $columnsToNull[] = ['table' => $table, 'rowid' => $rowId, 'column' => $entry['column']];
                    } else {
                        $unfixable[] = [$entry['violation'], sprintf('column "%s" is NOT NULL and not covered by an ON DELETE CASCADE foreign key, so the row cannot be fixed automatically', $entry['column'])];
                    }
                }
            }
        }

        if ($unfixable !== []) {
            $io->warning(sprintf('%d violation(s) can not be fixed automatically and need to be resolved manually:', count($unfixable)));
            foreach ($unfixable as [$violation, $reason]) {
                $io->writeln(sprintf(' * Table "%s", row %s: %s', $violation['table'], $violation['rowid'] ?? 'unknown', $reason));
            }
        }

        if ($rowsToDelete === [] && $columnsToNull === []) {
            $io->error('None of the found violations can be fixed automatically. You have to resolve them manually.');
            return Command::FAILURE;
        }

        $io->section('The following changes would be made:');

        if ($rowsToDelete !== []) {
            $io->writeln(sprintf('<comment>%d row(s) will be DELETED (ON DELETE CASCADE):</comment>', count($rowsToDelete)));
            $io->table(
                ['Table', 'Row ID', 'Foreign key'],
                array_map(static fn (array $row): array => [$row['table'], $row['rowid'], $row['reason']], $rowsToDelete)
            );
        }

        if ($columnsToNull !== []) {
            $io->writeln(sprintf('<comment>%d column(s) will be set to NULL:</comment>', count($columnsToNull)));
            $io->table(
                ['Table', 'Row ID', 'Column that will be set to NULL'],
                array_map(static fn (array $fix): array => [$fix['table'], $fix['rowid'], $fix['column']], $columnsToNull)
            );
        }

        if (!$io->confirm('Apply the changes shown above? Make sure you have a backup of your database! This can not be undone.', true)) {
            $io->info('Aborted. No changes were made.');
            return Command::FAILURE;
        }

        $connection->beginTransaction();
        try {
            foreach ($rowsToDelete as $delete) {
                $connection->executeStatement(
                    sprintf('DELETE FROM %s WHERE rowid = ?', $connection->quoteIdentifier($delete['table'])),
                    [$delete['rowid']],
                );
            }

            foreach ($columnsToNull as $fix) {
                $connection->executeStatement(
                    sprintf(
                        'UPDATE %s SET %s = NULL WHERE rowid = ?',
                        $connection->quoteIdentifier($fix['table']),
                        $connection->quoteIdentifier($fix['column']),
                    ),
                    [$fix['rowid']],
                );
            }
            $connection->commit();
        } catch (\Throwable $exception) {
            $connection->rollBack();
            $io->error('Failed to apply fixes, no changes were made: ' . $exception->getMessage());
            return Command::FAILURE;
        }

        $io->success(sprintf('Deleted %d row(s) and fixed %d column(s).', count($rowsToDelete), count($columnsToNull)));

        $remaining = $connection->fetchAllAssociative('PRAGMA foreign_key_check');
        if ($remaining === []) {
            $io->success('No foreign key constraint violations remain. It should be safe to enable DATABASE_SQLITE_ENFORCE_FOREIGN_KEYS.');
            return Command::SUCCESS;
        }

        $io->warning(sprintf('%d violation(s) remain. This can happen if deleting a row uncovered a new violation '
            .'(e.g. another row referencing it). Run this command again to resolve them.', count($remaining)));
        return Command::FAILURE;
    }
}
