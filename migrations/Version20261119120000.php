<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Migration\AbstractMultiPlatformMigration;
use Doctrine\DBAL\Schema\Schema;

final class Version20261119120000 extends AbstractMultiPlatformMigration
{
    public function getDescription(): string
    {
        return 'Migrate alternative_names from CSV column to relational alternative_names table';
    }

    // Discriminator values match AbstractAlternativeName::DiscriminatorMap
    private const ENTITY_SOURCES = [
        ['table' => 'categories',         'type' => 0],
        ['table' => 'footprints',         'type' => 1],
        ['table' => 'manufacturers',      'type' => 2],
        ['table' => 'suppliers',          'type' => 3],
        ['table' => 'storelocations',     'type' => 4],
        ['table' => 'measurement_units',  'type' => 5],
        ['table' => 'attachment_types',   'type' => 6],
        ['table' => 'part_custom_states', 'type' => 7],
        ['table' => 'groups',             'type' => 8],
        ['table' => 'currencies',         'type' => 9],
        ['table' => 'projects',           'type' => 10],
    ];

    public function mySQLUp(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE alternative_names (
                id INT AUTO_INCREMENT NOT NULL,
                element_id INT NOT NULL,
                type SMALLINT NOT NULL,
                name VARCHAR(255) NOT NULL,
                sort_index INT NOT NULL DEFAULT 0,
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql('CREATE INDEX idx_altname_lookup ON alternative_names (type, name)');
        $this->addSql('CREATE INDEX idx_altname_element ON alternative_names (type, element_id)');
        $this->addSql('CREATE INDEX IDX_D81ABF221F1F2A24 ON alternative_names (element_id)');
        // Old columns dropped in postUp() after data migration
    }

    public function mySQLDown(Schema $schema): void
    {
        foreach (self::ENTITY_SOURCES as $source) {
            $this->addSql(sprintf("ALTER TABLE `%s` ADD COLUMN alternative_names LONGTEXT DEFAULT NULL", $source['table']));
        }
        $this->addSql('DROP TABLE alternative_names');
    }

    public function sqLiteUp(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE alternative_names (
                id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                element_id INTEGER NOT NULL,
                type SMALLINT NOT NULL,
                name VARCHAR(255) NOT NULL,
                sort_index INTEGER NOT NULL DEFAULT 0
            )
        SQL);
        $this->addSql('CREATE INDEX idx_altname_lookup ON alternative_names (type, name)');
        $this->addSql('CREATE INDEX idx_altname_element ON alternative_names (type, element_id)');
        $this->addSql('CREATE INDEX IDX_D81ABF221F1F2A24 ON alternative_names (element_id)');

        foreach (self::ENTITY_SOURCES as $source) {
            // SQLite cannot DROP columns directly; rebuild each table without alternative_names.
            // We fetch the CREATE TABLE statement and strip the column.
            // For simplicity we use a known-good approach: alter via temp table is complex,
            // so we just leave the column in SQLite (it becomes orphaned but causes no harm).
            // The column is simply no longer read or written by the application.
        }
    }

    public function sqLiteDown(Schema $schema): void
    {
        $this->addSql('DROP TABLE alternative_names');
    }

    public function postgreSQLUp(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE alternative_names (
                id SERIAL NOT NULL,
                element_id INT NOT NULL,
                type SMALLINT NOT NULL,
                name VARCHAR(255) NOT NULL,
                sort_index INT NOT NULL DEFAULT 0,
                PRIMARY KEY(id)
            )
        SQL);
        $this->addSql('CREATE INDEX idx_altname_lookup ON alternative_names (type, name)');
        $this->addSql('CREATE INDEX idx_altname_element ON alternative_names (type, element_id)');
        $this->addSql('CREATE INDEX IDX_D81ABF221F1F2A24 ON alternative_names (element_id)');
        // Old columns dropped in postUp() after data migration
    }

    public function postgreSQLDown(Schema $schema): void
    {
        foreach (self::ENTITY_SOURCES as $source) {
            $this->addSql(sprintf('ALTER TABLE %s ADD COLUMN alternative_names TEXT DEFAULT NULL', $source['table']));
        }
        $this->addSql('DROP TABLE alternative_names');
    }

    public function postUp(Schema $schema): void
    {
        parent::postUp($schema);
        // Data migration must happen before column drops, since addSql() drops
        // are deferred and would have already run if placed in up() — leaving
        // nothing to read from.
        $this->migrateData();
        $this->dropOldColumns();
    }

    private function migrateData(): void
    {
        $dbType = $this->getDatabaseType();

        foreach (self::ENTITY_SOURCES as $source) {
            $table = $source['table'];
            $type  = $source['type'];

            $rows = $this->connection->fetchAllAssociative(
                sprintf(
                    'SELECT id, alternative_names FROM %s WHERE alternative_names IS NOT NULL AND alternative_names != %s AND alternative_names != %s',
                    $dbType === 'mysql' ? "`{$table}`" : $table,
                    $this->connection->quote(''),
                    $this->connection->quote(',')
                )
            );

            foreach ($rows as $row) {
                $csv = rtrim(trim((string) $row['alternative_names']), ', ');
                if ($csv === '') {
                    continue;
                }

                $sortIndex = 0;
                foreach (explode(',', $csv) as $name) {
                    $name = trim($name);
                    if ($name === '') {
                        continue;
                    }

                    $this->connection->executeStatement(
                        'INSERT INTO alternative_names (element_id, type, name, sort_index) VALUES (?, ?, ?, ?)',
                        [(int) $row['id'], $type, $name, $sortIndex]
                    );
                    ++$sortIndex;
                }
            }
        }
    }

    private function dropOldColumns(): void
    {
        $dbType = $this->getDatabaseType();

        // SQLite cannot easily drop columns; leave them (orphaned but harmless).
        if ($dbType === 'sqlite') {
            return;
        }

        foreach (self::ENTITY_SOURCES as $source) {
            $table = $source['table'];
            $sql = $dbType === 'mysql'
                ? sprintf('ALTER TABLE `%s` DROP COLUMN alternative_names', $table)
                : sprintf('ALTER TABLE %s DROP COLUMN alternative_names', $table);
            $this->connection->executeStatement($sql);
        }
    }
}
