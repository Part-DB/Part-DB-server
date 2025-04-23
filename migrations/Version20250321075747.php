<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Migration\AbstractMultiPlatformMigration;
use Doctrine\DBAL\Schema\Schema;

final class Version20250321075747 extends AbstractMultiPlatformMigration
{
    public function getDescription(): string
    {
        return 'Create entity table for custom part states';
    }

    public function mySQLUp(Schema $schema): void
    {
        $this->addSql('CREATE TABLE `part_custom_states` (id INT AUTO_INCREMENT NOT NULL, parent_id INT DEFAULT NULL, id_preview_attachment INT DEFAULT NULL, name VARCHAR(255) NOT NULL, comment LONGTEXT NOT NULL, not_selectable TINYINT(1) NOT NULL, alternative_names LONGTEXT DEFAULT NULL, last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, INDEX IDX_F552745D727ACA70 (parent_id), INDEX IDX_F552745DEA7100A1 (id_preview_attachment), INDEX part_custom_state_name (name), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE `part_custom_states` ADD CONSTRAINT FK_F552745D727ACA70 FOREIGN KEY (parent_id) REFERENCES `part_custom_states` (id)');
        $this->addSql('ALTER TABLE `part_custom_states` ADD CONSTRAINT FK_F552745DEA7100A1 FOREIGN KEY (id_preview_attachment) REFERENCES `attachments` (id) ON DELETE SET NULL');
    }

    public function mySQLDown(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `part_custom_states` DROP FOREIGN KEY FK_F552745D727ACA70');
        $this->addSql('ALTER TABLE `part_custom_states` DROP FOREIGN KEY FK_F552745DEA7100A1');
        $this->addSql('DROP TABLE `part_custom_states`');
    }

    public function sqLiteUp(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE "part_custom_states" (
                id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, 
                parent_id INTEGER DEFAULT NULL, 
                id_preview_attachment INTEGER DEFAULT NULL,
                name VARCHAR(255) NOT NULL, 
                comment CLOB NOT NULL,
                not_selectable BOOLEAN NOT NULL, 
                alternative_names CLOB DEFAULT NULL, 
                last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, 
                datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, 
                CONSTRAINT FK_F552745D727ACA70 FOREIGN KEY (parent_id) REFERENCES "part_custom_states" (id) NOT DEFERRABLE INITIALLY IMMEDIATE,
                CONSTRAINT FK_F5AF83CFEA7100A1 FOREIGN KEY (id_preview_attachment) REFERENCES "attachments" (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE
            )
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_F552745D727ACA70 ON "part_custom_states" (parent_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX part_custom_state_name ON "part_custom_states" (name)
        SQL);
    }

    public function sqLiteDown(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            DROP TABLE "part_custom_states"
        SQL);
    }

    public function postgreSQLUp(Schema $schema): void
    {
        //Not needed
    }

    public function postgreSQLDown(Schema $schema): void
    {
        //Not needed
    }
}
