<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20190902130102 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE attachments DROP FOREIGN KEY FK_212B82DC1F1F2A24');
        $this->addSql('ALTER TABLE attachments DROP FOREIGN KEY attachements_type_id_fk');
        $this->addSql('ALTER TABLE attachments CHANGE last_modified last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, CHANGE datetime_added datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL');
        $this->addSql('DROP INDEX idx_212b82dcc54c8c93 ON attachments');
        $this->addSql('CREATE INDEX IDX_47C4FAD6C54C8C93 ON attachments (type_id)');
        $this->addSql('DROP INDEX idx_212b82dc1f1f2a24 ON attachments');
        $this->addSql('CREATE INDEX IDX_47C4FAD61F1F2A24 ON attachments (element_id)');
        $this->addSql('ALTER TABLE attachments ADD CONSTRAINT FK_212B82DC1F1F2A24 FOREIGN KEY (element_id) REFERENCES parts (id)');
        $this->addSql('ALTER TABLE attachments ADD CONSTRAINT attachements_type_id_fk FOREIGN KEY (type_id) REFERENCES attachment_types (id)');
        $this->addSql('ALTER TABLE attachment_types DROP FOREIGN KEY attachement_types_parent_id_fk');
        $this->addSql('ALTER TABLE attachment_types CHANGE datetime_added datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, CHANGE last_modified last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL');
        $this->addSql('DROP INDEX idx_9a8c1c77727aca70 ON attachment_types');
        $this->addSql('CREATE INDEX IDX_EFAED719727ACA70 ON attachment_types (parent_id)');
        $this->addSql('ALTER TABLE attachment_types ADD CONSTRAINT attachement_types_parent_id_fk FOREIGN KEY (parent_id) REFERENCES attachment_types (id)');
        $this->addSql('ALTER TABLE devices CHANGE datetime_added datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, CHANGE last_modified last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL');
        $this->addSql('ALTER TABLE categories CHANGE datetime_added datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, CHANGE last_modified last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL');
        $this->addSql('ALTER TABLE footprints CHANGE datetime_added datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, CHANGE last_modified last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL');
        $this->addSql('ALTER TABLE manufacturers CHANGE datetime_added datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, CHANGE last_modified last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL');
        $this->addSql('ALTER TABLE measurement_units CHANGE last_modified last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, CHANGE datetime_added datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL');
        $this->addSql('ALTER TABLE parts CHANGE datetime_added datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, CHANGE last_modified last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL');
        $this->addSql('ALTER TABLE part_lots CHANGE last_modified last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, CHANGE datetime_added datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL');
        $this->addSql('ALTER TABLE storelocations CHANGE datetime_added datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, CHANGE last_modified last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL');
        $this->addSql('ALTER TABLE suppliers CHANGE datetime_added datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, CHANGE last_modified last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL');
        $this->addSql('ALTER TABLE currencies CHANGE last_modified last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, CHANGE datetime_added datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL');
        $this->addSql('ALTER TABLE orderdetails CHANGE datetime_added datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, CHANGE last_modified last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL');
        $this->addSql('ALTER TABLE pricedetails CHANGE last_modified last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, CHANGE datetime_added datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL');
        $this->addSql('ALTER TABLE groups CHANGE datetime_added datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, CHANGE last_modified last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL');
        $this->addSql('ALTER TABLE users CHANGE datetime_added datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, CHANGE last_modified last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE `attachment_types` DROP FOREIGN KEY FK_EFAED719727ACA70');
        $this->addSql('ALTER TABLE `attachment_types` CHANGE last_modified last_modified DATETIME NOT NULL, CHANGE datetime_added datetime_added DATETIME NOT NULL');
        $this->addSql('DROP INDEX idx_efaed719727aca70 ON `attachment_types`');
        $this->addSql('CREATE INDEX IDX_9A8C1C77727ACA70 ON `attachment_types` (parent_id)');
        $this->addSql('ALTER TABLE `attachment_types` ADD CONSTRAINT FK_EFAED719727ACA70 FOREIGN KEY (parent_id) REFERENCES `attachment_types` (id)');
        $this->addSql('ALTER TABLE `attachments` DROP FOREIGN KEY FK_47C4FAD6C54C8C93');
        $this->addSql('ALTER TABLE `attachments` DROP FOREIGN KEY FK_47C4FAD61F1F2A24');
        $this->addSql('ALTER TABLE `attachments` CHANGE last_modified last_modified DATETIME NOT NULL, CHANGE datetime_added datetime_added DATETIME NOT NULL');
        $this->addSql('DROP INDEX idx_47c4fad6c54c8c93 ON `attachments`');
        $this->addSql('CREATE INDEX IDX_212B82DCC54C8C93 ON `attachments` (type_id)');
        $this->addSql('DROP INDEX idx_47c4fad61f1f2a24 ON `attachments`');
        $this->addSql('CREATE INDEX IDX_212B82DC1F1F2A24 ON `attachments` (element_id)');
        $this->addSql('ALTER TABLE `attachments` ADD CONSTRAINT FK_47C4FAD6C54C8C93 FOREIGN KEY (type_id) REFERENCES `attachment_types` (id)');
        $this->addSql('ALTER TABLE `attachments` ADD CONSTRAINT FK_47C4FAD61F1F2A24 FOREIGN KEY (element_id) REFERENCES `parts` (id)');
        $this->addSql('ALTER TABLE `categories` CHANGE last_modified last_modified DATETIME NOT NULL, CHANGE datetime_added datetime_added DATETIME NOT NULL');
        $this->addSql('ALTER TABLE currencies CHANGE last_modified last_modified DATETIME NOT NULL, CHANGE datetime_added datetime_added DATETIME NOT NULL');
        $this->addSql('ALTER TABLE `devices` CHANGE last_modified last_modified DATETIME NOT NULL, CHANGE datetime_added datetime_added DATETIME NOT NULL');
        $this->addSql('ALTER TABLE `footprints` CHANGE last_modified last_modified DATETIME NOT NULL, CHANGE datetime_added datetime_added DATETIME NOT NULL');
        $this->addSql('ALTER TABLE `groups` CHANGE last_modified last_modified DATETIME NOT NULL, CHANGE datetime_added datetime_added DATETIME NOT NULL');
        $this->addSql('ALTER TABLE `manufacturers` CHANGE last_modified last_modified DATETIME NOT NULL, CHANGE datetime_added datetime_added DATETIME NOT NULL');
        $this->addSql('ALTER TABLE `measurement_units` CHANGE last_modified last_modified DATETIME NOT NULL, CHANGE datetime_added datetime_added DATETIME NOT NULL');
        $this->addSql('ALTER TABLE `orderdetails` CHANGE last_modified last_modified DATETIME NOT NULL, CHANGE datetime_added datetime_added DATETIME NOT NULL');
        $this->addSql('ALTER TABLE part_lots CHANGE last_modified last_modified DATETIME NOT NULL, CHANGE datetime_added datetime_added DATETIME NOT NULL');
        $this->addSql('ALTER TABLE `parts` CHANGE datetime_added datetime_added DATETIME NOT NULL, CHANGE last_modified last_modified DATETIME NOT NULL');
        $this->addSql('ALTER TABLE `pricedetails` CHANGE last_modified last_modified DATETIME NOT NULL, CHANGE datetime_added datetime_added DATETIME NOT NULL');
        $this->addSql('ALTER TABLE `storelocations` CHANGE last_modified last_modified DATETIME NOT NULL, CHANGE datetime_added datetime_added DATETIME NOT NULL');
        $this->addSql('ALTER TABLE `suppliers` CHANGE last_modified last_modified DATETIME NOT NULL, CHANGE datetime_added datetime_added DATETIME NOT NULL');
        $this->addSql('ALTER TABLE `users` CHANGE last_modified last_modified DATETIME NOT NULL, CHANGE datetime_added datetime_added DATETIME NOT NULL');
    }
}
