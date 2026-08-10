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

use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
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
            .'immediately start rejecting operations on data that is already inconsistent.');
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

        $io->note('These rows reference a parent row that does not exist. Enabling DATABASE_SQLITE_ENFORCE_FOREIGN_KEYS does not '
            .'fix or remove them, but any future write touching them may then be rejected with a "FOREIGN KEY constraint '
            .'failed" error. Fix or remove the offending rows before enabling enforcement.');

        return Command::FAILURE;
    }
}
