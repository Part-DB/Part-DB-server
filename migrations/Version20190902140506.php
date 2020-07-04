<?php
/**
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 * Copyright (C) 2019 - 2020 Jan Böhmer (https://github.com/jbtronics)
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Migration\AbstractMultiPlatformMigration;
use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20190902140506 extends AbstractMultiPlatformMigration
{
    public function getDescription(): string
    {
        return 'Upgrade database from old Part-DB 0.5 Version (dbVersion 26)';
    }

    public function mySQLUp(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf('mysql' !== $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'mysql\'.');

        try {
            //Check if we can use this migration method:
            $version = (int) $this->connection->fetchColumn("SELECT keyValue AS version FROM `internal` WHERE `keyName` = 'dbVersion'");
            $this->abortIf(26 !== $version, 'This database migration can only be used if the database version is 26! Install Part-DB 0.5.6 and update database there!');
        } catch (DBALException $dBALException) {
            //when the table was not found, then you can not use this migration
            $this->warnIf(true, 'Empty database detected. Skip migration.');
        }

        //Deactive SQL Modes (especially NO_ZERO_DATE, which prevents updating)
        //$this->addSql("SET sql_mode = ''");

        //Rename attachment tables (fix typos)
        $this->addSql('RENAME TABLE `attachement_types` TO `attachment_types`;');
        $this->addSql('RENAME TABLE `attachements` TO `attachments`;');

        $this->addSql('CREATE TABLE currencies (id INT AUTO_INCREMENT NOT NULL, parent_id INT DEFAULT NULL, iso_code VARCHAR(255) NOT NULL, exchange_rate NUMERIC(11, 5) DEFAULT NULL, comment LONGTEXT NOT NULL, not_selectable TINYINT(1) NOT NULL, name VARCHAR(255) NOT NULL, last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, INDEX IDX_37C44693727ACA70 (parent_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE `measurement_units` (id INT AUTO_INCREMENT NOT NULL, parent_id INT DEFAULT NULL, unit VARCHAR(255) DEFAULT NULL, is_integer TINYINT(1) NOT NULL, use_si_prefix TINYINT(1) NOT NULL, comment LONGTEXT NOT NULL, not_selectable TINYINT(1) NOT NULL, name VARCHAR(255) NOT NULL, last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, INDEX IDX_F5AF83CF727ACA70 (parent_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE part_lots (id INT AUTO_INCREMENT NOT NULL, id_store_location INT DEFAULT NULL, id_part INT NOT NULL, description LONGTEXT NOT NULL, comment LONGTEXT NOT NULL, expiration_date DATETIME DEFAULT NULL, instock_unknown TINYINT(1) NOT NULL, amount DOUBLE PRECISION NOT NULL, needs_refill TINYINT(1) NOT NULL, last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, INDEX IDX_EBC8F9435D8F4B37 (id_store_location), INDEX IDX_EBC8F943C22F6CC4 (id_part), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');

        /* Migrate the part locations for parts with known instock */
        $this->addSql(
            'INSERT IGNORE INTO part_lots (id_part, id_store_location, amount, instock_unknown, last_modified, datetime_added) '.
            'SELECT parts.id, parts.id_storelocation,  parts.instock, 0, NOW(), NOW() FROM parts '.
            'WHERE parts.instock >= 0'
        );

        //Migrate part locations for parts with unknown instock
        $this->addSql(
            'INSERT IGNORE INTO part_lots (id_part, id_store_location, amount, instock_unknown, last_modified, datetime_added) '.
            'SELECT parts.id, parts.id_storelocation, 0, 1, NOW(), NOW() FROM parts '.
            'WHERE parts.instock = -2'
        );

        $this->addSql('ALTER TABLE currencies ADD CONSTRAINT FK_37C44693727ACA70 FOREIGN KEY (parent_id) REFERENCES currencies (id)');
        $this->addSql('ALTER TABLE `measurement_units` ADD CONSTRAINT FK_F5AF83CF727ACA70 FOREIGN KEY (parent_id) REFERENCES `measurement_units` (id)');
        $this->addSql('ALTER TABLE part_lots ADD CONSTRAINT FK_EBC8F9435D8F4B37 FOREIGN KEY (id_store_location) REFERENCES `storelocations` (id)');
        $this->addSql('ALTER TABLE part_lots ADD CONSTRAINT FK_EBC8F943C22F6CC4 FOREIGN KEY (id_part) REFERENCES `parts` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE parts DROP INDEX parts_order_orderdetails_id_k, ADD UNIQUE INDEX UNIQ_6940A7FE81081E9B (order_orderdetails_id)');
        $this->addSql('ALTER TABLE parts DROP FOREIGN KEY parts_id_storelocation_fk');
        $this->addSql('DROP INDEX favorite ON parts');
        $this->addSql('DROP INDEX parts_id_storelocation_k ON parts');
        $this->addSql('ALTER TABLE parts DROP FOREIGN KEY parts_id_footprint_fk');
        $this->addSql('ALTER TABLE parts DROP FOREIGN KEY parts_id_manufacturer_fk');
        $this->addSql('ALTER TABLE parts CHANGE mininstock minamount DOUBLE PRECISION NOT NULL, ADD id_part_unit INT DEFAULT NULL, ADD manufacturer_product_number VARCHAR(255) NOT NULL, ADD manufacturing_status VARCHAR(255) DEFAULT NULL, ADD needs_review TINYINT(1) NOT NULL, ADD tags LONGTEXT NOT NULL, ADD mass DOUBLE PRECISION DEFAULT NULL, DROP instock, CHANGE id_category id_category INT NOT NULL, CHANGE name name VARCHAR(255) NOT NULL, CHANGE order_quantity order_quantity INT NOT NULL, CHANGE manual_order manual_order TINYINT(1) NOT NULL, CHANGE manufacturer_product_url manufacturer_product_url VARCHAR(255) NOT NULL, CHANGE last_modified last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, CHANGE favorite favorite TINYINT(1) NOT NULL, DROP id_storelocation');
        $this->addSql('ALTER TABLE parts ADD CONSTRAINT FK_6940A7FE5697F554 FOREIGN KEY (id_category) REFERENCES `categories` (id)');
        $this->addSql('ALTER TABLE parts ADD CONSTRAINT FK_6940A7FEEBBCC786 FOREIGN KEY (id_master_picture_attachement) REFERENCES `attachments` (id)');
        $this->addSql('ALTER TABLE parts ADD CONSTRAINT FK_6940A7FE2626CEF9 FOREIGN KEY (id_part_unit) REFERENCES `measurement_units` (id)');
        $this->addSql('CREATE INDEX IDX_6940A7FEEBBCC786 ON parts (id_master_picture_attachement)');
        $this->addSql('CREATE INDEX IDX_6940A7FE2626CEF9 ON parts (id_part_unit)');
        $this->addSql('CREATE INDEX IDX_6940A7FE5697F554 ON parts (id_category)');
        $this->addSql('DROP INDEX parts_id_category_k ON parts');
        $this->addSql('DROP INDEX parts_id_footprint_k ON parts');
        $this->addSql('CREATE INDEX IDX_6940A7FE7E371A10 ON parts (id_footprint)');
        $this->addSql('DROP INDEX parts_id_manufacturer_k ON parts');
        $this->addSql('CREATE INDEX IDX_6940A7FE1ECB93AE ON parts (id_manufacturer)');
        $this->addSql('ALTER TABLE parts ADD CONSTRAINT parts_id_footprint_fk FOREIGN KEY (id_footprint) REFERENCES footprints (id)');
        $this->addSql('ALTER TABLE parts ADD CONSTRAINT parts_id_manufacturer_fk FOREIGN KEY (id_manufacturer) REFERENCES manufacturers (id)');
        $this->addSql('ALTER TABLE attachment_types DROP FOREIGN KEY attachement_types_parent_id_fk');
        $this->addSql('ALTER TABLE attachment_types ADD filetype_filter LONGTEXT NOT NULL, ADD not_selectable TINYINT(1) NOT NULL, CHANGE name name VARCHAR(255) NOT NULL, CHANGE comment comment LONGTEXT NOT NULL, CHANGE last_modified last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL');
        $this->addSql('DROP INDEX attachement_types_parent_id_k ON attachment_types');
        $this->addSql('CREATE INDEX IDX_EFAED719727ACA70 ON attachment_types (parent_id)');
        $this->addSql('ALTER TABLE attachment_types ADD CONSTRAINT attachement_types_parent_id_fk FOREIGN KEY (parent_id) REFERENCES attachment_types (id)');
        $this->addSql('ALTER TABLE categories DROP FOREIGN KEY categories_parent_id_fk');
        $this->addSql('ALTER TABLE categories ADD not_selectable TINYINT(1) NOT NULL, CHANGE name name VARCHAR(255) NOT NULL, CHANGE disable_footprints disable_footprints TINYINT(1) NOT NULL, CHANGE disable_manufacturers disable_manufacturers TINYINT(1) NOT NULL, CHANGE disable_autodatasheets disable_autodatasheets TINYINT(1) NOT NULL, CHANGE disable_properties disable_properties TINYINT(1) NOT NULL, CHANGE comment comment LONGTEXT NOT NULL, CHANGE last_modified last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL');
        $this->addSql('DROP INDEX categories_parent_id_k ON categories');
        $this->addSql('CREATE INDEX IDX_3AF34668727ACA70 ON categories (parent_id)');
        $this->addSql('ALTER TABLE categories ADD CONSTRAINT categories_parent_id_fk FOREIGN KEY (parent_id) REFERENCES categories (id)');
        $this->addSql('ALTER TABLE devices DROP FOREIGN KEY devices_parent_id_fk');
        $this->addSql('ALTER TABLE devices ADD not_selectable TINYINT(1) NOT NULL, CHANGE name name VARCHAR(255) NOT NULL, CHANGE order_quantity order_quantity INT NOT NULL, CHANGE order_only_missing_parts order_only_missing_parts TINYINT(1) NOT NULL, CHANGE last_modified last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, CHANGE comment comment LONGTEXT NOT NULL');
        $this->addSql('DROP INDEX devices_parent_id_k ON devices');
        $this->addSql('CREATE INDEX IDX_11074E9A727ACA70 ON devices (parent_id)');
        $this->addSql('ALTER TABLE devices ADD CONSTRAINT devices_parent_id_fk FOREIGN KEY (parent_id) REFERENCES devices (id)');
        $this->addSql('ALTER TABLE footprints DROP FOREIGN KEY footprints_parent_id_fk');
        $this->addSql('ALTER TABLE footprints ADD not_selectable TINYINT(1) NOT NULL, CHANGE name name VARCHAR(255) NOT NULL, CHANGE comment comment LONGTEXT NOT NULL, CHANGE last_modified last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL');
        $this->addSql('DROP INDEX footprints_parent_id_k ON footprints');
        $this->addSql('CREATE INDEX IDX_A34D68A2727ACA70 ON footprints (parent_id)');
        $this->addSql('ALTER TABLE footprints ADD CONSTRAINT footprints_parent_id_fk FOREIGN KEY (parent_id) REFERENCES footprints (id)');
        $this->addSql('ALTER TABLE manufacturers DROP FOREIGN KEY manufacturers_parent_id_fk');
        $this->addSql('ALTER TABLE manufacturers ADD not_selectable TINYINT(1) NOT NULL, CHANGE name name VARCHAR(255) NOT NULL, CHANGE address address VARCHAR(255) NOT NULL, CHANGE phone_number phone_number VARCHAR(255) NOT NULL, CHANGE fax_number fax_number VARCHAR(255) NOT NULL, CHANGE email_address email_address VARCHAR(255) NOT NULL, CHANGE website website VARCHAR(255) NOT NULL, CHANGE auto_product_url auto_product_url VARCHAR(255) NOT NULL, CHANGE comment comment LONGTEXT NOT NULL, CHANGE last_modified last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL');
        $this->addSql('DROP INDEX manufacturers_parent_id_k ON manufacturers');
        $this->addSql('CREATE INDEX IDX_94565B12727ACA70 ON manufacturers (parent_id)');
        $this->addSql('ALTER TABLE manufacturers ADD CONSTRAINT manufacturers_parent_id_fk FOREIGN KEY (parent_id) REFERENCES manufacturers (id)');
        $this->addSql('ALTER TABLE storelocations DROP FOREIGN KEY storelocations_parent_id_fk');
        $this->addSql('ALTER TABLE storelocations ADD storage_type_id INT DEFAULT NULL, ADD only_single_part TINYINT(1) NOT NULL, ADD limit_to_existing_parts TINYINT(1) NOT NULL, ADD not_selectable TINYINT(1) NOT NULL, CHANGE name name VARCHAR(255) NOT NULL, CHANGE is_full is_full TINYINT(1) NOT NULL, CHANGE comment comment LONGTEXT NOT NULL, CHANGE last_modified last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL');
        $this->addSql('ALTER TABLE storelocations ADD CONSTRAINT FK_7517020B270BFF1 FOREIGN KEY (storage_type_id) REFERENCES `measurement_units` (id)');
        $this->addSql('CREATE INDEX IDX_7517020B270BFF1 ON storelocations (storage_type_id)');
        $this->addSql('DROP INDEX storelocations_parent_id_k ON storelocations');
        $this->addSql('CREATE INDEX IDX_7517020727ACA70 ON storelocations (parent_id)');
        $this->addSql('ALTER TABLE storelocations ADD CONSTRAINT storelocations_parent_id_fk FOREIGN KEY (parent_id) REFERENCES storelocations (id)');
        $this->addSql('ALTER TABLE suppliers DROP FOREIGN KEY suppliers_parent_id_fk');
        $this->addSql('ALTER TABLE suppliers ADD default_currency_id INT DEFAULT NULL, ADD shipping_costs NUMERIC(11, 5) DEFAULT NULL, ADD not_selectable TINYINT(1) NOT NULL, CHANGE name name VARCHAR(255) NOT NULL, CHANGE address address VARCHAR(255) NOT NULL, CHANGE phone_number phone_number VARCHAR(255) NOT NULL, CHANGE fax_number fax_number VARCHAR(255) NOT NULL, CHANGE email_address email_address VARCHAR(255) NOT NULL, CHANGE website website VARCHAR(255) NOT NULL, CHANGE auto_product_url auto_product_url VARCHAR(255) NOT NULL, CHANGE comment comment LONGTEXT NOT NULL, CHANGE last_modified last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL');
        $this->addSql('ALTER TABLE suppliers ADD CONSTRAINT FK_AC28B95CECD792C0 FOREIGN KEY (default_currency_id) REFERENCES currencies (id)');
        $this->addSql('CREATE INDEX IDX_AC28B95CECD792C0 ON suppliers (default_currency_id)');
        $this->addSql('DROP INDEX suppliers_parent_id_k ON suppliers');
        $this->addSql('CREATE INDEX IDX_AC28B95C727ACA70 ON suppliers (parent_id)');
        $this->addSql('ALTER TABLE suppliers ADD CONSTRAINT suppliers_parent_id_fk FOREIGN KEY (parent_id) REFERENCES suppliers (id)');
        $this->addSql('DROP INDEX attachements_class_name_k ON attachments');
        $this->addSql('ALTER TABLE attachments DROP FOREIGN KEY attachements_type_id_fk');
        $this->addSql('ALTER TABLE attachments ADD datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, CHANGE type_id type_id INT DEFAULT NULL, CHANGE name name VARCHAR(255) NOT NULL, CHANGE filename filename VARCHAR(255) NOT NULL, CHANGE show_in_table show_in_table TINYINT(1) NOT NULL, CHANGE last_modified last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL');
        $this->addSql('ALTER TABLE attachments ADD CONSTRAINT FK_47C4FAD61F1F2A24 FOREIGN KEY (element_id) REFERENCES `parts` (id) ON DELETE CASCADE');
        $this->addSql('DROP INDEX attachements_type_id_fk ON attachments');
        $this->addSql('CREATE INDEX IDX_47C4FAD6C54C8C93 ON attachments (type_id)');
        $this->addSql('CREATE INDEX IDX_47C4FAD61F1F2A24 ON attachments (element_id)');
        $this->addSql('DROP INDEX attachements_element_id_k ON attachments');
        $this->addSql('ALTER TABLE attachments ADD CONSTRAINT attachements_type_id_fk FOREIGN KEY (type_id) REFERENCES attachment_types (id)');
        $this->addSql('ALTER TABLE users CHANGE name name VARCHAR(180) NOT NULL, CHANGE first_name first_name VARCHAR(255) DEFAULT NULL, CHANGE last_name last_name VARCHAR(255) DEFAULT NULL, CHANGE department department VARCHAR(255) DEFAULT NULL, CHANGE email email VARCHAR(255) DEFAULT NULL, CHANGE need_pw_change need_pw_change TINYINT(1) NOT NULL, CHANGE config_language config_language VARCHAR(255) DEFAULT NULL, CHANGE config_timezone config_timezone VARCHAR(255) DEFAULT NULL, CHANGE config_theme config_theme VARCHAR(255) DEFAULT NULL, CHANGE config_currency config_currency VARCHAR(255) NOT NULL, CHANGE perms_labels perms_labels INT NOT NULL, CHANGE last_modified last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL');
        $this->addSql('ALTER TABLE users ADD CONSTRAINT FK_1483A5E9FE54D947 FOREIGN KEY (group_id) REFERENCES `groups` (id)');
        $this->addSql('CREATE INDEX IDX_1483A5E9FE54D947 ON users (group_id)');
        $this->addSql('DROP INDEX name ON users');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_1483A5E95E237E06 ON users (name)');
        $this->addSql('DROP INDEX device_parts_combination_uk ON device_parts');
        $this->addSql('ALTER TABLE device_parts CHANGE id_part id_part INT DEFAULT NULL, CHANGE id_device id_device INT DEFAULT NULL, CHANGE quantity quantity INT NOT NULL');
        $this->addSql('ALTER TABLE device_parts ADD CONSTRAINT FK_AFC547992F180363 FOREIGN KEY (id_device) REFERENCES `devices` (id)');
        $this->addSql('ALTER TABLE device_parts ADD CONSTRAINT FK_AFC54799C22F6CC4 FOREIGN KEY (id_part) REFERENCES `parts` (id)');
        $this->addSql('CREATE INDEX IDX_AFC547992F180363 ON device_parts (id_device)');
        $this->addSql('DROP INDEX device_parts_id_device_k ON device_parts');
        $this->addSql('CREATE INDEX IDX_AFC54799C22F6CC4 ON device_parts (id_part)');
        $this->addSql('DROP INDEX device_parts_id_part_k ON device_parts');
        $this->addSql('ALTER TABLE orderdetails ADD last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, CHANGE id_supplier id_supplier INT DEFAULT NULL, CHANGE supplierpartnr supplierpartnr VARCHAR(255) NOT NULL, CHANGE obsolete obsolete TINYINT(1) NOT NULL, CHANGE supplier_product_url supplier_product_url VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE orderdetails ADD CONSTRAINT FK_489AFCDC4CE34BEC FOREIGN KEY (part_id) REFERENCES `parts` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE orderdetails ADD CONSTRAINT FK_489AFCDCCBF180EB FOREIGN KEY (id_supplier) REFERENCES `suppliers` (id)');
        $this->addSql('CREATE INDEX IDX_489AFCDC4CE34BEC ON orderdetails (part_id)');
        $this->addSql('DROP INDEX orderdetails_part_id_k ON orderdetails');
        $this->addSql('CREATE INDEX IDX_489AFCDCCBF180EB ON orderdetails (id_supplier)');
        $this->addSql('DROP INDEX orderdetails_id_supplier_k ON orderdetails');
        $this->addSql('DROP INDEX pricedetails_combination_uk ON pricedetails');
        $this->addSql('ALTER TABLE pricedetails ADD id_currency INT DEFAULT NULL, ADD datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, CHANGE price price NUMERIC(11, 5) NOT NULL, CHANGE price_related_quantity price_related_quantity DOUBLE PRECISION NOT NULL, CHANGE min_discount_quantity min_discount_quantity DOUBLE PRECISION NOT NULL, CHANGE manual_input manual_input TINYINT(1) NOT NULL');
        $this->addSql('ALTER TABLE pricedetails ADD CONSTRAINT FK_C68C44594A01DDC7 FOREIGN KEY (orderdetails_id) REFERENCES `orderdetails` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE pricedetails ADD CONSTRAINT FK_C68C4459398D64AA FOREIGN KEY (id_currency) REFERENCES currencies (id)');
        $this->addSql('CREATE INDEX IDX_C68C4459398D64AA ON pricedetails (id_currency)');
        $this->addSql('CREATE INDEX IDX_C68C44594A01DDC7 ON pricedetails (orderdetails_id)');
        $this->addSql('DROP INDEX pricedetails_orderdetails_id_k ON pricedetails');
        $this->addSql('DROP INDEX name ON groups');
        $this->addSql('ALTER TABLE groups ADD not_selectable TINYINT(1) NOT NULL, CHANGE name name VARCHAR(255) NOT NULL, CHANGE comment comment LONGTEXT NOT NULL, CHANGE perms_labels perms_labels INT NOT NULL, CHANGE last_modified last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL');
        $this->addSql('ALTER TABLE groups ADD CONSTRAINT FK_F06D3970727ACA70 FOREIGN KEY (parent_id) REFERENCES `groups` (id)');
        $this->addSql('CREATE INDEX IDX_F06D3970727ACA70 ON groups (parent_id)');

        //Fill empty timestamps with current date
        $tables = ['attachments', 'attachment_types', 'categories', 'devices', 'footprints', 'manufacturers',
            'orderdetails', 'pricedetails', 'storelocations', 'suppliers', ];

        foreach ($tables as $table) {
            $this->addSql("UPDATE ${table} SET datetime_added = NOW() WHERE datetime_added = '0000-00-00 00:00:00'");
            $this->addSql("UPDATE ${table} SET last_modified = datetime_added WHERE last_modified = '0000-00-00 00:00:00'");
        }

        //Set the dbVersion to a high value, to prevent the old Part-DB versions to upgrade DB!
        $this->addSql("UPDATE `internal` SET `keyValue` = '99' WHERE `internal`.`keyName` = 'dbVersion'");
    }

    public function mySQLDown(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf('mysql' !== $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE currencies DROP FOREIGN KEY FK_37C44693727ACA70');
        $this->addSql('ALTER TABLE `suppliers` DROP FOREIGN KEY FK_AC28B95CECD792C0');
        $this->addSql('ALTER TABLE `pricedetails` DROP FOREIGN KEY FK_C68C4459398D64AA');
        $this->addSql('ALTER TABLE `parts` DROP FOREIGN KEY FK_6940A7FE2626CEF9');
        $this->addSql('ALTER TABLE `measurement_units` DROP FOREIGN KEY FK_F5AF83CF727ACA70');
        $this->addSql('ALTER TABLE `storelocations` DROP FOREIGN KEY FK_7517020B270BFF1');
        $this->addSql('DROP TABLE currencies');
        $this->addSql('DROP TABLE `measurement_units`');
        $this->addSql('DROP TABLE part_lots');
        $this->addSql('ALTER TABLE `attachment_types` DROP FOREIGN KEY FK_EFAED719727ACA70');
        $this->addSql('ALTER TABLE `attachment_types` DROP filetype_filter, DROP not_selectable, CHANGE comment comment TEXT DEFAULT NULL COLLATE utf8_unicode_ci, CHANGE name name TINYTEXT NOT NULL COLLATE utf8_unicode_ci, CHANGE last_modified last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL');
        $this->addSql('DROP INDEX idx_efaed719727aca70 ON `attachment_types`');
        $this->addSql('CREATE INDEX attachement_types_parent_id_k ON `attachment_types` (parent_id)');
        $this->addSql('ALTER TABLE `attachment_types` ADD CONSTRAINT FK_EFAED719727ACA70 FOREIGN KEY (parent_id) REFERENCES `attachment_types` (id)');
        $this->addSql('ALTER TABLE `attachments` DROP FOREIGN KEY FK_47C4FAD61F1F2A24');
        $this->addSql('ALTER TABLE `attachments` DROP FOREIGN KEY FK_47C4FAD6C54C8C93');
        $this->addSql('ALTER TABLE `attachments` DROP FOREIGN KEY FK_47C4FAD61F1F2A24');
        $this->addSql('ALTER TABLE `attachments` DROP datetime_added, CHANGE type_id type_id INT NOT NULL, CHANGE show_in_table show_in_table TINYINT(1) DEFAULT \'0\' NOT NULL, CHANGE filename filename MEDIUMTEXT NOT NULL COLLATE utf8_unicode_ci, CHANGE name name TINYTEXT NOT NULL COLLATE utf8_unicode_ci, CHANGE last_modified last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL');
        $this->addSql('CREATE INDEX attachements_class_name_k ON `attachments` (class_name)');
        $this->addSql('DROP INDEX idx_47c4fad61f1f2a24 ON `attachments`');
        $this->addSql('CREATE INDEX attachements_element_id_k ON `attachments` (element_id)');
        $this->addSql('DROP INDEX idx_47c4fad6c54c8c93 ON `attachments`');
        $this->addSql('CREATE INDEX attachements_type_id_fk ON `attachments` (type_id)');
        $this->addSql('ALTER TABLE `attachments` ADD CONSTRAINT FK_47C4FAD6C54C8C93 FOREIGN KEY (type_id) REFERENCES `attachment_types` (id)');
        $this->addSql('ALTER TABLE `attachments` ADD CONSTRAINT FK_47C4FAD61F1F2A24 FOREIGN KEY (element_id) REFERENCES `parts` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE `categories` DROP FOREIGN KEY FK_3AF34668727ACA70');
        $this->addSql('ALTER TABLE `categories` DROP not_selectable, CHANGE disable_footprints disable_footprints TINYINT(1) DEFAULT \'0\' NOT NULL, CHANGE disable_manufacturers disable_manufacturers TINYINT(1) DEFAULT \'0\' NOT NULL, CHANGE disable_autodatasheets disable_autodatasheets TINYINT(1) DEFAULT \'0\' NOT NULL, CHANGE disable_properties disable_properties TINYINT(1) DEFAULT \'0\' NOT NULL, CHANGE comment comment TEXT DEFAULT NULL COLLATE utf8_unicode_ci, CHANGE name name TINYTEXT NOT NULL COLLATE utf8_unicode_ci, CHANGE last_modified last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL');
        $this->addSql('DROP INDEX idx_3af34668727aca70 ON `categories`');
        $this->addSql('CREATE INDEX categories_parent_id_k ON `categories` (parent_id)');
        $this->addSql('ALTER TABLE `categories` ADD CONSTRAINT FK_3AF34668727ACA70 FOREIGN KEY (parent_id) REFERENCES `categories` (id)');
        $this->addSql('ALTER TABLE `device_parts` DROP FOREIGN KEY FK_AFC547992F180363');
        $this->addSql('ALTER TABLE `device_parts` DROP FOREIGN KEY FK_AFC54799C22F6CC4');
        $this->addSql('ALTER TABLE `device_parts` DROP FOREIGN KEY FK_AFC547992F180363');
        $this->addSql('ALTER TABLE `device_parts` DROP FOREIGN KEY FK_AFC54799C22F6CC4');
        $this->addSql('ALTER TABLE `device_parts` CHANGE id_device id_device INT DEFAULT 0 NOT NULL, CHANGE id_part id_part INT DEFAULT 0 NOT NULL, CHANGE quantity quantity INT DEFAULT 0 NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX device_parts_combination_uk ON `device_parts` (id_part, id_device)');
        $this->addSql('DROP INDEX idx_afc547992f180363 ON `device_parts`');
        $this->addSql('CREATE INDEX device_parts_id_device_k ON `device_parts` (id_device)');
        $this->addSql('DROP INDEX idx_afc54799c22f6cc4 ON `device_parts`');
        $this->addSql('CREATE INDEX device_parts_id_part_k ON `device_parts` (id_part)');
        $this->addSql('ALTER TABLE `device_parts` ADD CONSTRAINT FK_AFC547992F180363 FOREIGN KEY (id_device) REFERENCES `devices` (id)');
        $this->addSql('ALTER TABLE `device_parts` ADD CONSTRAINT FK_AFC54799C22F6CC4 FOREIGN KEY (id_part) REFERENCES `parts` (id)');
        $this->addSql('ALTER TABLE `devices` DROP FOREIGN KEY FK_11074E9A727ACA70');
        $this->addSql('ALTER TABLE `devices` DROP not_selectable, CHANGE order_quantity order_quantity INT DEFAULT 0 NOT NULL, CHANGE order_only_missing_parts order_only_missing_parts TINYINT(1) DEFAULT \'0\' NOT NULL, CHANGE comment comment TEXT DEFAULT NULL COLLATE utf8_unicode_ci, CHANGE name name TINYTEXT NOT NULL COLLATE utf8_unicode_ci, CHANGE last_modified last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL');
        $this->addSql('DROP INDEX idx_11074e9a727aca70 ON `devices`');
        $this->addSql('CREATE INDEX devices_parent_id_k ON `devices` (parent_id)');
        $this->addSql('ALTER TABLE `devices` ADD CONSTRAINT FK_11074E9A727ACA70 FOREIGN KEY (parent_id) REFERENCES `devices` (id)');
        $this->addSql('ALTER TABLE `footprints` DROP FOREIGN KEY FK_A34D68A2727ACA70');
        $this->addSql('ALTER TABLE `footprints` DROP not_selectable, CHANGE comment comment TEXT DEFAULT NULL COLLATE utf8_unicode_ci, CHANGE name name TINYTEXT NOT NULL COLLATE utf8_unicode_ci, CHANGE last_modified last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL');
        $this->addSql('DROP INDEX idx_a34d68a2727aca70 ON `footprints`');
        $this->addSql('CREATE INDEX footprints_parent_id_k ON `footprints` (parent_id)');
        $this->addSql('ALTER TABLE `footprints` ADD CONSTRAINT FK_A34D68A2727ACA70 FOREIGN KEY (parent_id) REFERENCES `footprints` (id)');
        $this->addSql('ALTER TABLE `groups` DROP FOREIGN KEY FK_F06D3970727ACA70');
        $this->addSql('DROP INDEX IDX_F06D3970727ACA70 ON `groups`');
        $this->addSql('ALTER TABLE `groups` DROP not_selectable, CHANGE comment comment MEDIUMTEXT DEFAULT NULL COLLATE utf8_general_ci, CHANGE name name VARCHAR(32) NOT NULL COLLATE utf8_general_ci, CHANGE last_modified last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, CHANGE perms_labels perms_labels SMALLINT NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX name ON `groups` (name)');
        $this->addSql('ALTER TABLE `manufacturers` DROP FOREIGN KEY FK_94565B12727ACA70');
        $this->addSql('ALTER TABLE `manufacturers` DROP not_selectable, CHANGE address address MEDIUMTEXT NOT NULL COLLATE utf8_unicode_ci, CHANGE phone_number phone_number TINYTEXT NOT NULL COLLATE utf8_unicode_ci, CHANGE fax_number fax_number TINYTEXT NOT NULL COLLATE utf8_unicode_ci, CHANGE email_address email_address TINYTEXT NOT NULL COLLATE utf8_unicode_ci, CHANGE website website TINYTEXT NOT NULL COLLATE utf8_unicode_ci, CHANGE auto_product_url auto_product_url TINYTEXT NOT NULL COLLATE utf8_unicode_ci, CHANGE comment comment TEXT DEFAULT NULL COLLATE utf8_unicode_ci, CHANGE name name TINYTEXT NOT NULL COLLATE utf8_unicode_ci, CHANGE last_modified last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL');
        $this->addSql('DROP INDEX idx_94565b12727aca70 ON `manufacturers`');
        $this->addSql('CREATE INDEX manufacturers_parent_id_k ON `manufacturers` (parent_id)');
        $this->addSql('ALTER TABLE `manufacturers` ADD CONSTRAINT FK_94565B12727ACA70 FOREIGN KEY (parent_id) REFERENCES `manufacturers` (id)');
        $this->addSql('ALTER TABLE `orderdetails` DROP FOREIGN KEY FK_489AFCDC4CE34BEC');
        $this->addSql('ALTER TABLE `orderdetails` DROP FOREIGN KEY FK_489AFCDCCBF180EB');
        $this->addSql('ALTER TABLE `orderdetails` DROP FOREIGN KEY FK_489AFCDC4CE34BEC');
        $this->addSql('ALTER TABLE `orderdetails` DROP FOREIGN KEY FK_489AFCDCCBF180EB');
        $this->addSql('ALTER TABLE `orderdetails` DROP last_modified, CHANGE id_supplier id_supplier INT DEFAULT 0 NOT NULL, CHANGE supplierpartnr supplierpartnr TINYTEXT NOT NULL COLLATE utf8_unicode_ci, CHANGE obsolete obsolete TINYINT(1) DEFAULT \'0\', CHANGE supplier_product_url supplier_product_url TINYTEXT NOT NULL COLLATE utf8_unicode_ci');
        $this->addSql('DROP INDEX idx_489afcdccbf180eb ON `orderdetails`');
        $this->addSql('CREATE INDEX orderdetails_id_supplier_k ON `orderdetails` (id_supplier)');
        $this->addSql('DROP INDEX idx_489afcdc4ce34bec ON `orderdetails`');
        $this->addSql('CREATE INDEX orderdetails_part_id_k ON `orderdetails` (part_id)');
        $this->addSql('ALTER TABLE `orderdetails` ADD CONSTRAINT FK_489AFCDC4CE34BEC FOREIGN KEY (part_id) REFERENCES `parts` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE `orderdetails` ADD CONSTRAINT FK_489AFCDCCBF180EB FOREIGN KEY (id_supplier) REFERENCES `suppliers` (id)');
        $this->addSql('ALTER TABLE `parts` DROP INDEX UNIQ_6940A7FE81081E9B, ADD INDEX parts_order_orderdetails_id_k (order_orderdetails_id)');
        $this->addSql('ALTER TABLE `parts` DROP FOREIGN KEY FK_6940A7FE5697F554');
        $this->addSql('ALTER TABLE `parts` DROP FOREIGN KEY FK_6940A7FEEBBCC786');
        $this->addSql('DROP INDEX IDX_6940A7FEEBBCC786 ON `parts`');
        $this->addSql('DROP INDEX IDX_6940A7FE2626CEF9 ON `parts`');
        $this->addSql('ALTER TABLE `parts` DROP FOREIGN KEY FK_6940A7FE5697F554');
        $this->addSql('ALTER TABLE `parts` DROP FOREIGN KEY FK_6940A7FE7E371A10');
        $this->addSql('ALTER TABLE `parts` DROP FOREIGN KEY FK_6940A7FE1ECB93AE');
        $this->addSql('ALTER TABLE `parts` ADD instock INT DEFAULT 0 NOT NULL, ADD mininstock INT DEFAULT 0 NOT NULL, DROP minamount, DROP manufacturer_product_number, DROP manufacturing_status, DROP needs_review, DROP tags, DROP mass, CHANGE id_category id_category INT DEFAULT 0 NOT NULL, CHANGE last_modified last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, CHANGE name name MEDIUMTEXT NOT NULL COLLATE utf8_unicode_ci, CHANGE favorite favorite TINYINT(1) DEFAULT \'0\' NOT NULL, CHANGE order_quantity order_quantity INT DEFAULT 1 NOT NULL, CHANGE manual_order manual_order TINYINT(1) DEFAULT \'0\' NOT NULL, CHANGE manufacturer_product_url manufacturer_product_url TINYTEXT NOT NULL COLLATE utf8_unicode_ci, CHANGE id_part_unit id_storelocation INT DEFAULT NULL');
        $this->addSql('ALTER TABLE `parts` ADD CONSTRAINT parts_id_storelocation_fk FOREIGN KEY (id_storelocation) REFERENCES storelocations (id)');
        $this->addSql('CREATE INDEX favorite ON `parts` (favorite)');
        $this->addSql('CREATE INDEX parts_id_storelocation_k ON `parts` (id_storelocation)');
        $this->addSql('DROP INDEX idx_6940a7fe7e371a10 ON `parts`');
        $this->addSql('CREATE INDEX parts_id_footprint_k ON `parts` (id_footprint)');
        $this->addSql('DROP INDEX idx_6940a7fe1ecb93ae ON `parts`');
        $this->addSql('CREATE INDEX parts_id_manufacturer_k ON `parts` (id_manufacturer)');
        $this->addSql('DROP INDEX idx_6940a7fe5697f554 ON `parts`');
        $this->addSql('CREATE INDEX parts_id_category_k ON `parts` (id_category)');
        $this->addSql('ALTER TABLE `parts` ADD CONSTRAINT FK_6940A7FE5697F554 FOREIGN KEY (id_category) REFERENCES `categories` (id)');
        $this->addSql('ALTER TABLE `parts` ADD CONSTRAINT FK_6940A7FE7E371A10 FOREIGN KEY (id_footprint) REFERENCES `footprints` (id)');
        $this->addSql('ALTER TABLE `parts` ADD CONSTRAINT FK_6940A7FE1ECB93AE FOREIGN KEY (id_manufacturer) REFERENCES `manufacturers` (id)');
        $this->addSql('ALTER TABLE `pricedetails` DROP FOREIGN KEY FK_C68C44594A01DDC7');
        $this->addSql('DROP INDEX IDX_C68C4459398D64AA ON `pricedetails`');
        $this->addSql('ALTER TABLE `pricedetails` DROP FOREIGN KEY FK_C68C44594A01DDC7');
        $this->addSql('ALTER TABLE `pricedetails` DROP id_currency, DROP datetime_added, CHANGE price price NUMERIC(11, 5) DEFAULT NULL, CHANGE price_related_quantity price_related_quantity INT DEFAULT 1 NOT NULL, CHANGE min_discount_quantity min_discount_quantity INT DEFAULT 1 NOT NULL, CHANGE manual_input manual_input TINYINT(1) DEFAULT \'1\' NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX pricedetails_combination_uk ON `pricedetails` (orderdetails_id, min_discount_quantity)');
        $this->addSql('DROP INDEX idx_c68c44594a01ddc7 ON `pricedetails`');
        $this->addSql('CREATE INDEX pricedetails_orderdetails_id_k ON `pricedetails` (orderdetails_id)');
        $this->addSql('ALTER TABLE `pricedetails` ADD CONSTRAINT FK_C68C44594A01DDC7 FOREIGN KEY (orderdetails_id) REFERENCES `orderdetails` (id) ON DELETE CASCADE');
        $this->addSql('DROP INDEX IDX_7517020B270BFF1 ON `storelocations`');
        $this->addSql('ALTER TABLE `storelocations` DROP FOREIGN KEY FK_7517020727ACA70');
        $this->addSql('ALTER TABLE `storelocations` DROP storage_type_id, DROP only_single_part, DROP limit_to_existing_parts, DROP not_selectable, CHANGE is_full is_full TINYINT(1) DEFAULT \'0\' NOT NULL, CHANGE comment comment TEXT DEFAULT NULL COLLATE utf8_unicode_ci, CHANGE name name TINYTEXT NOT NULL COLLATE utf8_unicode_ci, CHANGE last_modified last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL');
        $this->addSql('DROP INDEX idx_7517020727aca70 ON `storelocations`');
        $this->addSql('CREATE INDEX storelocations_parent_id_k ON `storelocations` (parent_id)');
        $this->addSql('ALTER TABLE `storelocations` ADD CONSTRAINT FK_7517020727ACA70 FOREIGN KEY (parent_id) REFERENCES `storelocations` (id)');
        $this->addSql('DROP INDEX IDX_AC28B95CECD792C0 ON `suppliers`');
        $this->addSql('ALTER TABLE `suppliers` DROP FOREIGN KEY FK_AC28B95C727ACA70');
        $this->addSql('ALTER TABLE `suppliers` DROP default_currency_id, DROP shipping_costs, DROP not_selectable, CHANGE address address MEDIUMTEXT NOT NULL COLLATE utf8_unicode_ci, CHANGE phone_number phone_number TINYTEXT NOT NULL COLLATE utf8_unicode_ci, CHANGE fax_number fax_number TINYTEXT NOT NULL COLLATE utf8_unicode_ci, CHANGE email_address email_address TINYTEXT NOT NULL COLLATE utf8_unicode_ci, CHANGE website website TINYTEXT NOT NULL COLLATE utf8_unicode_ci, CHANGE auto_product_url auto_product_url TINYTEXT NOT NULL COLLATE utf8_unicode_ci, CHANGE comment comment TEXT DEFAULT NULL COLLATE utf8_unicode_ci, CHANGE name name TINYTEXT NOT NULL COLLATE utf8_unicode_ci, CHANGE last_modified last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL');
        $this->addSql('DROP INDEX idx_ac28b95c727aca70 ON `suppliers`');
        $this->addSql('CREATE INDEX suppliers_parent_id_k ON `suppliers` (parent_id)');
        $this->addSql('ALTER TABLE `suppliers` ADD CONSTRAINT FK_AC28B95C727ACA70 FOREIGN KEY (parent_id) REFERENCES `suppliers` (id)');
        $this->addSql('ALTER TABLE `users` DROP FOREIGN KEY FK_1483A5E9FE54D947');
        $this->addSql('DROP INDEX IDX_1483A5E9FE54D947 ON `users`');
        $this->addSql('ALTER TABLE `users` CHANGE name name VARCHAR(32) NOT NULL COLLATE utf8_general_ci, CHANGE need_pw_change need_pw_change TINYINT(1) DEFAULT \'0\' NOT NULL, CHANGE first_name first_name TINYTEXT DEFAULT NULL COLLATE utf8_general_ci, CHANGE last_name last_name TINYTEXT DEFAULT NULL COLLATE utf8_general_ci, CHANGE department department TINYTEXT DEFAULT NULL COLLATE utf8_general_ci, CHANGE email email TINYTEXT DEFAULT NULL COLLATE utf8_general_ci, CHANGE config_language config_language TINYTEXT DEFAULT NULL COLLATE utf8_general_ci, CHANGE config_timezone config_timezone TINYTEXT DEFAULT NULL COLLATE utf8_general_ci, CHANGE config_theme config_theme TINYTEXT DEFAULT NULL COLLATE utf8_general_ci, CHANGE config_currency config_currency TINYTEXT DEFAULT NULL COLLATE utf8_general_ci, CHANGE last_modified last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, CHANGE perms_labels perms_labels SMALLINT NOT NULL');
        $this->addSql('DROP INDEX uniq_1483a5e95e237e06 ON `users`');
        $this->addSql('CREATE UNIQUE INDEX name ON `users` (name)');
    }

    public function sqLiteUp(Schema $schema): void
    {
        $this->warnIf(true, "Migration not needed for SQLite. Skipping...");
    }

    public function sqLiteDown(Schema $schema): void
    {
        $this->warnIf(true, "Migration not needed for SQLite. Skipping...");
    }
}
