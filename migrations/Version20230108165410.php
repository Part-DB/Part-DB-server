<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20230108165410 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        //Rename the table from devices to projects
        $this->addSql('ALTER TABLE devices RENAME TO projects');
        $this->addSql('ALTER TABLE device_parts RENAME TO project_bom_entries');

        $this->addSql('ALTER TABLE parts ADD built_project_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE parts ADD CONSTRAINT FK_6940A7FEE8AE70D9 FOREIGN KEY (built_project_id) REFERENCES projects (id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_6940A7FEE8AE70D9 ON parts (built_project_id)');
        $this->addSql('ALTER TABLE project_bom_entries DROP FOREIGN KEY FK_AFC547992F180363');
        $this->addSql('ALTER TABLE project_bom_entries DROP FOREIGN KEY FK_AFC54799C22F6CC4');
        $this->addSql('ALTER TABLE project_bom_entries ADD price_currency_id INT DEFAULT NULL, ADD name VARCHAR(255) DEFAULT NULL, ADD comment LONGTEXT NOT NULL, ADD price NUMERIC(11, 5) DEFAULT NULL COMMENT \'(DC2Type:big_decimal)\', ADD last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, ADD datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, CHANGE quantity quantity DOUBLE PRECISION NOT NULL');
        $this->addSql('ALTER TABLE project_bom_entries ADD CONSTRAINT FK_1AA2DD313FFDCD60 FOREIGN KEY (price_currency_id) REFERENCES currencies (id)');
        $this->addSql('CREATE INDEX IDX_1AA2DD313FFDCD60 ON project_bom_entries (price_currency_id)');
        $this->addSql('DROP INDEX idx_afc547992f180363 ON project_bom_entries');
        $this->addSql('CREATE INDEX IDX_1AA2DD312F180363 ON project_bom_entries (id_device)');
        $this->addSql('DROP INDEX idx_afc54799c22f6cc4 ON project_bom_entries');
        $this->addSql('CREATE INDEX IDX_1AA2DD31C22F6CC4 ON project_bom_entries (id_part)');
        $this->addSql('ALTER TABLE project_bom_entries ADD CONSTRAINT FK_AFC547992F180363 FOREIGN KEY (id_device) REFERENCES projects (id)');
        $this->addSql('ALTER TABLE project_bom_entries ADD CONSTRAINT FK_AFC54799C22F6CC4 FOREIGN KEY (id_part) REFERENCES parts (id)');
        $this->addSql('ALTER TABLE projects DROP FOREIGN KEY FK_11074E9A6DEDCEC2');
        $this->addSql('ALTER TABLE projects DROP FOREIGN KEY devices_parent_id_fk');
        $this->addSql('ALTER TABLE projects ADD status VARCHAR(64) DEFAULT NULL, ADD description LONGTEXT NOT NULL');
        $this->addSql('DROP INDEX idx_11074e9a727aca70 ON projects');
        $this->addSql('CREATE INDEX IDX_5C93B3A4727ACA70 ON projects (parent_id)');
        $this->addSql('DROP INDEX idx_11074e9a6dedcec2 ON projects');
        $this->addSql('CREATE INDEX IDX_5C93B3A46DEDCEC2 ON projects (id_preview_attachement)');
        $this->addSql('ALTER TABLE projects ADD CONSTRAINT FK_11074E9A6DEDCEC2 FOREIGN KEY (id_preview_attachement) REFERENCES attachments (id)');
        $this->addSql('ALTER TABLE projects ADD CONSTRAINT devices_parent_id_fk FOREIGN KEY (parent_id) REFERENCES projects (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `parts` DROP FOREIGN KEY FK_6940A7FEE8AE70D9');
        $this->addSql('DROP INDEX UNIQ_6940A7FEE8AE70D9 ON `parts`');
        $this->addSql('ALTER TABLE `parts` DROP built_project_id');
        $this->addSql('ALTER TABLE projects DROP FOREIGN KEY FK_5C93B3A4727ACA70');
        $this->addSql('ALTER TABLE projects DROP FOREIGN KEY FK_5C93B3A46DEDCEC2');
        $this->addSql('ALTER TABLE projects DROP status, DROP description');
        $this->addSql('DROP INDEX idx_5c93b3a4727aca70 ON projects');
        $this->addSql('CREATE INDEX IDX_11074E9A727ACA70 ON projects (parent_id)');
        $this->addSql('DROP INDEX idx_5c93b3a46dedcec2 ON projects');
        $this->addSql('CREATE INDEX IDX_11074E9A6DEDCEC2 ON projects (id_preview_attachement)');
        $this->addSql('ALTER TABLE projects ADD CONSTRAINT FK_5C93B3A4727ACA70 FOREIGN KEY (parent_id) REFERENCES projects (id)');
        $this->addSql('ALTER TABLE projects ADD CONSTRAINT FK_5C93B3A46DEDCEC2 FOREIGN KEY (id_preview_attachement) REFERENCES `attachments` (id)');
        $this->addSql('ALTER TABLE project_bom_entries DROP FOREIGN KEY FK_1AA2DD313FFDCD60');
        $this->addSql('DROP INDEX IDX_1AA2DD313FFDCD60 ON project_bom_entries');
        $this->addSql('ALTER TABLE project_bom_entries DROP FOREIGN KEY FK_1AA2DD312F180363');
        $this->addSql('ALTER TABLE project_bom_entries DROP FOREIGN KEY FK_1AA2DD31C22F6CC4');
        $this->addSql('ALTER TABLE project_bom_entries DROP price_currency_id, DROP name, DROP comment, DROP price, DROP last_modified, DROP datetime_added, CHANGE quantity quantity INT NOT NULL');
        $this->addSql('DROP INDEX idx_1aa2dd312f180363 ON project_bom_entries');
        $this->addSql('CREATE INDEX IDX_AFC547992F180363 ON project_bom_entries (id_device)');
        $this->addSql('DROP INDEX idx_1aa2dd31c22f6cc4 ON project_bom_entries');
        $this->addSql('CREATE INDEX IDX_AFC54799C22F6CC4 ON project_bom_entries (id_part)');
        $this->addSql('ALTER TABLE project_bom_entries ADD CONSTRAINT FK_1AA2DD312F180363 FOREIGN KEY (id_device) REFERENCES projects (id)');
        $this->addSql('ALTER TABLE project_bom_entries ADD CONSTRAINT FK_1AA2DD31C22F6CC4 FOREIGN KEY (id_part) REFERENCES `parts` (id)');

        $this->addSql('ALTER TABLE projects RENAME TO devices');
        $this->addSql('ALTER TABLE project_bom_entries RENAME TO device_parts');
    }
}
