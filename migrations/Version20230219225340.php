<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Migration\AbstractMultiPlatformMigration;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20230219225340 extends AbstractMultiPlatformMigration
{
    public function getDescription(): string
    {
        return 'Change FKs for preview_attachment so that they are set to null on delete';
    }

    public function mySQLUp(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE attachment_types DROP FOREIGN KEY FK_EFAED7196DEDCEC2');
        $this->addSql('DROP INDEX IDX_EFAED7196DEDCEC2 ON attachment_types');
        $this->addSql('ALTER TABLE attachment_types CHANGE id_preview_attachement id_preview_attachment INT DEFAULT NULL');
        $this->addSql('ALTER TABLE attachment_types ADD CONSTRAINT FK_EFAED719EA7100A1 FOREIGN KEY (id_preview_attachment) REFERENCES `attachments` (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_EFAED719EA7100A1 ON attachment_types (id_preview_attachment)');
        $this->addSql('ALTER TABLE categories DROP FOREIGN KEY FK_3AF346686DEDCEC2');
        $this->addSql('DROP INDEX IDX_3AF346686DEDCEC2 ON categories');
        $this->addSql('ALTER TABLE categories CHANGE id_preview_attachement id_preview_attachment INT DEFAULT NULL');
        $this->addSql('ALTER TABLE categories ADD CONSTRAINT FK_3AF34668EA7100A1 FOREIGN KEY (id_preview_attachment) REFERENCES `attachments` (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_3AF34668EA7100A1 ON categories (id_preview_attachment)');
        $this->addSql('ALTER TABLE currencies DROP FOREIGN KEY FK_37C446936DEDCEC2');
        $this->addSql('DROP INDEX IDX_37C446936DEDCEC2 ON currencies');
        $this->addSql('ALTER TABLE currencies CHANGE id_preview_attachement id_preview_attachment INT DEFAULT NULL');
        $this->addSql('ALTER TABLE currencies ADD CONSTRAINT FK_37C44693EA7100A1 FOREIGN KEY (id_preview_attachment) REFERENCES `attachments` (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_37C44693EA7100A1 ON currencies (id_preview_attachment)');
        $this->addSql('ALTER TABLE footprints DROP FOREIGN KEY FK_A34D68A26DEDCEC2');
        $this->addSql('DROP INDEX IDX_A34D68A26DEDCEC2 ON footprints');
        $this->addSql('ALTER TABLE footprints CHANGE id_preview_attachement id_preview_attachment INT DEFAULT NULL');
        $this->addSql('ALTER TABLE footprints ADD CONSTRAINT FK_A34D68A2EA7100A1 FOREIGN KEY (id_preview_attachment) REFERENCES `attachments` (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_A34D68A2EA7100A1 ON footprints (id_preview_attachment)');
        $this->addSql('ALTER TABLE `groups` DROP FOREIGN KEY FK_F06D39706DEDCEC2');
        $this->addSql('DROP INDEX IDX_F06D39706DEDCEC2 ON `groups`');
        $this->addSql('ALTER TABLE `groups`  CHANGE id_preview_attachement id_preview_attachment INT DEFAULT NULL');
        $this->addSql('ALTER TABLE `groups` ADD CONSTRAINT FK_F06D3970EA7100A1 FOREIGN KEY (id_preview_attachment) REFERENCES `attachments` (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_F06D3970EA7100A1 ON `groups` (id_preview_attachment)');
        $this->addSql('ALTER TABLE label_profiles DROP FOREIGN KEY FK_C93E9CF56DEDCEC2');
        $this->addSql('DROP INDEX IDX_C93E9CF56DEDCEC2 ON label_profiles');
        $this->addSql('ALTER TABLE label_profiles CHANGE id_preview_attachement id_preview_attachment INT DEFAULT NULL');
        $this->addSql('ALTER TABLE label_profiles ADD CONSTRAINT FK_C93E9CF5EA7100A1 FOREIGN KEY (id_preview_attachment) REFERENCES `attachments` (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_C93E9CF5EA7100A1 ON label_profiles (id_preview_attachment)');
        $this->addSql('ALTER TABLE manufacturers DROP FOREIGN KEY FK_94565B126DEDCEC2');
        $this->addSql('DROP INDEX IDX_94565B126DEDCEC2 ON manufacturers');
        $this->addSql('ALTER TABLE manufacturers CHANGE id_preview_attachement id_preview_attachment INT DEFAULT NULL');
        $this->addSql('ALTER TABLE manufacturers ADD CONSTRAINT FK_94565B12EA7100A1 FOREIGN KEY (id_preview_attachment) REFERENCES `attachments` (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_94565B12EA7100A1 ON manufacturers (id_preview_attachment)');
        $this->addSql('ALTER TABLE measurement_units DROP FOREIGN KEY FK_F5AF83CF6DEDCEC2');
        $this->addSql('DROP INDEX IDX_F5AF83CF6DEDCEC2 ON measurement_units');
        $this->addSql('ALTER TABLE measurement_units CHANGE id_preview_attachement id_preview_attachment INT DEFAULT NULL');
        $this->addSql('ALTER TABLE measurement_units ADD CONSTRAINT FK_F5AF83CFEA7100A1 FOREIGN KEY (id_preview_attachment) REFERENCES `attachments` (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_F5AF83CFEA7100A1 ON measurement_units (id_preview_attachment)');
        $this->addSql('ALTER TABLE parts DROP FOREIGN KEY FK_6940A7FE6DEDCEC2');
        $this->addSql('DROP INDEX IDX_6940A7FE6DEDCEC2 ON parts');
        $this->addSql('ALTER TABLE parts CHANGE id_preview_attachement id_preview_attachment INT DEFAULT NULL');
        $this->addSql('ALTER TABLE parts ADD CONSTRAINT FK_6940A7FEEA7100A1 FOREIGN KEY (id_preview_attachment) REFERENCES `attachments` (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_6940A7FEEA7100A1 ON parts (id_preview_attachment)');
        $this->addSql('ALTER TABLE projects DROP FOREIGN KEY FK_11074E9A6DEDCEC2');
        $this->addSql('ALTER TABLE projects DROP FOREIGN KEY FK_5C93B3A4727ACA70');
        $this->addSql('DROP INDEX IDX_5C93B3A46DEDCEC2 ON projects');
        $this->addSql('ALTER TABLE projects CHANGE id_preview_attachement id_preview_attachment INT DEFAULT NULL');
        $this->addSql('ALTER TABLE projects ADD CONSTRAINT FK_5C93B3A4EA7100A1 FOREIGN KEY (id_preview_attachment) REFERENCES `attachments` (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_5C93B3A4EA7100A1 ON projects (id_preview_attachment)');
        $this->addSql('ALTER TABLE storelocations DROP FOREIGN KEY FK_75170206DEDCEC2');
        $this->addSql('DROP INDEX IDX_75170206DEDCEC2 ON storelocations');
        $this->addSql('ALTER TABLE storelocations CHANGE id_preview_attachement id_preview_attachment INT DEFAULT NULL');
        $this->addSql('ALTER TABLE storelocations ADD CONSTRAINT FK_7517020EA7100A1 FOREIGN KEY (id_preview_attachment) REFERENCES `attachments` (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_7517020EA7100A1 ON storelocations (id_preview_attachment)');
        $this->addSql('ALTER TABLE suppliers DROP FOREIGN KEY FK_AC28B95C6DEDCEC2');
        $this->addSql('DROP INDEX IDX_AC28B95C6DEDCEC2 ON suppliers');
        $this->addSql('ALTER TABLE suppliers CHANGE id_preview_attachement id_preview_attachment INT DEFAULT NULL');
        $this->addSql('ALTER TABLE suppliers ADD CONSTRAINT FK_AC28B95CEA7100A1 FOREIGN KEY (id_preview_attachment) REFERENCES `attachments` (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_AC28B95CEA7100A1 ON suppliers (id_preview_attachment)');
        $this->addSql('ALTER TABLE `users` DROP FOREIGN KEY FK_1483A5E96DEDCEC2');
        $this->addSql('DROP INDEX IDX_1483A5E96DEDCEC2 ON `users`');
        $this->addSql('ALTER TABLE `users` CHANGE id_preview_attachement id_preview_attachment INT DEFAULT NULL');
        $this->addSql('ALTER TABLE `users` ADD CONSTRAINT FK_1483A5E9EA7100A1 FOREIGN KEY (id_preview_attachment) REFERENCES `attachments` (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_1483A5E9EA7100A1 ON `users` (id_preview_attachment)');
    }

    public function mySQLDown(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE `attachment_types` DROP FOREIGN KEY FK_EFAED719EA7100A1');
        $this->addSql('DROP INDEX IDX_EFAED719EA7100A1 ON `attachment_types`');
        $this->addSql('ALTER TABLE `attachment_types` CHANGE id_preview_attachment id_preview_attachement INT DEFAULT NULL');
        $this->addSql('ALTER TABLE `attachment_types` ADD CONSTRAINT FK_EFAED7196DEDCEC2 FOREIGN KEY (id_preview_attachement) REFERENCES attachments (id)');
        $this->addSql('CREATE INDEX IDX_EFAED7196DEDCEC2 ON `attachment_types` (id_preview_attachement)');
        $this->addSql('ALTER TABLE `categories` DROP FOREIGN KEY FK_3AF34668EA7100A1');
        $this->addSql('DROP INDEX IDX_3AF34668EA7100A1 ON `categories`');
        $this->addSql('ALTER TABLE `categories` CHANGE id_preview_attachment id_preview_attachement INT DEFAULT NULL');
        $this->addSql('ALTER TABLE `categories` ADD CONSTRAINT FK_3AF346686DEDCEC2 FOREIGN KEY (id_preview_attachement) REFERENCES attachments (id)');
        $this->addSql('CREATE INDEX IDX_3AF346686DEDCEC2 ON `categories` (id_preview_attachement)');
        $this->addSql('ALTER TABLE currencies DROP FOREIGN KEY FK_37C44693EA7100A1');
        $this->addSql('DROP INDEX IDX_37C44693EA7100A1 ON currencies');
        $this->addSql('ALTER TABLE currencies CHANGE id_preview_attachment id_preview_attachement INT DEFAULT NULL');
        $this->addSql('ALTER TABLE currencies ADD CONSTRAINT FK_37C446936DEDCEC2 FOREIGN KEY (id_preview_attachement) REFERENCES attachments (id)');
        $this->addSql('CREATE INDEX IDX_37C446936DEDCEC2 ON currencies (id_preview_attachement)');
        $this->addSql('ALTER TABLE `footprints` DROP FOREIGN KEY FK_A34D68A2EA7100A1');
        $this->addSql('DROP INDEX IDX_A34D68A2EA7100A1 ON `footprints`');
        $this->addSql('ALTER TABLE `footprints` CHANGE id_preview_attachment id_preview_attachement INT DEFAULT NULL');
        $this->addSql('ALTER TABLE `footprints` ADD CONSTRAINT FK_A34D68A26DEDCEC2 FOREIGN KEY (id_preview_attachement) REFERENCES attachments (id)');
        $this->addSql('CREATE INDEX IDX_A34D68A26DEDCEC2 ON `footprints` (id_preview_attachement)');
        $this->addSql('ALTER TABLE `groups` DROP FOREIGN KEY FK_F06D3970EA7100A1');
        $this->addSql('DROP INDEX IDX_F06D3970EA7100A1 ON `groups`');
        $this->addSql('ALTER TABLE `groups` CHANGE permissions_data permissions_data LONGTEXT NOT NULL COMMENT \'(DC2Type:json)\', CHANGE id_preview_attachment id_preview_attachement INT DEFAULT NULL');
        $this->addSql('ALTER TABLE `groups` ADD CONSTRAINT FK_F06D39706DEDCEC2 FOREIGN KEY (id_preview_attachement) REFERENCES attachments (id)');
        $this->addSql('CREATE INDEX IDX_F06D39706DEDCEC2 ON `groups` (id_preview_attachement)');
        $this->addSql('ALTER TABLE label_profiles DROP FOREIGN KEY FK_C93E9CF5EA7100A1');
        $this->addSql('DROP INDEX IDX_C93E9CF5EA7100A1 ON label_profiles');
        $this->addSql('ALTER TABLE label_profiles CHANGE id_preview_attachment id_preview_attachement INT DEFAULT NULL');
        $this->addSql('ALTER TABLE label_profiles ADD CONSTRAINT FK_C93E9CF56DEDCEC2 FOREIGN KEY (id_preview_attachement) REFERENCES attachments (id)');
        $this->addSql('CREATE INDEX IDX_C93E9CF56DEDCEC2 ON label_profiles (id_preview_attachement)');
        $this->addSql('ALTER TABLE log CHANGE level level TINYINT(1) NOT NULL');
        $this->addSql('ALTER TABLE `manufacturers` DROP FOREIGN KEY FK_94565B12EA7100A1');
        $this->addSql('DROP INDEX IDX_94565B12EA7100A1 ON `manufacturers`');
        $this->addSql('ALTER TABLE `manufacturers` CHANGE id_preview_attachment id_preview_attachement INT DEFAULT NULL');
        $this->addSql('ALTER TABLE `manufacturers` ADD CONSTRAINT FK_94565B126DEDCEC2 FOREIGN KEY (id_preview_attachement) REFERENCES attachments (id)');
        $this->addSql('CREATE INDEX IDX_94565B126DEDCEC2 ON `manufacturers` (id_preview_attachement)');
        $this->addSql('ALTER TABLE `measurement_units` DROP FOREIGN KEY FK_F5AF83CFEA7100A1');
        $this->addSql('DROP INDEX IDX_F5AF83CFEA7100A1 ON `measurement_units`');
        $this->addSql('ALTER TABLE `measurement_units` CHANGE id_preview_attachment id_preview_attachement INT DEFAULT NULL');
        $this->addSql('ALTER TABLE `measurement_units` ADD CONSTRAINT FK_F5AF83CF6DEDCEC2 FOREIGN KEY (id_preview_attachement) REFERENCES attachments (id)');
        $this->addSql('CREATE INDEX IDX_F5AF83CF6DEDCEC2 ON `measurement_units` (id_preview_attachement)');
        $this->addSql('ALTER TABLE `parts` DROP FOREIGN KEY FK_6940A7FEEA7100A1');
        $this->addSql('DROP INDEX IDX_6940A7FEEA7100A1 ON `parts`');
        $this->addSql('ALTER TABLE `parts` CHANGE id_preview_attachment id_preview_attachement INT DEFAULT NULL');
        $this->addSql('ALTER TABLE `parts` ADD CONSTRAINT FK_6940A7FE6DEDCEC2 FOREIGN KEY (id_preview_attachement) REFERENCES attachments (id)');
        $this->addSql('CREATE INDEX IDX_6940A7FE6DEDCEC2 ON `parts` (id_preview_attachement)');
        $this->addSql('ALTER TABLE projects DROP FOREIGN KEY FK_5C93B3A4EA7100A1');
        $this->addSql('DROP INDEX IDX_5C93B3A4EA7100A1 ON projects');
        $this->addSql('ALTER TABLE projects CHANGE id_preview_attachment id_preview_attachement INT DEFAULT NULL');
        $this->addSql('ALTER TABLE projects ADD CONSTRAINT FK_11074E9A6DEDCEC2 FOREIGN KEY (id_preview_attachement) REFERENCES attachments (id)');
        $this->addSql('CREATE INDEX IDX_5C93B3A46DEDCEC2 ON projects (id_preview_attachement)');
        $this->addSql('ALTER TABLE `storelocations` DROP FOREIGN KEY FK_7517020EA7100A1');
        $this->addSql('DROP INDEX IDX_7517020EA7100A1 ON `storelocations`');
        $this->addSql('ALTER TABLE `storelocations` CHANGE id_preview_attachment id_preview_attachement INT DEFAULT NULL');
        $this->addSql('ALTER TABLE `storelocations` ADD CONSTRAINT FK_75170206DEDCEC2 FOREIGN KEY (id_preview_attachement) REFERENCES attachments (id)');
        $this->addSql('CREATE INDEX IDX_75170206DEDCEC2 ON `storelocations` (id_preview_attachement)');
        $this->addSql('ALTER TABLE `suppliers` DROP FOREIGN KEY FK_AC28B95CEA7100A1');
        $this->addSql('DROP INDEX IDX_AC28B95CEA7100A1 ON `suppliers`');
        $this->addSql('ALTER TABLE `suppliers` CHANGE id_preview_attachment id_preview_attachement INT DEFAULT NULL');
        $this->addSql('ALTER TABLE `suppliers` ADD CONSTRAINT FK_AC28B95C6DEDCEC2 FOREIGN KEY (id_preview_attachement) REFERENCES attachments (id)');
        $this->addSql('CREATE INDEX IDX_AC28B95C6DEDCEC2 ON `suppliers` (id_preview_attachement)');
        $this->addSql('ALTER TABLE `users` DROP FOREIGN KEY FK_1483A5E9EA7100A1');
        $this->addSql('DROP INDEX IDX_1483A5E9EA7100A1 ON `users`');
        $this->addSql('ALTER TABLE `users` CHANGE permissions_data permissions_data LONGTEXT NOT NULL COMMENT \'(DC2Type:json)\', CHANGE id_preview_attachment id_preview_attachement INT DEFAULT NULL');
        $this->addSql('ALTER TABLE `users` ADD CONSTRAINT FK_1483A5E96DEDCEC2 FOREIGN KEY (id_preview_attachement) REFERENCES attachments (id)');
        $this->addSql('CREATE INDEX IDX_1483A5E96DEDCEC2 ON `users` (id_preview_attachement)');
    }

    public function sqLiteUp(Schema $schema): void
    {
        // TODO: Implement sqLiteUp() method.
    }

    public function sqLiteDown(Schema $schema): void
    {
        // TODO: Implement sqLiteDown() method.
    }
}
