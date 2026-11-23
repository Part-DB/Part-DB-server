<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Migration\AbstractMultiPlatformMigration;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Schema\Schema;

final class Version20261123120000 extends AbstractMultiPlatformMigration
{
    public function getDescription(): string
    {
        return 'Migrate the tags of parts from a comma-separated column to tag entities';
    }

    public function mySQLUp(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE `tags` (
                id INT AUTO_INCREMENT NOT NULL,
                name VARCHAR(255) NOT NULL,
                last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
                datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
                comment LONGTEXT NOT NULL,
                not_selectable TINYINT NOT NULL,
                color VARCHAR(20) DEFAULT NULL,
                parent_id INT DEFAULT NULL,
                id_preview_attachment INT DEFAULT NULL,
                UNIQUE INDEX tag_name_unique (name),
                INDEX IDX_6FBC9426727ACA70 (parent_id),
                INDEX IDX_6FBC9426EA7100A1 (id_preview_attachment),
                PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql('ALTER TABLE `tags` ADD CONSTRAINT FK_6FBC9426727ACA70 FOREIGN KEY (parent_id) REFERENCES `tags` (id)');
        $this->addSql('ALTER TABLE `tags` ADD CONSTRAINT FK_6FBC9426EA7100A1 FOREIGN KEY (id_preview_attachment) REFERENCES `attachments` (id) ON DELETE SET NULL');
        $this->addSql(<<<'SQL'
            CREATE TABLE parts_tags (
                part_id INT NOT NULL,
                tag_id INT NOT NULL,
                INDEX IDX_D18476934CE34BEC (part_id),
                INDEX IDX_D1847693BAD26311 (tag_id),
                PRIMARY KEY (part_id, tag_id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql('ALTER TABLE parts_tags ADD CONSTRAINT FK_D18476934CE34BEC FOREIGN KEY (part_id) REFERENCES `parts` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE parts_tags ADD CONSTRAINT FK_D1847693BAD26311 FOREIGN KEY (tag_id) REFERENCES `tags` (id) ON DELETE CASCADE');
        // The old column is dropped in postUp() after the data migration
    }

    public function mySQLDown(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `parts` ADD tags LONGTEXT NOT NULL');
        $this->addSql(<<<'SQL'
            UPDATE `parts` p SET p.tags = COALESCE((
                SELECT GROUP_CONCAT(t.name ORDER BY t.name SEPARATOR ',')
                FROM parts_tags pt INNER JOIN `tags` t ON t.id = pt.tag_id
                WHERE pt.part_id = p.id
            ), '')
        SQL);
        $this->deleteTagElements();
        $this->addSql('DROP TABLE parts_tags');
        $this->addSql('ALTER TABLE `tags` DROP FOREIGN KEY FK_6FBC9426727ACA70');
        $this->addSql('DROP TABLE `tags`');
    }

    public function sqLiteUp(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE "tags" (
                id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                name VARCHAR(255) NOT NULL,
                last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
                datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
                comment CLOB NOT NULL,
                not_selectable BOOLEAN NOT NULL,
                color VARCHAR(20) DEFAULT NULL,
                parent_id INTEGER DEFAULT NULL,
                id_preview_attachment INTEGER DEFAULT NULL,
                CONSTRAINT FK_6FBC9426727ACA70 FOREIGN KEY (parent_id) REFERENCES "tags" (id) NOT DEFERRABLE INITIALLY IMMEDIATE,
                CONSTRAINT FK_6FBC9426EA7100A1 FOREIGN KEY (id_preview_attachment) REFERENCES "attachments" (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE
            )
        SQL);
        $this->addSql('CREATE UNIQUE INDEX tag_name_unique ON "tags" (name)');
        $this->addSql('CREATE INDEX IDX_6FBC9426727ACA70 ON "tags" (parent_id)');
        $this->addSql('CREATE INDEX IDX_6FBC9426EA7100A1 ON "tags" (id_preview_attachment)');
        $this->addSql(<<<'SQL'
            CREATE TABLE parts_tags (
                part_id INTEGER NOT NULL,
                tag_id INTEGER NOT NULL,
                PRIMARY KEY (part_id, tag_id),
                CONSTRAINT FK_D18476934CE34BEC FOREIGN KEY (part_id) REFERENCES "parts" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE,
                CONSTRAINT FK_D1847693BAD26311 FOREIGN KEY (tag_id) REFERENCES "tags" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
            )
        SQL);
        $this->addSql('CREATE INDEX IDX_D18476934CE34BEC ON parts_tags (part_id)');
        $this->addSql('CREATE INDEX IDX_D1847693BAD26311 ON parts_tags (tag_id)');
    }

    public function sqLiteDown(Schema $schema): void
    {
        $this->addSql("ALTER TABLE parts ADD COLUMN tags CLOB NOT NULL DEFAULT ''");
        $this->addSql(<<<'SQL'
            UPDATE parts SET tags = COALESCE((
                SELECT GROUP_CONCAT(name, ',') FROM (
                    SELECT t.name AS name FROM parts_tags pt INNER JOIN "tags" t ON t.id = pt.tag_id
                    WHERE pt.part_id = parts.id ORDER BY t.name
                )
            ), '')
        SQL);
        $this->deleteTagElements();
        $this->addSql('DROP TABLE parts_tags');
        $this->addSql('DROP TABLE "tags"');
    }

    public function postgreSQLUp(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE "tags" (
                id INT GENERATED BY DEFAULT AS IDENTITY NOT NULL,
                name VARCHAR(255) NOT NULL,
                last_modified TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL,
                datetime_added TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL,
                comment TEXT NOT NULL,
                not_selectable BOOLEAN NOT NULL,
                color VARCHAR(20) DEFAULT NULL,
                parent_id INT DEFAULT NULL,
                id_preview_attachment INT DEFAULT NULL,
                PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE UNIQUE INDEX tag_name_unique ON "tags" (name)');
        $this->addSql('CREATE INDEX IDX_6FBC9426727ACA70 ON "tags" (parent_id)');
        $this->addSql('CREATE INDEX IDX_6FBC9426EA7100A1 ON "tags" (id_preview_attachment)');
        $this->addSql('ALTER TABLE "tags" ADD CONSTRAINT FK_6FBC9426727ACA70 FOREIGN KEY (parent_id) REFERENCES "tags" (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE "tags" ADD CONSTRAINT FK_6FBC9426EA7100A1 FOREIGN KEY (id_preview_attachment) REFERENCES "attachments" (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('CREATE TABLE parts_tags (part_id INT NOT NULL, tag_id INT NOT NULL, PRIMARY KEY (part_id, tag_id))');
        $this->addSql('CREATE INDEX IDX_D18476934CE34BEC ON parts_tags (part_id)');
        $this->addSql('CREATE INDEX IDX_D1847693BAD26311 ON parts_tags (tag_id)');
        $this->addSql('ALTER TABLE parts_tags ADD CONSTRAINT FK_D18476934CE34BEC FOREIGN KEY (part_id) REFERENCES "parts" (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE parts_tags ADD CONSTRAINT FK_D1847693BAD26311 FOREIGN KEY (tag_id) REFERENCES "tags" (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function postgreSQLDown(Schema $schema): void
    {
        $this->addSql("ALTER TABLE parts ADD tags TEXT DEFAULT '' NOT NULL");
        $this->addSql('ALTER TABLE parts ALTER tags DROP DEFAULT');
        $this->addSql(<<<'SQL'
            UPDATE parts SET tags = COALESCE((
                SELECT STRING_AGG(t.name, ',' ORDER BY t.name)
                FROM parts_tags pt INNER JOIN "tags" t ON t.id = pt.tag_id
                WHERE pt.part_id = parts.id
            ), '')
        SQL);
        $this->deleteTagElements();
        $this->addSql('DROP TABLE parts_tags');
        $this->addSql('DROP TABLE "tags"');
    }

    /**
     * The attachments, parameters and alternative names of tags would refer to a type the previous version does not
     * know anymore, so they are removed with the tags.
     */
    private function deleteTagElements(): void
    {
        $this->addSql("DELETE FROM attachments WHERE class_name = 'Tag'");
        $this->addSql('DELETE FROM parameters WHERE type = 13');
        $this->addSql('DELETE FROM alternative_names WHERE type = 11');
    }

    public function postUp(Schema $schema): void
    {
        parent::postUp($schema);
        // Like for the alternative names, the data has to be migrated before the old column is dropped, which is why
        // both happen here and not with addSql() in up()
        $this->migrateData();
        $this->dropOldColumn();
    }

    private function migrateData(): void
    {
        $quote = $this->getDatabaseType() === 'mysql' ? '`%s`' : '"%s"';

        $rows = $this->connection->fetchAllAssociative(
            sprintf("SELECT id, tags FROM %s WHERE tags <> ''", sprintf($quote, 'parts'))
        );

        // The tags of each part, and the first spelling of each tag name, both by the lowercased name. Names which
        // only differ in case or whitespace become one tag, like Tag::nameKey() compares them.
        $tagsOfParts = [];
        $tagNames = [];
        foreach ($rows as $row) {
            foreach (explode(',', (string) $row['tags']) as $name) {
                $name = mb_substr(trim((string) preg_replace('/\s+/u', ' ', $name)), 0, 255);
                if ($name === '') {
                    continue;
                }
                $key = mb_strtolower($name);
                $tagNames[$key] ??= $name;
                $tagsOfParts[(int) $row['id']][$key] = true;
            }
        }

        $tagsTable = sprintf($quote, 'tags');
        $tagIds = [];
        foreach ($tagNames as $key => $name) {
            // On MySQL, the unique index also treats names which only differ in accents as equal (utf8mb4_unicode_ci),
            // so such a name has to share the tag of the name it collides with
            $existingId = $this->connection->fetchOne(sprintf('SELECT id FROM %s WHERE name = ?', $tagsTable), [$name]);
            if ($existingId === false) {
                $this->connection->executeStatement(
                    sprintf("INSERT INTO %s (name, comment, not_selectable) VALUES (?, '', ?)", $tagsTable),
                    [$name, false],
                    [ParameterType::STRING, ParameterType::BOOLEAN]
                );
                $existingId = $this->connection->fetchOne(sprintf('SELECT id FROM %s WHERE name = ?', $tagsTable), [$name]);
            }
            $tagIds[$key] = (int) $existingId;
        }

        foreach ($tagsOfParts as $partId => $keys) {
            $ids = array_unique(array_map(static fn(string $key): int => $tagIds[$key], array_keys($keys)));
            foreach ($ids as $tagId) {
                $this->connection->executeStatement('INSERT INTO parts_tags (part_id, tag_id) VALUES (?, ?)', [$partId, $tagId]);
            }
        }
    }

    private function dropOldColumn(): void
    {
        // Unlike for the alternative names, the column can not just be left behind on SQLite: it is NOT NULL without
        // a default value, so no part could be created anymore. SQLite supports dropping columns since version 3.35.
        $this->connection->executeStatement(match ($this->getDatabaseType()) {
            'mysql' => 'ALTER TABLE `parts` DROP COLUMN tags',
            default => 'ALTER TABLE parts DROP COLUMN tags',
        });
    }
}
