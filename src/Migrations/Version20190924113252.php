<?php
/**
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 * Copyright (C) 2019 Jan Böhmer (https://github.com/jbtronics)
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA
 */

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20190924113252 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf('mysql' !== $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE users ADD id_preview_attachement INT DEFAULT NULL, ADD pw_reset_token VARCHAR(255) DEFAULT NULL, ADD pw_reset_expires DATETIME DEFAULT NULL, ADD disabled TINYINT(1) NOT NULL, DROP config_image_path');
        $this->addSql('ALTER TABLE users ADD CONSTRAINT FK_1483A5E96DEDCEC2 FOREIGN KEY (id_preview_attachement) REFERENCES `attachments` (id)');
        $this->addSql('CREATE INDEX IDX_1483A5E96DEDCEC2 ON users (id_preview_attachement)');
        $this->addSql('ALTER TABLE attachment_types ADD id_preview_attachement INT DEFAULT NULL');
        $this->addSql('ALTER TABLE attachment_types ADD CONSTRAINT FK_EFAED7196DEDCEC2 FOREIGN KEY (id_preview_attachement) REFERENCES `attachments` (id)');
        $this->addSql('CREATE INDEX IDX_EFAED7196DEDCEC2 ON attachment_types (id_preview_attachement)');
        $this->addSql('ALTER TABLE categories ADD id_preview_attachement INT DEFAULT NULL');
        $this->addSql('ALTER TABLE categories ADD CONSTRAINT FK_3AF346686DEDCEC2 FOREIGN KEY (id_preview_attachement) REFERENCES `attachments` (id)');
        $this->addSql('CREATE INDEX IDX_3AF346686DEDCEC2 ON categories (id_preview_attachement)');
        $this->addSql('ALTER TABLE currencies ADD id_preview_attachement INT DEFAULT NULL');
        $this->addSql('ALTER TABLE currencies ADD CONSTRAINT FK_37C446936DEDCEC2 FOREIGN KEY (id_preview_attachement) REFERENCES `attachments` (id)');
        $this->addSql('CREATE INDEX IDX_37C446936DEDCEC2 ON currencies (id_preview_attachement)');
        $this->addSql('ALTER TABLE devices ADD id_preview_attachement INT DEFAULT NULL');
        $this->addSql('ALTER TABLE devices ADD CONSTRAINT FK_11074E9A6DEDCEC2 FOREIGN KEY (id_preview_attachement) REFERENCES `attachments` (id)');
        $this->addSql('CREATE INDEX IDX_11074E9A6DEDCEC2 ON devices (id_preview_attachement)');

        $this->addSql('ALTER TABLE attachments DROP FOREIGN KEY FK_47C4FAD61F1F2A24');
        $this->addSql('ALTER TABLE attachments ADD original_filename VARCHAR(255) DEFAULT NULL, CHANGE filename path VARCHAR(255) NOT NULL');

        //Before we drop the filename and filename_3d properties we have to migrate the data to attachments
        $this->addSql("INSERT IGNORE INTO `attachment_types` (`id`, `name`, `parent_id`, `comment`, `datetime_added`, `last_modified`, `filetype_filter`) 
            VALUES 
            (1000, 'Footprints', NULL, 'Created during automatic migration of the footprint files.', current_timestamp(), current_timestamp(), 'image/*'),
            (1001, 'Footprints (3D)', NULL, 'Created during automatic migration of the footprint files.', current_timestamp(), current_timestamp(), '.x3d')
        ");

        //Add a attachment for each footprint attachment
        $this->addSql(
            'INSERT IGNORE INTO attachments (element_id, type_id, name, class_name, path, last_modified, datetime_added) '.
            "SELECT footprints.id, 1000, 'Footprint', 'Footprint',  REPLACE(footprints.filename, '%BASE%/img/footprints', '%FOOTPRINTS%' ), NOW(), NOW() FROM footprints ".
            "WHERE footprints.filename <> ''"
        );

        //Do the same for 3D Footprints
        $this->addSql(
            'INSERT IGNORE INTO attachments (element_id, type_id, name, class_name, path, last_modified, datetime_added) '.
            "SELECT footprints.id, 1001, 'Footprint 3D', 'Footprint',  REPLACE(footprints.filename_3d, '%BASE%/models', '%FOOTPRINTS3D%' ), NOW(), NOW() FROM footprints ".
            "WHERE footprints.filename_3d <> ''"
        );

        $this->addSql('ALTER TABLE footprints ADD id_footprint_3d INT DEFAULT NULL, ADD id_preview_attachement INT DEFAULT NULL, DROP filename, DROP filename_3d');
        $this->addSql('ALTER TABLE footprints ADD CONSTRAINT FK_A34D68A232A38C34 FOREIGN KEY (id_footprint_3d) REFERENCES `attachments` (id)');
        $this->addSql('ALTER TABLE footprints ADD CONSTRAINT FK_A34D68A26DEDCEC2 FOREIGN KEY (id_preview_attachement) REFERENCES `attachments` (id)');
        $this->addSql('CREATE INDEX IDX_A34D68A232A38C34 ON footprints (id_footprint_3d)');
        $this->addSql('CREATE INDEX IDX_A34D68A26DEDCEC2 ON footprints (id_preview_attachement)');

        //Set the created footprint attachments as preview image / 3D footprint image
        $this->addSql('UPDATE footprints, attachments
            SET footprints.id_preview_attachement = attachments.id
            WHERE attachments.class_name = "Footprint" AND attachments.element_id = footprints.id
            AND attachments.type_id = 1000;
        ');

        $this->addSql('UPDATE footprints, attachments
            SET footprints.id_footprint_3d = attachments.id
            WHERE attachments.class_name = "Footprint" AND attachments.element_id = footprints.id
            AND attachments.type_id = 1001;
        ');

        $this->addSql('ALTER TABLE manufacturers ADD id_preview_attachement INT DEFAULT NULL');
        $this->addSql('ALTER TABLE manufacturers ADD CONSTRAINT FK_94565B126DEDCEC2 FOREIGN KEY (id_preview_attachement) REFERENCES `attachments` (id)');
        $this->addSql('CREATE INDEX IDX_94565B126DEDCEC2 ON manufacturers (id_preview_attachement)');
        $this->addSql('ALTER TABLE measurement_units ADD id_preview_attachement INT DEFAULT NULL');
        $this->addSql('ALTER TABLE measurement_units ADD CONSTRAINT FK_F5AF83CF6DEDCEC2 FOREIGN KEY (id_preview_attachement) REFERENCES `attachments` (id)');
        $this->addSql('CREATE INDEX IDX_F5AF83CF6DEDCEC2 ON measurement_units (id_preview_attachement)');
        $this->addSql('ALTER TABLE storelocations ADD id_preview_attachement INT DEFAULT NULL');
        $this->addSql('ALTER TABLE storelocations ADD CONSTRAINT FK_75170206DEDCEC2 FOREIGN KEY (id_preview_attachement) REFERENCES `attachments` (id)');
        $this->addSql('CREATE INDEX IDX_75170206DEDCEC2 ON storelocations (id_preview_attachement)');
        $this->addSql('ALTER TABLE suppliers ADD id_preview_attachement INT DEFAULT NULL');
        $this->addSql('ALTER TABLE suppliers ADD CONSTRAINT FK_AC28B95C6DEDCEC2 FOREIGN KEY (id_preview_attachement) REFERENCES `attachments` (id)');
        $this->addSql('CREATE INDEX IDX_AC28B95C6DEDCEC2 ON suppliers (id_preview_attachement)');
        $this->addSql('ALTER TABLE `groups` ADD id_preview_attachement INT DEFAULT NULL');
        $this->addSql('ALTER TABLE `groups` ADD CONSTRAINT FK_F06D39706DEDCEC2 FOREIGN KEY (id_preview_attachement) REFERENCES `attachments` (id)');
        $this->addSql('CREATE INDEX IDX_F06D39706DEDCEC2 ON `groups` (id_preview_attachement)');
        $this->addSql('ALTER TABLE parts DROP FOREIGN KEY FK_6940A7FEEBBCC786');
        $this->addSql('DROP INDEX IDX_6940A7FEEBBCC786 ON parts');
        $this->addSql('ALTER TABLE parts CHANGE id_master_picture_attachement id_preview_attachement INT DEFAULT NULL');
        $this->addSql('ALTER TABLE parts ADD CONSTRAINT FK_6940A7FE6DEDCEC2 FOREIGN KEY (id_preview_attachement) REFERENCES `attachments` (id)');
        $this->addSql('CREATE INDEX IDX_6940A7FE6DEDCEC2 ON parts (id_preview_attachement)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf('mysql' !== $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE `attachment_types` DROP FOREIGN KEY FK_EFAED7196DEDCEC2');
        $this->addSql('DROP INDEX IDX_EFAED7196DEDCEC2 ON `attachment_types`');
        $this->addSql('ALTER TABLE `attachment_types` DROP id_preview_attachement');
        $this->addSql('ALTER TABLE `attachments` DROP original_filename, CHANGE path filename VARCHAR(255) NOT NULL COLLATE utf8_unicode_ci');
        $this->addSql('ALTER TABLE `attachments` ADD CONSTRAINT FK_47C4FAD61F1F2A24 FOREIGN KEY (element_id) REFERENCES parts (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE `categories` DROP FOREIGN KEY FK_3AF346686DEDCEC2');
        $this->addSql('DROP INDEX IDX_3AF346686DEDCEC2 ON `categories`');
        $this->addSql('ALTER TABLE `categories` DROP id_preview_attachement');
        $this->addSql('ALTER TABLE currencies DROP FOREIGN KEY FK_37C446936DEDCEC2');
        $this->addSql('DROP INDEX IDX_37C446936DEDCEC2 ON currencies');
        $this->addSql('ALTER TABLE currencies DROP id_preview_attachement');
        $this->addSql('ALTER TABLE `devices` DROP FOREIGN KEY FK_11074E9A6DEDCEC2');
        $this->addSql('DROP INDEX IDX_11074E9A6DEDCEC2 ON `devices`');
        $this->addSql('ALTER TABLE `devices` DROP id_preview_attachement');
        $this->addSql('ALTER TABLE `footprints` DROP FOREIGN KEY FK_A34D68A232A38C34');
        $this->addSql('ALTER TABLE `footprints` DROP FOREIGN KEY FK_A34D68A26DEDCEC2');
        $this->addSql('DROP INDEX IDX_A34D68A232A38C34 ON `footprints`');
        $this->addSql('DROP INDEX IDX_A34D68A26DEDCEC2 ON `footprints`');
        $this->addSql('ALTER TABLE `footprints` ADD filename MEDIUMTEXT NOT NULL COLLATE utf8_unicode_ci, ADD filename_3d MEDIUMTEXT NOT NULL COLLATE utf8_unicode_ci, DROP id_footprint_3d, DROP id_preview_attachement');
        $this->addSql('ALTER TABLE `groups` DROP FOREIGN KEY FK_F06D39706DEDCEC2');
        $this->addSql('DROP INDEX IDX_F06D39706DEDCEC2 ON `groups`');
        $this->addSql('ALTER TABLE `groups` DROP id_preview_attachement');
        $this->addSql('ALTER TABLE `manufacturers` DROP FOREIGN KEY FK_94565B126DEDCEC2');
        $this->addSql('DROP INDEX IDX_94565B126DEDCEC2 ON `manufacturers`');
        $this->addSql('ALTER TABLE `manufacturers` DROP id_preview_attachement');
        $this->addSql('ALTER TABLE `measurement_units` DROP FOREIGN KEY FK_F5AF83CF6DEDCEC2');
        $this->addSql('DROP INDEX IDX_F5AF83CF6DEDCEC2 ON `measurement_units`');
        $this->addSql('ALTER TABLE `measurement_units` DROP id_preview_attachement');
        $this->addSql('ALTER TABLE `parts` DROP FOREIGN KEY FK_6940A7FE6DEDCEC2');
        $this->addSql('DROP INDEX IDX_6940A7FE6DEDCEC2 ON `parts`');
        $this->addSql('ALTER TABLE `parts` CHANGE id_preview_attachement id_master_picture_attachement INT DEFAULT NULL');
        $this->addSql('ALTER TABLE `parts` ADD CONSTRAINT FK_6940A7FEEBBCC786 FOREIGN KEY (id_master_picture_attachement) REFERENCES attachments (id)');
        $this->addSql('CREATE INDEX IDX_6940A7FEEBBCC786 ON `parts` (id_master_picture_attachement)');
        $this->addSql('ALTER TABLE `storelocations` DROP FOREIGN KEY FK_75170206DEDCEC2');
        $this->addSql('DROP INDEX IDX_75170206DEDCEC2 ON `storelocations`');
        $this->addSql('ALTER TABLE `storelocations` DROP id_preview_attachement');
        $this->addSql('ALTER TABLE `suppliers` DROP FOREIGN KEY FK_AC28B95C6DEDCEC2');
        $this->addSql('DROP INDEX IDX_AC28B95C6DEDCEC2 ON `suppliers`');
        $this->addSql('ALTER TABLE `suppliers` DROP id_preview_attachement');
        $this->addSql('ALTER TABLE `users` DROP FOREIGN KEY FK_1483A5E96DEDCEC2');
        $this->addSql('DROP INDEX IDX_1483A5E96DEDCEC2 ON `users`');
        $this->addSql('ALTER TABLE `users` ADD config_image_path TEXT NOT NULL COLLATE utf8_general_ci, DROP id_preview_attachement, DROP pw_reset_token, DROP pw_reset_expires, DROP disabled');
    }
}
