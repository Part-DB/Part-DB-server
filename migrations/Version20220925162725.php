<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Migration\AbstractMultiPlatformMigration;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20220925162725 extends AbstractMultiPlatformMigration
{
    public function getDescription(): string
    {
        return 'Add indices to improve performance';
    }

    public function mySQLUp(Schema $schema): void
    {
        //Change row format to dynamic to allow longer indices (this step is only needed for databases created with an older MySQL/MariaDB version
        $this->addSql('ALTER TABLE attachments ROW_FORMAT=DYNAMIC');
        $this->addSql('ALTER TABLE attachment_types ROW_FORMAT=DYNAMIC');
        $this->addSql('ALTER TABLE categories ROW_FORMAT=DYNAMIC');
        $this->addSql('ALTER TABLE currencies ROW_FORMAT=DYNAMIC');
        $this->addSql('ALTER TABLE devices ROW_FORMAT=DYNAMIC');
        $this->addSql('ALTER TABLE device_parts ROW_FORMAT=DYNAMIC');
        $this->addSql('ALTER TABLE footprints ROW_FORMAT=DYNAMIC');
        $this->addSql('ALTER TABLE `groups` ROW_FORMAT=DYNAMIC');
        $this->addSql('ALTER TABLE label_profiles ROW_FORMAT=DYNAMIC');
        $this->addSql('ALTER TABLE log ROW_FORMAT=DYNAMIC');
        $this->addSql('ALTER TABLE manufacturers ROW_FORMAT=DYNAMIC');
        $this->addSql('ALTER TABLE measurement_units ROW_FORMAT=DYNAMIC');
        $this->addSql('ALTER TABLE migration_versions ROW_FORMAT=DYNAMIC');
        $this->addSql('ALTER TABLE orderdetails ROW_FORMAT=DYNAMIC');
        $this->addSql('ALTER TABLE parameters ROW_FORMAT=DYNAMIC');
        $this->addSql('ALTER TABLE parts ROW_FORMAT=DYNAMIC');
        $this->addSql('ALTER TABLE part_lots ROW_FORMAT=DYNAMIC');
        $this->addSql('ALTER TABLE pricedetails ROW_FORMAT=DYNAMIC');
        $this->addSql('ALTER TABLE storelocations ROW_FORMAT=DYNAMIC');
        $this->addSql('ALTER TABLE suppliers ROW_FORMAT=DYNAMIC');
        $this->addSql('ALTER TABLE u2f_keys ROW_FORMAT=DYNAMIC');
        $this->addSql('ALTER TABLE users ROW_FORMAT=DYNAMIC');

        //Normalize charset encoding for all tables, as old installations might have different encodings
        $this->addSql('ALTER TABLE attachments convert to character set utf8mb4 collate utf8mb4_unicode_ci');
        $this->addSql('ALTER TABLE attachment_types convert to character set utf8mb4 collate utf8mb4_unicode_ci');
        $this->addSql('ALTER TABLE categories convert to character set utf8mb4 collate utf8mb4_unicode_ci');
        $this->addSql('ALTER TABLE currencies convert to character set utf8mb4 collate utf8mb4_unicode_ci');
        $this->addSql('ALTER TABLE devices convert to character set utf8mb4 collate utf8mb4_unicode_ci');
        $this->addSql('ALTER TABLE device_parts convert to character set utf8mb4 collate utf8mb4_unicode_ci');
        $this->addSql('ALTER TABLE footprints convert to character set utf8mb4 collate utf8mb4_unicode_ci');
        $this->addSql('ALTER TABLE `groups` convert to character set utf8mb4 collate utf8mb4_unicode_ci');
        $this->addSql('ALTER TABLE label_profiles convert to character set utf8mb4 collate utf8mb4_unicode_ci');
        $this->addSql('ALTER TABLE log convert to character set utf8mb4 collate utf8mb4_unicode_ci');
        $this->addSql('ALTER TABLE manufacturers convert to character set utf8mb4 collate utf8mb4_unicode_ci');
        $this->addSql('ALTER TABLE measurement_units convert to character set utf8mb4 collate utf8mb4_unicode_ci');
        $this->addSql('ALTER TABLE migration_versions convert to character set utf8mb4 collate utf8mb4_unicode_ci');
        $this->addSql('ALTER TABLE orderdetails convert to character set utf8mb4 collate utf8mb4_unicode_ci');
        $this->addSql('ALTER TABLE parameters convert to character set utf8mb4 collate utf8mb4_unicode_ci');
        $this->addSql('ALTER TABLE parts convert to character set utf8mb4 collate utf8mb4_unicode_ci');
        $this->addSql('ALTER TABLE part_lots convert to character set utf8mb4 collate utf8mb4_unicode_ci');
        $this->addSql('ALTER TABLE pricedetails convert to character set utf8mb4 collate utf8mb4_unicode_ci');
        $this->addSql('ALTER TABLE storelocations convert to character set utf8mb4 collate utf8mb4_unicode_ci');
        $this->addSql('ALTER TABLE suppliers convert to character set utf8mb4 collate utf8mb4_unicode_ci');
        $this->addSql('ALTER TABLE u2f_keys convert to character set utf8mb4 collate utf8mb4_unicode_ci');
        $this->addSql('ALTER TABLE users convert to character set utf8mb4 collate utf8mb4_unicode_ci');

        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE INDEX attachment_types_idx_name ON attachment_types (name)');
        $this->addSql('CREATE INDEX attachment_types_idx_parent_name ON attachment_types (parent_id, name)');
        //We have to disable foreign key checks here, or we get a error on MySQL for legacy database migration. On MariaDB this works fine (with enabled foreign key checks), so I guess this is not so bad...
        $this->addSql('SET foreign_key_checks = 0');
        $this->addSql('ALTER TABLE attachments CHANGE type_id type_id INT NOT NULL');
        $this->addSql('SET foreign_key_checks = 1');
        $this->addSql('CREATE INDEX attachments_idx_id_element_id_class_name ON attachments (id, element_id, class_name)');
        $this->addSql('CREATE INDEX attachments_idx_class_name_id ON attachments (class_name, id)');
        $this->addSql('CREATE INDEX attachment_name_idx ON attachments (name)');
        $this->addSql('CREATE INDEX attachment_element_idx ON attachments (class_name, element_id)');
        $this->addSql('ALTER TABLE categories CHANGE partname_regex partname_regex LONGTEXT NOT NULL, CHANGE partname_hint partname_hint LONGTEXT NOT NULL, CHANGE default_description default_description LONGTEXT NOT NULL, CHANGE default_comment default_comment LONGTEXT NOT NULL');
        $this->addSql('CREATE INDEX category_idx_name ON categories (name)');
        $this->addSql('CREATE INDEX category_idx_parent_name ON categories (parent_id, name)');
        $this->addSql('ALTER TABLE currencies CHANGE exchange_rate exchange_rate NUMERIC(11, 5) DEFAULT NULL COMMENT \'(DC2Type:big_decimal)\'');
        $this->addSql('CREATE INDEX currency_idx_name ON currencies (name)');
        $this->addSql('CREATE INDEX currency_idx_parent_name ON currencies (parent_id, name)');
        $this->addSql('ALTER TABLE device_parts CHANGE mountnames mountnames LONGTEXT NOT NULL');
        $this->addSql('CREATE INDEX footprint_idx_name ON footprints (name)');
        $this->addSql('CREATE INDEX footprint_idx_parent_name ON footprints (parent_id, name)');
        $this->addSql('CREATE INDEX group_idx_name ON `groups` (name)');
        $this->addSql('CREATE INDEX group_idx_parent_name ON `groups` (parent_id, name)');
        $this->addSql('ALTER TABLE log CHANGE level level TINYINT(4) NOT NULL');
        $this->addSql('CREATE INDEX log_idx_type ON log (type)');
        $this->addSql('CREATE INDEX log_idx_type_target ON log (type, target_type, target_id)');
        $this->addSql('CREATE INDEX log_idx_datetime ON log (datetime)');
        $this->addSql('CREATE INDEX manufacturer_name ON manufacturers (name)');
        $this->addSql('CREATE INDEX manufacturer_idx_parent_name ON manufacturers (parent_id, name)');
        $this->addSql('CREATE INDEX unit_idx_name ON measurement_units (name)');
        $this->addSql('CREATE INDEX unit_idx_parent_name ON measurement_units (parent_id, name)');
        $this->addSql('CREATE INDEX orderdetails_supplier_part_nr ON orderdetails (supplierpartnr)');
        $this->addSql('CREATE INDEX parameter_name_idx ON parameters (name)');
        $this->addSql('CREATE INDEX parameter_group_idx ON parameters (param_group)');
        $this->addSql('CREATE INDEX parameter_type_element_idx ON parameters (type, element_id)');
        $this->addSql('CREATE INDEX part_lots_idx_instock_un_expiration_id_part ON part_lots (instock_unknown, expiration_date, id_part)');
        $this->addSql('CREATE INDEX part_lots_idx_needs_refill ON part_lots (needs_refill)');
        $this->addSql('ALTER TABLE parts CHANGE description description LONGTEXT NOT NULL, CHANGE comment comment LONGTEXT NOT NULL');
        $this->addSql('CREATE INDEX parts_idx_datet_name_last_id_needs ON parts (datetime_added, name, last_modified, id, needs_review)');
        $this->addSql('CREATE INDEX parts_idx_name ON parts (name)');
        $this->addSql('ALTER TABLE pricedetails CHANGE price price NUMERIC(11, 5) NOT NULL COMMENT \'(DC2Type:big_decimal)\'');
        $this->addSql('CREATE INDEX pricedetails_idx_min_discount ON pricedetails (min_discount_quantity)');
        $this->addSql('CREATE INDEX pricedetails_idx_min_discount_price_qty ON pricedetails (min_discount_quantity, price_related_quantity)');
        $this->addSql('CREATE INDEX location_idx_name ON storelocations (name)');
        $this->addSql('CREATE INDEX location_idx_parent_name ON storelocations (parent_id, name)');
        $this->addSql('ALTER TABLE suppliers CHANGE shipping_costs shipping_costs NUMERIC(11, 5) DEFAULT NULL COMMENT \'(DC2Type:big_decimal)\'');
        $this->addSql('CREATE INDEX supplier_idx_name ON suppliers (name)');
        $this->addSql('CREATE INDEX supplier_idx_parent_name ON suppliers (parent_id, name)');
        $this->addSql('ALTER TABLE users CHANGE config_instock_comment_w config_instock_comment_w LONGTEXT NOT NULL, CHANGE config_instock_comment_a config_instock_comment_a LONGTEXT NOT NULL');
        $this->addSql('CREATE INDEX user_idx_username ON users (name)');
    }

    public function mySQLDown(Schema $schema): void
    {
        $this->addSql('DROP INDEX attachments_idx_id_element_id_class_name ON `attachments`');
        $this->addSql('DROP INDEX attachments_idx_class_name_id ON `attachments`');
        $this->addSql('DROP INDEX attachment_name_idx ON `attachments`');
        $this->addSql('DROP INDEX attachment_element_idx ON `attachments`');
        $this->addSql('ALTER TABLE `attachments` CHANGE type_id type_id INT DEFAULT NULL');
        $this->addSql('DROP INDEX attachment_types_idx_name ON `attachment_types`');
        $this->addSql('DROP INDEX attachment_types_idx_parent_name ON `attachment_types`');
        $this->addSql('DROP INDEX category_idx_name ON `categories`');
        $this->addSql('DROP INDEX category_idx_parent_name ON `categories`');
        $this->addSql('ALTER TABLE `categories` CHANGE partname_hint partname_hint TEXT NOT NULL, CHANGE partname_regex partname_regex TEXT NOT NULL, CHANGE default_description default_description TEXT NOT NULL, CHANGE default_comment default_comment TEXT NOT NULL');
        $this->addSql('DROP INDEX currency_idx_name ON currencies');
        $this->addSql('DROP INDEX currency_idx_parent_name ON currencies');
        $this->addSql('ALTER TABLE currencies CHANGE exchange_rate exchange_rate NUMERIC(11, 5) DEFAULT NULL');
        $this->addSql('ALTER TABLE `device_parts` CHANGE mountnames mountnames MEDIUMTEXT NOT NULL');
        $this->addSql('DROP INDEX footprint_idx_name ON `footprints`');
        $this->addSql('DROP INDEX footprint_idx_parent_name ON `footprints`');
        $this->addSql('DROP INDEX group_idx_name ON `groups`');
        $this->addSql('DROP INDEX group_idx_parent_name ON `groups`');
        $this->addSql('DROP INDEX log_idx_type ON log');
        $this->addSql('DROP INDEX log_idx_type_target ON log');
        $this->addSql('DROP INDEX log_idx_datetime ON log');
        $this->addSql('ALTER TABLE log CHANGE level level TINYINT(1) NOT NULL');
        $this->addSql('DROP INDEX manufacturer_name ON `manufacturers`');
        $this->addSql('DROP INDEX manufacturer_idx_parent_name ON `manufacturers`');
        $this->addSql('DROP INDEX unit_idx_name ON `measurement_units`');
        $this->addSql('DROP INDEX unit_idx_parent_name ON `measurement_units`');
        $this->addSql('DROP INDEX orderdetails_supplier_part_nr ON `orderdetails`');
        $this->addSql('DROP INDEX parameter_name_idx ON parameters');
        $this->addSql('DROP INDEX parameter_group_idx ON parameters');
        $this->addSql('DROP INDEX parameter_type_element_idx ON parameters');
        $this->addSql('DROP INDEX parts_idx_datet_name_last_id_needs ON `parts`');
        $this->addSql('DROP INDEX parts_idx_name ON `parts`');
        $this->addSql('ALTER TABLE `parts` CHANGE description description MEDIUMTEXT NOT NULL, CHANGE comment comment MEDIUMTEXT NOT NULL');
        $this->addSql('DROP INDEX part_lots_idx_instock_un_expiration_id_part ON part_lots');
        $this->addSql('DROP INDEX part_lots_idx_needs_refill ON part_lots');
        $this->addSql('DROP INDEX pricedetails_idx_min_discount ON `pricedetails`');
        $this->addSql('DROP INDEX pricedetails_idx_min_discount_price_qty ON `pricedetails`');
        $this->addSql('ALTER TABLE `pricedetails` CHANGE price price NUMERIC(11, 5) NOT NULL');
        $this->addSql('DROP INDEX location_idx_name ON `storelocations`');
        $this->addSql('DROP INDEX location_idx_parent_name ON `storelocations`');
        $this->addSql('DROP INDEX supplier_idx_name ON `suppliers`');
        $this->addSql('DROP INDEX supplier_idx_parent_name ON `suppliers`');
        $this->addSql('ALTER TABLE `suppliers` CHANGE shipping_costs shipping_costs NUMERIC(11, 5) DEFAULT NULL');
        $this->addSql('DROP INDEX user_idx_username ON `users`');
        $this->addSql('ALTER TABLE `users` CHANGE config_instock_comment_a config_instock_comment_a TEXT NOT NULL, CHANGE config_instock_comment_w config_instock_comment_w TEXT NOT NULL');
    }

    public function sqLiteUp(Schema $schema): void
    {
        $this->addSql('CREATE TEMPORARY TABLE __temp__attachment_types AS SELECT id, parent_id, id_preview_attachement, filetype_filter, comment, not_selectable, name, last_modified, datetime_added FROM attachment_types');
        $this->addSql('DROP TABLE attachment_types');
        $this->addSql('CREATE TABLE attachment_types (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, parent_id INTEGER DEFAULT NULL, id_preview_attachement INTEGER DEFAULT NULL, filetype_filter CLOB NOT NULL, comment CLOB NOT NULL, not_selectable BOOLEAN NOT NULL, name VARCHAR(255) NOT NULL, last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, CONSTRAINT FK_EFAED719727ACA70 FOREIGN KEY (parent_id) REFERENCES "attachment_types" (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_EFAED7196DEDCEC2 FOREIGN KEY (id_preview_attachement) REFERENCES "attachments" (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO attachment_types (id, parent_id, id_preview_attachement, filetype_filter, comment, not_selectable, name, last_modified, datetime_added) SELECT id, parent_id, id_preview_attachement, filetype_filter, comment, not_selectable, name, last_modified, datetime_added FROM __temp__attachment_types');
        $this->addSql('DROP TABLE __temp__attachment_types');
        $this->addSql('CREATE INDEX IDX_EFAED7196DEDCEC2 ON attachment_types (id_preview_attachement)');
        $this->addSql('CREATE INDEX IDX_EFAED719727ACA70 ON attachment_types (parent_id)');
        $this->addSql('CREATE INDEX attachment_types_idx_name ON attachment_types (name)');
        $this->addSql('CREATE INDEX attachment_types_idx_parent_name ON attachment_types (parent_id, name)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__attachments AS SELECT id, type_id, original_filename, path, show_in_table, name, last_modified, datetime_added, class_name, element_id FROM attachments');
        $this->addSql('DROP TABLE attachments');
        $this->addSql('CREATE TABLE attachments (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, type_id INTEGER NOT NULL, original_filename VARCHAR(255) DEFAULT NULL, path VARCHAR(255) NOT NULL, show_in_table BOOLEAN NOT NULL, name VARCHAR(255) NOT NULL, last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, class_name VARCHAR(255) NOT NULL, element_id INTEGER NOT NULL, CONSTRAINT FK_47C4FAD6C54C8C93 FOREIGN KEY (type_id) REFERENCES "attachment_types" (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO attachments (id, type_id, original_filename, path, show_in_table, name, last_modified, datetime_added, class_name, element_id) SELECT id, type_id, original_filename, path, show_in_table, name, last_modified, datetime_added, class_name, element_id FROM __temp__attachments');
        $this->addSql('DROP TABLE __temp__attachments');
        $this->addSql('CREATE INDEX IDX_47C4FAD61F1F2A24 ON attachments (element_id)');
        $this->addSql('CREATE INDEX IDX_47C4FAD6C54C8C93 ON attachments (type_id)');
        $this->addSql('CREATE INDEX attachments_idx_id_element_id_class_name ON attachments (id, element_id, class_name)');
        $this->addSql('CREATE INDEX attachments_idx_class_name_id ON attachments (class_name, id)');
        $this->addSql('CREATE INDEX attachment_name_idx ON attachments (name)');
        $this->addSql('CREATE INDEX attachment_element_idx ON attachments (class_name, element_id)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__categories AS SELECT id, parent_id, id_preview_attachement, partname_hint, partname_regex, disable_footprints, disable_manufacturers, disable_autodatasheets, disable_properties, default_description, default_comment, comment, not_selectable, name, last_modified, datetime_added FROM categories');
        $this->addSql('DROP TABLE categories');
        $this->addSql('CREATE TABLE categories (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, parent_id INTEGER DEFAULT NULL, id_preview_attachement INTEGER DEFAULT NULL, partname_hint CLOB NOT NULL, partname_regex CLOB NOT NULL, disable_footprints BOOLEAN NOT NULL, disable_manufacturers BOOLEAN NOT NULL, disable_autodatasheets BOOLEAN NOT NULL, disable_properties BOOLEAN NOT NULL, default_description CLOB NOT NULL, default_comment CLOB NOT NULL, comment CLOB NOT NULL, not_selectable BOOLEAN NOT NULL, name VARCHAR(255) NOT NULL, last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, CONSTRAINT FK_3AF34668727ACA70 FOREIGN KEY (parent_id) REFERENCES "categories" (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_3AF346686DEDCEC2 FOREIGN KEY (id_preview_attachement) REFERENCES "attachments" (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO categories (id, parent_id, id_preview_attachement, partname_hint, partname_regex, disable_footprints, disable_manufacturers, disable_autodatasheets, disable_properties, default_description, default_comment, comment, not_selectable, name, last_modified, datetime_added) SELECT id, parent_id, id_preview_attachement, partname_hint, partname_regex, disable_footprints, disable_manufacturers, disable_autodatasheets, disable_properties, default_description, default_comment, comment, not_selectable, name, last_modified, datetime_added FROM __temp__categories');
        $this->addSql('DROP TABLE __temp__categories');
        $this->addSql('CREATE INDEX IDX_3AF346686DEDCEC2 ON categories (id_preview_attachement)');
        $this->addSql('CREATE INDEX IDX_3AF34668727ACA70 ON categories (parent_id)');
        $this->addSql('CREATE INDEX category_idx_name ON categories (name)');
        $this->addSql('CREATE INDEX category_idx_parent_name ON categories (parent_id, name)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__currencies AS SELECT id, parent_id, id_preview_attachement, exchange_rate, iso_code, comment, not_selectable, name, last_modified, datetime_added FROM currencies');
        $this->addSql('DROP TABLE currencies');
        $this->addSql('CREATE TABLE currencies (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, parent_id INTEGER DEFAULT NULL, id_preview_attachement INTEGER DEFAULT NULL, exchange_rate NUMERIC(11, 5) DEFAULT NULL --(DC2Type:big_decimal)
        , iso_code VARCHAR(255) NOT NULL, comment CLOB NOT NULL, not_selectable BOOLEAN NOT NULL, name VARCHAR(255) NOT NULL, last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, CONSTRAINT FK_37C44693727ACA70 FOREIGN KEY (parent_id) REFERENCES currencies (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_37C446936DEDCEC2 FOREIGN KEY (id_preview_attachement) REFERENCES "attachments" (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO currencies (id, parent_id, id_preview_attachement, exchange_rate, iso_code, comment, not_selectable, name, last_modified, datetime_added) SELECT id, parent_id, id_preview_attachement, exchange_rate, iso_code, comment, not_selectable, name, last_modified, datetime_added FROM __temp__currencies');
        $this->addSql('DROP TABLE __temp__currencies');
        $this->addSql('CREATE INDEX IDX_37C446936DEDCEC2 ON currencies (id_preview_attachement)');
        $this->addSql('CREATE INDEX IDX_37C44693727ACA70 ON currencies (parent_id)');
        $this->addSql('CREATE INDEX currency_idx_name ON currencies (name)');
        $this->addSql('CREATE INDEX currency_idx_parent_name ON currencies (parent_id, name)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__device_parts AS SELECT id, id_device, id_part, quantity, mountnames FROM device_parts');
        $this->addSql('DROP TABLE device_parts');
        $this->addSql('CREATE TABLE device_parts (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, id_device INTEGER DEFAULT NULL, id_part INTEGER DEFAULT NULL, quantity INTEGER NOT NULL, mountnames CLOB NOT NULL, CONSTRAINT FK_AFC547992F180363 FOREIGN KEY (id_device) REFERENCES "devices" (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_AFC54799C22F6CC4 FOREIGN KEY (id_part) REFERENCES "parts" (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO device_parts (id, id_device, id_part, quantity, mountnames) SELECT id, id_device, id_part, quantity, mountnames FROM __temp__device_parts');
        $this->addSql('DROP TABLE __temp__device_parts');
        $this->addSql('CREATE INDEX IDX_AFC54799C22F6CC4 ON device_parts (id_part)');
        $this->addSql('CREATE INDEX IDX_AFC547992F180363 ON device_parts (id_device)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__devices AS SELECT id, parent_id, id_preview_attachement, order_quantity, order_only_missing_parts, comment, not_selectable, name, last_modified, datetime_added FROM devices');
        $this->addSql('DROP TABLE devices');
        $this->addSql('CREATE TABLE devices (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, parent_id INTEGER DEFAULT NULL, id_preview_attachement INTEGER DEFAULT NULL, order_quantity INTEGER NOT NULL, order_only_missing_parts BOOLEAN NOT NULL, comment CLOB NOT NULL, not_selectable BOOLEAN NOT NULL, name VARCHAR(255) NOT NULL, last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, CONSTRAINT FK_11074E9A727ACA70 FOREIGN KEY (parent_id) REFERENCES "devices" (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_11074E9A6DEDCEC2 FOREIGN KEY (id_preview_attachement) REFERENCES "attachments" (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO devices (id, parent_id, id_preview_attachement, order_quantity, order_only_missing_parts, comment, not_selectable, name, last_modified, datetime_added) SELECT id, parent_id, id_preview_attachement, order_quantity, order_only_missing_parts, comment, not_selectable, name, last_modified, datetime_added FROM __temp__devices');
        $this->addSql('DROP TABLE __temp__devices');
        $this->addSql('CREATE INDEX IDX_11074E9A6DEDCEC2 ON devices (id_preview_attachement)');
        $this->addSql('CREATE INDEX IDX_11074E9A727ACA70 ON devices (parent_id)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__footprints AS SELECT id, parent_id, id_footprint_3d, id_preview_attachement, comment, not_selectable, name, last_modified, datetime_added FROM footprints');
        $this->addSql('DROP TABLE footprints');
        $this->addSql('CREATE TABLE footprints (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, parent_id INTEGER DEFAULT NULL, id_footprint_3d INTEGER DEFAULT NULL, id_preview_attachement INTEGER DEFAULT NULL, comment CLOB NOT NULL, not_selectable BOOLEAN NOT NULL, name VARCHAR(255) NOT NULL, last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, CONSTRAINT FK_A34D68A2727ACA70 FOREIGN KEY (parent_id) REFERENCES "footprints" (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_A34D68A232A38C34 FOREIGN KEY (id_footprint_3d) REFERENCES "attachments" (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_A34D68A26DEDCEC2 FOREIGN KEY (id_preview_attachement) REFERENCES "attachments" (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO footprints (id, parent_id, id_footprint_3d, id_preview_attachement, comment, not_selectable, name, last_modified, datetime_added) SELECT id, parent_id, id_footprint_3d, id_preview_attachement, comment, not_selectable, name, last_modified, datetime_added FROM __temp__footprints');
        $this->addSql('DROP TABLE __temp__footprints');
        $this->addSql('CREATE INDEX IDX_A34D68A26DEDCEC2 ON footprints (id_preview_attachement)');
        $this->addSql('CREATE INDEX IDX_A34D68A232A38C34 ON footprints (id_footprint_3d)');
        $this->addSql('CREATE INDEX IDX_A34D68A2727ACA70 ON footprints (parent_id)');
        $this->addSql('CREATE INDEX footprint_idx_name ON footprints (name)');
        $this->addSql('CREATE INDEX footprint_idx_parent_name ON footprints (parent_id, name)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__groups AS SELECT id, parent_id, id_preview_attachement, enforce_2fa, comment, not_selectable, name, last_modified, datetime_added, perms_system, perms_groups, perms_users, perms_self, perms_system_config, perms_system_database, perms_parts, perms_parts_name, perms_parts_category, perms_parts_description, perms_parts_minamount, perms_parts_footprint, perms_parts_lots, perms_parts_tags, perms_parts_unit, perms_parts_mass, perms_parts_manufacturer, perms_parts_status, perms_parts_mpn, perms_parts_comment, perms_parts_order, perms_parts_orderdetails, perms_parts_prices, perms_parts_parameters, perms_parts_attachements, perms_devices, perms_devices_parts, perms_storelocations, perms_footprints, perms_categories, perms_suppliers, perms_manufacturers, perms_attachement_types, perms_currencies, perms_measurement_units, perms_tools, perms_labels FROM groups');
        $this->addSql('DROP TABLE groups');
        $this->addSql('CREATE TABLE groups (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, parent_id INTEGER DEFAULT NULL, id_preview_attachement INTEGER DEFAULT NULL, enforce_2fa BOOLEAN NOT NULL, comment CLOB NOT NULL, not_selectable BOOLEAN NOT NULL, name VARCHAR(255) NOT NULL, last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, perms_system INTEGER NOT NULL, perms_groups INTEGER NOT NULL, perms_users INTEGER NOT NULL, perms_self INTEGER NOT NULL, perms_system_config INTEGER NOT NULL, perms_system_database INTEGER NOT NULL, perms_parts BIGINT NOT NULL, perms_parts_name SMALLINT NOT NULL, perms_parts_category SMALLINT NOT NULL, perms_parts_description SMALLINT NOT NULL, perms_parts_minamount SMALLINT NOT NULL, perms_parts_footprint SMALLINT NOT NULL, perms_parts_lots SMALLINT NOT NULL, perms_parts_tags SMALLINT NOT NULL, perms_parts_unit SMALLINT NOT NULL, perms_parts_mass SMALLINT NOT NULL, perms_parts_manufacturer SMALLINT NOT NULL, perms_parts_status SMALLINT NOT NULL, perms_parts_mpn SMALLINT NOT NULL, perms_parts_comment SMALLINT NOT NULL, perms_parts_order SMALLINT NOT NULL, perms_parts_orderdetails SMALLINT NOT NULL, perms_parts_prices SMALLINT NOT NULL, perms_parts_parameters SMALLINT NOT NULL, perms_parts_attachements SMALLINT NOT NULL, perms_devices INTEGER NOT NULL, perms_devices_parts INTEGER NOT NULL, perms_storelocations INTEGER NOT NULL, perms_footprints INTEGER NOT NULL, perms_categories INTEGER NOT NULL, perms_suppliers INTEGER NOT NULL, perms_manufacturers INTEGER NOT NULL, perms_attachement_types INTEGER NOT NULL, perms_currencies INTEGER NOT NULL, perms_measurement_units INTEGER NOT NULL, perms_tools INTEGER NOT NULL, perms_labels INTEGER NOT NULL, CONSTRAINT FK_F06D3970727ACA70 FOREIGN KEY (parent_id) REFERENCES "groups" (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_F06D39706DEDCEC2 FOREIGN KEY (id_preview_attachement) REFERENCES "attachments" (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO groups (id, parent_id, id_preview_attachement, enforce_2fa, comment, not_selectable, name, last_modified, datetime_added, perms_system, perms_groups, perms_users, perms_self, perms_system_config, perms_system_database, perms_parts, perms_parts_name, perms_parts_category, perms_parts_description, perms_parts_minamount, perms_parts_footprint, perms_parts_lots, perms_parts_tags, perms_parts_unit, perms_parts_mass, perms_parts_manufacturer, perms_parts_status, perms_parts_mpn, perms_parts_comment, perms_parts_order, perms_parts_orderdetails, perms_parts_prices, perms_parts_parameters, perms_parts_attachements, perms_devices, perms_devices_parts, perms_storelocations, perms_footprints, perms_categories, perms_suppliers, perms_manufacturers, perms_attachement_types, perms_currencies, perms_measurement_units, perms_tools, perms_labels) SELECT id, parent_id, id_preview_attachement, enforce_2fa, comment, not_selectable, name, last_modified, datetime_added, perms_system, perms_groups, perms_users, perms_self, perms_system_config, perms_system_database, perms_parts, perms_parts_name, perms_parts_category, perms_parts_description, perms_parts_minamount, perms_parts_footprint, perms_parts_lots, perms_parts_tags, perms_parts_unit, perms_parts_mass, perms_parts_manufacturer, perms_parts_status, perms_parts_mpn, perms_parts_comment, perms_parts_order, perms_parts_orderdetails, perms_parts_prices, perms_parts_parameters, perms_parts_attachements, perms_devices, perms_devices_parts, perms_storelocations, perms_footprints, perms_categories, perms_suppliers, perms_manufacturers, perms_attachement_types, perms_currencies, perms_measurement_units, perms_tools, perms_labels FROM __temp__groups');
        $this->addSql('DROP TABLE __temp__groups');
        $this->addSql('CREATE INDEX IDX_F06D39706DEDCEC2 ON groups (id_preview_attachement)');
        $this->addSql('CREATE INDEX IDX_F06D3970727ACA70 ON groups (parent_id)');
        $this->addSql('CREATE INDEX group_idx_name ON groups (name)');
        $this->addSql('CREATE INDEX group_idx_parent_name ON groups (parent_id, name)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__label_profiles AS SELECT id, id_preview_attachement, comment, show_in_dropdown, name, last_modified, datetime_added, options_width, options_height, options_barcode_type, options_picture_type, options_supported_element, options_additional_css, options_lines_mode, options_lines FROM label_profiles');
        $this->addSql('DROP TABLE label_profiles');
        $this->addSql('CREATE TABLE label_profiles (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, id_preview_attachement INTEGER DEFAULT NULL, comment CLOB NOT NULL, show_in_dropdown BOOLEAN NOT NULL, name VARCHAR(255) NOT NULL, last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, options_width DOUBLE PRECISION NOT NULL, options_height DOUBLE PRECISION NOT NULL, options_barcode_type VARCHAR(255) NOT NULL, options_picture_type VARCHAR(255) NOT NULL, options_supported_element VARCHAR(255) NOT NULL, options_additional_css CLOB NOT NULL, options_lines_mode VARCHAR(255) NOT NULL, options_lines CLOB NOT NULL, CONSTRAINT FK_C93E9CF56DEDCEC2 FOREIGN KEY (id_preview_attachement) REFERENCES "attachments" (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO label_profiles (id, id_preview_attachement, comment, show_in_dropdown, name, last_modified, datetime_added, options_width, options_height, options_barcode_type, options_picture_type, options_supported_element, options_additional_css, options_lines_mode, options_lines) SELECT id, id_preview_attachement, comment, show_in_dropdown, name, last_modified, datetime_added, options_width, options_height, options_barcode_type, options_picture_type, options_supported_element, options_additional_css, options_lines_mode, options_lines FROM __temp__label_profiles');
        $this->addSql('DROP TABLE __temp__label_profiles');
        $this->addSql('CREATE INDEX IDX_C93E9CF56DEDCEC2 ON label_profiles (id_preview_attachement)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__log AS SELECT id, id_user, datetime, level, target_id, target_type, extra, type FROM log');
        $this->addSql('DROP TABLE log');
        $this->addSql('CREATE TABLE log (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, id_user INTEGER NOT NULL, datetime DATETIME NOT NULL, level TINYINT(4) NOT NULL, target_id INTEGER NOT NULL, target_type SMALLINT NOT NULL, extra CLOB NOT NULL --(DC2Type:json)
        , type SMALLINT NOT NULL, CONSTRAINT FK_8F3F68C56B3CA4B FOREIGN KEY (id_user) REFERENCES "users" (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO log (id, id_user, datetime, level, target_id, target_type, extra, type) SELECT id, id_user, datetime, level, target_id, target_type, extra, type FROM __temp__log');
        $this->addSql('DROP TABLE __temp__log');
        $this->addSql('CREATE INDEX IDX_8F3F68C56B3CA4B ON log (id_user)');
        $this->addSql('CREATE INDEX log_idx_type ON log (type)');
        $this->addSql('CREATE INDEX log_idx_type_target ON log (type, target_type, target_id)');
        $this->addSql('CREATE INDEX log_idx_datetime ON log (datetime)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__manufacturers AS SELECT id, parent_id, id_preview_attachement, address, phone_number, fax_number, email_address, website, auto_product_url, comment, not_selectable, name, last_modified, datetime_added FROM manufacturers');
        $this->addSql('DROP TABLE manufacturers');
        $this->addSql('CREATE TABLE manufacturers (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, parent_id INTEGER DEFAULT NULL, id_preview_attachement INTEGER DEFAULT NULL, address VARCHAR(255) NOT NULL, phone_number VARCHAR(255) NOT NULL, fax_number VARCHAR(255) NOT NULL, email_address VARCHAR(255) NOT NULL, website VARCHAR(255) NOT NULL, auto_product_url VARCHAR(255) NOT NULL, comment CLOB NOT NULL, not_selectable BOOLEAN NOT NULL, name VARCHAR(255) NOT NULL, last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, CONSTRAINT FK_94565B12727ACA70 FOREIGN KEY (parent_id) REFERENCES "manufacturers" (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_94565B126DEDCEC2 FOREIGN KEY (id_preview_attachement) REFERENCES "attachments" (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO manufacturers (id, parent_id, id_preview_attachement, address, phone_number, fax_number, email_address, website, auto_product_url, comment, not_selectable, name, last_modified, datetime_added) SELECT id, parent_id, id_preview_attachement, address, phone_number, fax_number, email_address, website, auto_product_url, comment, not_selectable, name, last_modified, datetime_added FROM __temp__manufacturers');
        $this->addSql('DROP TABLE __temp__manufacturers');
        $this->addSql('CREATE INDEX IDX_94565B126DEDCEC2 ON manufacturers (id_preview_attachement)');
        $this->addSql('CREATE INDEX IDX_94565B12727ACA70 ON manufacturers (parent_id)');
        $this->addSql('CREATE INDEX manufacturer_name ON manufacturers (name)');
        $this->addSql('CREATE INDEX manufacturer_idx_parent_name ON manufacturers (parent_id, name)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__measurement_units AS SELECT id, parent_id, id_preview_attachement, unit, is_integer, use_si_prefix, comment, not_selectable, name, last_modified, datetime_added FROM measurement_units');
        $this->addSql('DROP TABLE measurement_units');
        $this->addSql('CREATE TABLE measurement_units (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, parent_id INTEGER DEFAULT NULL, id_preview_attachement INTEGER DEFAULT NULL, unit VARCHAR(255) DEFAULT NULL, is_integer BOOLEAN NOT NULL, use_si_prefix BOOLEAN NOT NULL, comment CLOB NOT NULL, not_selectable BOOLEAN NOT NULL, name VARCHAR(255) NOT NULL, last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, CONSTRAINT FK_F5AF83CF727ACA70 FOREIGN KEY (parent_id) REFERENCES "measurement_units" (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_F5AF83CF6DEDCEC2 FOREIGN KEY (id_preview_attachement) REFERENCES "attachments" (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO measurement_units (id, parent_id, id_preview_attachement, unit, is_integer, use_si_prefix, comment, not_selectable, name, last_modified, datetime_added) SELECT id, parent_id, id_preview_attachement, unit, is_integer, use_si_prefix, comment, not_selectable, name, last_modified, datetime_added FROM __temp__measurement_units');
        $this->addSql('DROP TABLE __temp__measurement_units');
        $this->addSql('CREATE INDEX IDX_F5AF83CF6DEDCEC2 ON measurement_units (id_preview_attachement)');
        $this->addSql('CREATE INDEX IDX_F5AF83CF727ACA70 ON measurement_units (parent_id)');
        $this->addSql('CREATE INDEX unit_idx_name ON measurement_units (name)');
        $this->addSql('CREATE INDEX unit_idx_parent_name ON measurement_units (parent_id, name)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__orderdetails AS SELECT id, part_id, id_supplier, supplierpartnr, obsolete, supplier_product_url, last_modified, datetime_added FROM orderdetails');
        $this->addSql('DROP TABLE orderdetails');
        $this->addSql('CREATE TABLE orderdetails (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, part_id INTEGER NOT NULL, id_supplier INTEGER DEFAULT NULL, supplierpartnr VARCHAR(255) NOT NULL, obsolete BOOLEAN NOT NULL, supplier_product_url VARCHAR(255) NOT NULL, last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, CONSTRAINT FK_489AFCDC4CE34BEC FOREIGN KEY (part_id) REFERENCES "parts" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_489AFCDCCBF180EB FOREIGN KEY (id_supplier) REFERENCES "suppliers" (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO orderdetails (id, part_id, id_supplier, supplierpartnr, obsolete, supplier_product_url, last_modified, datetime_added) SELECT id, part_id, id_supplier, supplierpartnr, obsolete, supplier_product_url, last_modified, datetime_added FROM __temp__orderdetails');
        $this->addSql('DROP TABLE __temp__orderdetails');
        $this->addSql('CREATE INDEX IDX_489AFCDCCBF180EB ON orderdetails (id_supplier)');
        $this->addSql('CREATE INDEX IDX_489AFCDC4CE34BEC ON orderdetails (part_id)');
        $this->addSql('CREATE INDEX orderdetails_supplier_part_nr ON orderdetails (supplierpartnr)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__parameters AS SELECT id, symbol, value_min, value_typical, value_max, unit, value_text, param_group, name, last_modified, datetime_added, type, element_id FROM parameters');
        $this->addSql('DROP TABLE parameters');
        $this->addSql('CREATE TABLE parameters (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, symbol VARCHAR(255) NOT NULL, value_min DOUBLE PRECISION DEFAULT NULL, value_typical DOUBLE PRECISION DEFAULT NULL, value_max DOUBLE PRECISION DEFAULT NULL, unit VARCHAR(255) NOT NULL, value_text VARCHAR(255) NOT NULL, param_group VARCHAR(255) NOT NULL, name VARCHAR(255) NOT NULL, last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, type SMALLINT NOT NULL, element_id INTEGER NOT NULL)');
        $this->addSql('INSERT INTO parameters (id, symbol, value_min, value_typical, value_max, unit, value_text, param_group, name, last_modified, datetime_added, type, element_id) SELECT id, symbol, value_min, value_typical, value_max, unit, value_text, param_group, name, last_modified, datetime_added, type, element_id FROM __temp__parameters');
        $this->addSql('DROP TABLE __temp__parameters');
        $this->addSql('CREATE INDEX IDX_69348FE1F1F2A24 ON parameters (element_id)');
        $this->addSql('CREATE INDEX parameter_name_idx ON parameters (name)');
        $this->addSql('CREATE INDEX parameter_group_idx ON parameters (param_group)');
        $this->addSql('CREATE INDEX parameter_type_element_idx ON parameters (type, element_id)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__part_lots AS SELECT id, id_store_location, id_part, description, comment, expiration_date, instock_unknown, amount, needs_refill, last_modified, datetime_added FROM part_lots');
        $this->addSql('DROP TABLE part_lots');
        $this->addSql('CREATE TABLE part_lots (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, id_store_location INTEGER DEFAULT NULL, id_part INTEGER NOT NULL, description CLOB NOT NULL, comment CLOB NOT NULL, expiration_date DATETIME DEFAULT NULL, instock_unknown BOOLEAN NOT NULL, amount DOUBLE PRECISION NOT NULL, needs_refill BOOLEAN NOT NULL, last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, CONSTRAINT FK_EBC8F9435D8F4B37 FOREIGN KEY (id_store_location) REFERENCES "storelocations" (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_EBC8F943C22F6CC4 FOREIGN KEY (id_part) REFERENCES "parts" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO part_lots (id, id_store_location, id_part, description, comment, expiration_date, instock_unknown, amount, needs_refill, last_modified, datetime_added) SELECT id, id_store_location, id_part, description, comment, expiration_date, instock_unknown, amount, needs_refill, last_modified, datetime_added FROM __temp__part_lots');
        $this->addSql('DROP TABLE __temp__part_lots');
        $this->addSql('CREATE INDEX IDX_EBC8F943C22F6CC4 ON part_lots (id_part)');
        $this->addSql('CREATE INDEX IDX_EBC8F9435D8F4B37 ON part_lots (id_store_location)');
        $this->addSql('CREATE INDEX part_lots_idx_instock_un_expiration_id_part ON part_lots (instock_unknown, expiration_date, id_part)');
        $this->addSql('CREATE INDEX part_lots_idx_needs_refill ON part_lots (needs_refill)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__parts AS SELECT id, id_preview_attachement, id_category, id_footprint, id_part_unit, id_manufacturer, order_orderdetails_id, datetime_added, name, last_modified, needs_review, tags, mass, description, comment, visible, favorite, minamount, manufacturer_product_url, manufacturer_product_number, manufacturing_status, order_quantity, manual_order FROM parts');
        $this->addSql('DROP TABLE parts');
        $this->addSql('CREATE TABLE parts (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, id_preview_attachement INTEGER DEFAULT NULL, id_category INTEGER NOT NULL, id_footprint INTEGER DEFAULT NULL, id_part_unit INTEGER DEFAULT NULL, id_manufacturer INTEGER DEFAULT NULL, order_orderdetails_id INTEGER DEFAULT NULL, datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, name VARCHAR(255) NOT NULL, last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, needs_review BOOLEAN NOT NULL, tags CLOB NOT NULL, mass DOUBLE PRECISION DEFAULT NULL, description CLOB NOT NULL, comment CLOB NOT NULL, visible BOOLEAN NOT NULL, favorite BOOLEAN NOT NULL, minamount DOUBLE PRECISION NOT NULL, manufacturer_product_url VARCHAR(255) NOT NULL, manufacturer_product_number VARCHAR(255) NOT NULL, manufacturing_status VARCHAR(255) DEFAULT NULL, order_quantity INTEGER NOT NULL, manual_order BOOLEAN NOT NULL, CONSTRAINT FK_6940A7FE6DEDCEC2 FOREIGN KEY (id_preview_attachement) REFERENCES "attachments" (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_6940A7FE5697F554 FOREIGN KEY (id_category) REFERENCES "categories" (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_6940A7FE7E371A10 FOREIGN KEY (id_footprint) REFERENCES "footprints" (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_6940A7FE2626CEF9 FOREIGN KEY (id_part_unit) REFERENCES "measurement_units" (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_6940A7FE1ECB93AE FOREIGN KEY (id_manufacturer) REFERENCES "manufacturers" (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_6940A7FE81081E9B FOREIGN KEY (order_orderdetails_id) REFERENCES "orderdetails" (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO parts (id, id_preview_attachement, id_category, id_footprint, id_part_unit, id_manufacturer, order_orderdetails_id, datetime_added, name, last_modified, needs_review, tags, mass, description, comment, visible, favorite, minamount, manufacturer_product_url, manufacturer_product_number, manufacturing_status, order_quantity, manual_order) SELECT id, id_preview_attachement, id_category, id_footprint, id_part_unit, id_manufacturer, order_orderdetails_id, datetime_added, name, last_modified, needs_review, tags, mass, description, comment, visible, favorite, minamount, manufacturer_product_url, manufacturer_product_number, manufacturing_status, order_quantity, manual_order FROM __temp__parts');
        $this->addSql('DROP TABLE __temp__parts');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_6940A7FE81081E9B ON parts (order_orderdetails_id)');
        $this->addSql('CREATE INDEX IDX_6940A7FE1ECB93AE ON parts (id_manufacturer)');
        $this->addSql('CREATE INDEX IDX_6940A7FE2626CEF9 ON parts (id_part_unit)');
        $this->addSql('CREATE INDEX IDX_6940A7FE7E371A10 ON parts (id_footprint)');
        $this->addSql('CREATE INDEX IDX_6940A7FE5697F554 ON parts (id_category)');
        $this->addSql('CREATE INDEX IDX_6940A7FE6DEDCEC2 ON parts (id_preview_attachement)');
        $this->addSql('CREATE INDEX parts_idx_datet_name_last_id_needs ON parts (datetime_added, name, last_modified, id, needs_review)');
        $this->addSql('CREATE INDEX parts_idx_name ON parts (name)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__pricedetails AS SELECT id, id_currency, orderdetails_id, price, price_related_quantity, min_discount_quantity, manual_input, last_modified, datetime_added FROM pricedetails');
        $this->addSql('DROP TABLE pricedetails');
        $this->addSql('CREATE TABLE pricedetails (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, id_currency INTEGER DEFAULT NULL, orderdetails_id INTEGER NOT NULL, price NUMERIC(11, 5) NOT NULL --(DC2Type:big_decimal)
        , price_related_quantity DOUBLE PRECISION NOT NULL, min_discount_quantity DOUBLE PRECISION NOT NULL, manual_input BOOLEAN NOT NULL, last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, CONSTRAINT FK_C68C4459398D64AA FOREIGN KEY (id_currency) REFERENCES currencies (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_C68C44594A01DDC7 FOREIGN KEY (orderdetails_id) REFERENCES "orderdetails" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO pricedetails (id, id_currency, orderdetails_id, price, price_related_quantity, min_discount_quantity, manual_input, last_modified, datetime_added) SELECT id, id_currency, orderdetails_id, price, price_related_quantity, min_discount_quantity, manual_input, last_modified, datetime_added FROM __temp__pricedetails');
        $this->addSql('DROP TABLE __temp__pricedetails');
        $this->addSql('CREATE INDEX IDX_C68C44594A01DDC7 ON pricedetails (orderdetails_id)');
        $this->addSql('CREATE INDEX IDX_C68C4459398D64AA ON pricedetails (id_currency)');
        $this->addSql('CREATE INDEX pricedetails_idx_min_discount ON pricedetails (min_discount_quantity)');
        $this->addSql('CREATE INDEX pricedetails_idx_min_discount_price_qty ON pricedetails (min_discount_quantity, price_related_quantity)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__storelocations AS SELECT id, parent_id, storage_type_id, id_preview_attachement, is_full, only_single_part, limit_to_existing_parts, comment, not_selectable, name, last_modified, datetime_added FROM storelocations');
        $this->addSql('DROP TABLE storelocations');
        $this->addSql('CREATE TABLE storelocations (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, parent_id INTEGER DEFAULT NULL, storage_type_id INTEGER DEFAULT NULL, id_preview_attachement INTEGER DEFAULT NULL, is_full BOOLEAN NOT NULL, only_single_part BOOLEAN NOT NULL, limit_to_existing_parts BOOLEAN NOT NULL, comment CLOB NOT NULL, not_selectable BOOLEAN NOT NULL, name VARCHAR(255) NOT NULL, last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, CONSTRAINT FK_7517020727ACA70 FOREIGN KEY (parent_id) REFERENCES "storelocations" (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_7517020B270BFF1 FOREIGN KEY (storage_type_id) REFERENCES "measurement_units" (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_75170206DEDCEC2 FOREIGN KEY (id_preview_attachement) REFERENCES "attachments" (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO storelocations (id, parent_id, storage_type_id, id_preview_attachement, is_full, only_single_part, limit_to_existing_parts, comment, not_selectable, name, last_modified, datetime_added) SELECT id, parent_id, storage_type_id, id_preview_attachement, is_full, only_single_part, limit_to_existing_parts, comment, not_selectable, name, last_modified, datetime_added FROM __temp__storelocations');
        $this->addSql('DROP TABLE __temp__storelocations');
        $this->addSql('CREATE INDEX IDX_75170206DEDCEC2 ON storelocations (id_preview_attachement)');
        $this->addSql('CREATE INDEX IDX_7517020B270BFF1 ON storelocations (storage_type_id)');
        $this->addSql('CREATE INDEX IDX_7517020727ACA70 ON storelocations (parent_id)');
        $this->addSql('CREATE INDEX location_idx_name ON storelocations (name)');
        $this->addSql('CREATE INDEX location_idx_parent_name ON storelocations (parent_id, name)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__suppliers AS SELECT id, parent_id, default_currency_id, id_preview_attachement, shipping_costs, address, phone_number, fax_number, email_address, website, auto_product_url, comment, not_selectable, name, last_modified, datetime_added FROM suppliers');
        $this->addSql('DROP TABLE suppliers');
        $this->addSql('CREATE TABLE suppliers (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, parent_id INTEGER DEFAULT NULL, default_currency_id INTEGER DEFAULT NULL, id_preview_attachement INTEGER DEFAULT NULL, shipping_costs NUMERIC(11, 5) DEFAULT NULL --(DC2Type:big_decimal)
        , address VARCHAR(255) NOT NULL, phone_number VARCHAR(255) NOT NULL, fax_number VARCHAR(255) NOT NULL, email_address VARCHAR(255) NOT NULL, website VARCHAR(255) NOT NULL, auto_product_url VARCHAR(255) NOT NULL, comment CLOB NOT NULL, not_selectable BOOLEAN NOT NULL, name VARCHAR(255) NOT NULL, last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, CONSTRAINT FK_AC28B95C727ACA70 FOREIGN KEY (parent_id) REFERENCES "suppliers" (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_AC28B95CECD792C0 FOREIGN KEY (default_currency_id) REFERENCES currencies (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_AC28B95C6DEDCEC2 FOREIGN KEY (id_preview_attachement) REFERENCES "attachments" (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO suppliers (id, parent_id, default_currency_id, id_preview_attachement, shipping_costs, address, phone_number, fax_number, email_address, website, auto_product_url, comment, not_selectable, name, last_modified, datetime_added) SELECT id, parent_id, default_currency_id, id_preview_attachement, shipping_costs, address, phone_number, fax_number, email_address, website, auto_product_url, comment, not_selectable, name, last_modified, datetime_added FROM __temp__suppliers');
        $this->addSql('DROP TABLE __temp__suppliers');
        $this->addSql('CREATE INDEX IDX_AC28B95C6DEDCEC2 ON suppliers (id_preview_attachement)');
        $this->addSql('CREATE INDEX IDX_AC28B95CECD792C0 ON suppliers (default_currency_id)');
        $this->addSql('CREATE INDEX IDX_AC28B95C727ACA70 ON suppliers (parent_id)');
        $this->addSql('CREATE INDEX supplier_idx_name ON suppliers (name)');
        $this->addSql('CREATE INDEX supplier_idx_parent_name ON suppliers (parent_id, name)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__u2f_keys AS SELECT id, user_id, key_handle, public_key, certificate, counter, name, last_modified, datetime_added FROM u2f_keys');
        $this->addSql('DROP TABLE u2f_keys');
        $this->addSql('CREATE TABLE u2f_keys (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, user_id INTEGER DEFAULT NULL, key_handle VARCHAR(128) NOT NULL, public_key VARCHAR(255) NOT NULL, certificate CLOB NOT NULL, counter VARCHAR(255) NOT NULL, name VARCHAR(255) NOT NULL, last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, CONSTRAINT FK_4F4ADB4BA76ED395 FOREIGN KEY (user_id) REFERENCES "users" (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO u2f_keys (id, user_id, key_handle, public_key, certificate, counter, name, last_modified, datetime_added) SELECT id, user_id, key_handle, public_key, certificate, counter, name, last_modified, datetime_added FROM __temp__u2f_keys');
        $this->addSql('DROP TABLE __temp__u2f_keys');
        $this->addSql('CREATE UNIQUE INDEX user_unique ON u2f_keys (user_id, key_handle)');
        $this->addSql('CREATE INDEX IDX_4F4ADB4BA76ED395 ON u2f_keys (user_id)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__users AS SELECT id, group_id, currency_id, id_preview_attachement, disabled, config_theme, pw_reset_token, config_instock_comment_a, config_instock_comment_w, trusted_device_cookie_version, backup_codes, google_authenticator_secret, config_timezone, config_language, email, department, last_name, first_name, need_pw_change, password, name, settings, backup_codes_generation_date, pw_reset_expires, last_modified, datetime_added, perms_system, perms_groups, perms_users, perms_self, perms_system_config, perms_system_database, perms_parts, perms_parts_name, perms_parts_category, perms_parts_description, perms_parts_minamount, perms_parts_footprint, perms_parts_lots, perms_parts_tags, perms_parts_unit, perms_parts_mass, perms_parts_manufacturer, perms_parts_status, perms_parts_mpn, perms_parts_comment, perms_parts_order, perms_parts_orderdetails, perms_parts_prices, perms_parts_parameters, perms_parts_attachements, perms_devices, perms_devices_parts, perms_storelocations, perms_footprints, perms_categories, perms_suppliers, perms_manufacturers, perms_attachement_types, perms_currencies, perms_measurement_units, perms_tools, perms_labels FROM users');
        $this->addSql('DROP TABLE users');
        $this->addSql('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, group_id INTEGER DEFAULT NULL, currency_id INTEGER DEFAULT NULL, id_preview_attachement INTEGER DEFAULT NULL, disabled BOOLEAN NOT NULL, config_theme VARCHAR(255) DEFAULT NULL, pw_reset_token VARCHAR(255) DEFAULT NULL, config_instock_comment_a CLOB NOT NULL, config_instock_comment_w CLOB NOT NULL, trusted_device_cookie_version INTEGER NOT NULL, backup_codes CLOB NOT NULL --(DC2Type:json)
        , google_authenticator_secret VARCHAR(255) DEFAULT NULL, config_timezone VARCHAR(255) DEFAULT NULL, config_language VARCHAR(255) DEFAULT NULL, email VARCHAR(255) DEFAULT NULL, department VARCHAR(255) DEFAULT NULL, last_name VARCHAR(255) DEFAULT NULL, first_name VARCHAR(255) DEFAULT NULL, need_pw_change BOOLEAN NOT NULL, password VARCHAR(255) DEFAULT NULL, name VARCHAR(180) NOT NULL, settings CLOB NOT NULL --(DC2Type:json)
        , backup_codes_generation_date DATETIME DEFAULT NULL, pw_reset_expires DATETIME DEFAULT NULL, last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, perms_system INTEGER NOT NULL, perms_groups INTEGER NOT NULL, perms_users INTEGER NOT NULL, perms_self INTEGER NOT NULL, perms_system_config INTEGER NOT NULL, perms_system_database INTEGER NOT NULL, perms_parts BIGINT NOT NULL, perms_parts_name SMALLINT NOT NULL, perms_parts_category SMALLINT NOT NULL, perms_parts_description SMALLINT NOT NULL, perms_parts_minamount SMALLINT NOT NULL, perms_parts_footprint SMALLINT NOT NULL, perms_parts_lots SMALLINT NOT NULL, perms_parts_tags SMALLINT NOT NULL, perms_parts_unit SMALLINT NOT NULL, perms_parts_mass SMALLINT NOT NULL, perms_parts_manufacturer SMALLINT NOT NULL, perms_parts_status SMALLINT NOT NULL, perms_parts_mpn SMALLINT NOT NULL, perms_parts_comment SMALLINT NOT NULL, perms_parts_order SMALLINT NOT NULL, perms_parts_orderdetails SMALLINT NOT NULL, perms_parts_prices SMALLINT NOT NULL, perms_parts_parameters SMALLINT NOT NULL, perms_parts_attachements SMALLINT NOT NULL, perms_devices INTEGER NOT NULL, perms_devices_parts INTEGER NOT NULL, perms_storelocations INTEGER NOT NULL, perms_footprints INTEGER NOT NULL, perms_categories INTEGER NOT NULL, perms_suppliers INTEGER NOT NULL, perms_manufacturers INTEGER NOT NULL, perms_attachement_types INTEGER NOT NULL, perms_currencies INTEGER NOT NULL, perms_measurement_units INTEGER NOT NULL, perms_tools INTEGER NOT NULL, perms_labels INTEGER NOT NULL, CONSTRAINT FK_1483A5E9FE54D947 FOREIGN KEY (group_id) REFERENCES "groups" (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_1483A5E938248176 FOREIGN KEY (currency_id) REFERENCES currencies (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_1483A5E96DEDCEC2 FOREIGN KEY (id_preview_attachement) REFERENCES "attachments" (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO users (id, group_id, currency_id, id_preview_attachement, disabled, config_theme, pw_reset_token, config_instock_comment_a, config_instock_comment_w, trusted_device_cookie_version, backup_codes, google_authenticator_secret, config_timezone, config_language, email, department, last_name, first_name, need_pw_change, password, name, settings, backup_codes_generation_date, pw_reset_expires, last_modified, datetime_added, perms_system, perms_groups, perms_users, perms_self, perms_system_config, perms_system_database, perms_parts, perms_parts_name, perms_parts_category, perms_parts_description, perms_parts_minamount, perms_parts_footprint, perms_parts_lots, perms_parts_tags, perms_parts_unit, perms_parts_mass, perms_parts_manufacturer, perms_parts_status, perms_parts_mpn, perms_parts_comment, perms_parts_order, perms_parts_orderdetails, perms_parts_prices, perms_parts_parameters, perms_parts_attachements, perms_devices, perms_devices_parts, perms_storelocations, perms_footprints, perms_categories, perms_suppliers, perms_manufacturers, perms_attachement_types, perms_currencies, perms_measurement_units, perms_tools, perms_labels) SELECT id, group_id, currency_id, id_preview_attachement, disabled, config_theme, pw_reset_token, config_instock_comment_a, config_instock_comment_w, trusted_device_cookie_version, backup_codes, google_authenticator_secret, config_timezone, config_language, email, department, last_name, first_name, need_pw_change, password, name, settings, backup_codes_generation_date, pw_reset_expires, last_modified, datetime_added, perms_system, perms_groups, perms_users, perms_self, perms_system_config, perms_system_database, perms_parts, perms_parts_name, perms_parts_category, perms_parts_description, perms_parts_minamount, perms_parts_footprint, perms_parts_lots, perms_parts_tags, perms_parts_unit, perms_parts_mass, perms_parts_manufacturer, perms_parts_status, perms_parts_mpn, perms_parts_comment, perms_parts_order, perms_parts_orderdetails, perms_parts_prices, perms_parts_parameters, perms_parts_attachements, perms_devices, perms_devices_parts, perms_storelocations, perms_footprints, perms_categories, perms_suppliers, perms_manufacturers, perms_attachement_types, perms_currencies, perms_measurement_units, perms_tools, perms_labels FROM __temp__users');
        $this->addSql('DROP TABLE __temp__users');
        $this->addSql('CREATE INDEX IDX_1483A5E96DEDCEC2 ON users (id_preview_attachement)');
        $this->addSql('CREATE INDEX IDX_1483A5E938248176 ON users (currency_id)');
        $this->addSql('CREATE INDEX IDX_1483A5E9FE54D947 ON users (group_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_1483A5E95E237E06 ON users (name)');
        $this->addSql('CREATE INDEX user_idx_username ON users (name)');
    }

    public function sqLiteDown(Schema $schema): void
    {
        $this->addSql('CREATE TEMPORARY TABLE __temp__attachment_types AS SELECT id, parent_id, id_preview_attachement, filetype_filter, comment, not_selectable, name, last_modified, datetime_added FROM "attachment_types"');
        $this->addSql('DROP TABLE "attachment_types"');
        $this->addSql('CREATE TABLE "attachment_types" (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, parent_id INTEGER DEFAULT NULL, id_preview_attachement INTEGER DEFAULT NULL, filetype_filter CLOB NOT NULL, comment CLOB NOT NULL, not_selectable BOOLEAN NOT NULL, name VARCHAR(255) NOT NULL, last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL)');
        $this->addSql('INSERT INTO "attachment_types" (id, parent_id, id_preview_attachement, filetype_filter, comment, not_selectable, name, last_modified, datetime_added) SELECT id, parent_id, id_preview_attachement, filetype_filter, comment, not_selectable, name, last_modified, datetime_added FROM __temp__attachment_types');
        $this->addSql('DROP TABLE __temp__attachment_types');
        $this->addSql('CREATE INDEX IDX_EFAED719727ACA70 ON "attachment_types" (parent_id)');
        $this->addSql('CREATE INDEX IDX_EFAED7196DEDCEC2 ON "attachment_types" (id_preview_attachement)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__attachments AS SELECT id, type_id, original_filename, path, show_in_table, name, last_modified, datetime_added, class_name, element_id FROM "attachments"');
        $this->addSql('DROP TABLE "attachments"');
        $this->addSql('CREATE TABLE "attachments" (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, type_id INTEGER DEFAULT NULL, original_filename VARCHAR(255) DEFAULT NULL, path VARCHAR(255) NOT NULL, show_in_table BOOLEAN NOT NULL, name VARCHAR(255) NOT NULL, last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, class_name VARCHAR(255) NOT NULL, element_id INTEGER NOT NULL)');
        $this->addSql('INSERT INTO "attachments" (id, type_id, original_filename, path, show_in_table, name, last_modified, datetime_added, class_name, element_id) SELECT id, type_id, original_filename, path, show_in_table, name, last_modified, datetime_added, class_name, element_id FROM __temp__attachments');
        $this->addSql('DROP TABLE __temp__attachments');
        $this->addSql('CREATE INDEX IDX_47C4FAD6C54C8C93 ON "attachments" (type_id)');
        $this->addSql('CREATE INDEX IDX_47C4FAD61F1F2A24 ON "attachments" (element_id)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__categories AS SELECT id, parent_id, id_preview_attachement, partname_hint, partname_regex, disable_footprints, disable_manufacturers, disable_autodatasheets, disable_properties, default_description, default_comment, comment, not_selectable, name, last_modified, datetime_added FROM "categories"');
        $this->addSql('DROP TABLE "categories"');
        $this->addSql('CREATE TABLE "categories" (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, parent_id INTEGER DEFAULT NULL, id_preview_attachement INTEGER DEFAULT NULL, partname_hint CLOB NOT NULL, partname_regex CLOB NOT NULL, disable_footprints BOOLEAN NOT NULL, disable_manufacturers BOOLEAN NOT NULL, disable_autodatasheets BOOLEAN NOT NULL, disable_properties BOOLEAN NOT NULL, default_description CLOB NOT NULL, default_comment CLOB NOT NULL, comment CLOB NOT NULL, not_selectable BOOLEAN NOT NULL, name VARCHAR(255) NOT NULL, last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL)');
        $this->addSql('INSERT INTO "categories" (id, parent_id, id_preview_attachement, partname_hint, partname_regex, disable_footprints, disable_manufacturers, disable_autodatasheets, disable_properties, default_description, default_comment, comment, not_selectable, name, last_modified, datetime_added) SELECT id, parent_id, id_preview_attachement, partname_hint, partname_regex, disable_footprints, disable_manufacturers, disable_autodatasheets, disable_properties, default_description, default_comment, comment, not_selectable, name, last_modified, datetime_added FROM __temp__categories');
        $this->addSql('DROP TABLE __temp__categories');
        $this->addSql('CREATE INDEX IDX_3AF34668727ACA70 ON "categories" (parent_id)');
        $this->addSql('CREATE INDEX IDX_3AF346686DEDCEC2 ON "categories" (id_preview_attachement)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__currencies AS SELECT id, parent_id, id_preview_attachement, exchange_rate, iso_code, comment, not_selectable, name, last_modified, datetime_added FROM currencies');
        $this->addSql('DROP TABLE currencies');
        $this->addSql('CREATE TABLE currencies (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, parent_id INTEGER DEFAULT NULL, id_preview_attachement INTEGER DEFAULT NULL, exchange_rate NUMERIC(11, 5) DEFAULT NULL --
(DC2Type:big_decimal)
        , iso_code VARCHAR(255) NOT NULL, comment CLOB NOT NULL, not_selectable BOOLEAN NOT NULL, name VARCHAR(255) NOT NULL, last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL)');
        $this->addSql('INSERT INTO currencies (id, parent_id, id_preview_attachement, exchange_rate, iso_code, comment, not_selectable, name, last_modified, datetime_added) SELECT id, parent_id, id_preview_attachement, exchange_rate, iso_code, comment, not_selectable, name, last_modified, datetime_added FROM __temp__currencies');
        $this->addSql('DROP TABLE __temp__currencies');
        $this->addSql('CREATE INDEX IDX_37C44693727ACA70 ON currencies (parent_id)');
        $this->addSql('CREATE INDEX IDX_37C446936DEDCEC2 ON currencies (id_preview_attachement)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__device_parts AS SELECT id, id_device, id_part, quantity, mountnames FROM "device_parts"');
        $this->addSql('DROP TABLE "device_parts"');
        $this->addSql('CREATE TABLE "device_parts" (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, id_device INTEGER DEFAULT NULL, id_part INTEGER DEFAULT NULL, quantity INTEGER NOT NULL, mountnames CLOB NOT NULL)');
        $this->addSql('INSERT INTO "device_parts" (id, id_device, id_part, quantity, mountnames) SELECT id, id_device, id_part, quantity, mountnames FROM __temp__device_parts');
        $this->addSql('DROP TABLE __temp__device_parts');
        $this->addSql('CREATE INDEX IDX_AFC547992F180363 ON "device_parts" (id_device)');
        $this->addSql('CREATE INDEX IDX_AFC54799C22F6CC4 ON "device_parts" (id_part)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__devices AS SELECT id, parent_id, id_preview_attachement, order_quantity, order_only_missing_parts, comment, not_selectable, name, last_modified, datetime_added FROM "devices"');
        $this->addSql('DROP TABLE "devices"');
        $this->addSql('CREATE TABLE "devices" (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, parent_id INTEGER DEFAULT NULL, id_preview_attachement INTEGER DEFAULT NULL, order_quantity INTEGER NOT NULL, order_only_missing_parts BOOLEAN NOT NULL, comment CLOB NOT NULL, not_selectable BOOLEAN NOT NULL, name VARCHAR(255) NOT NULL, last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL)');
        $this->addSql('INSERT INTO "devices" (id, parent_id, id_preview_attachement, order_quantity, order_only_missing_parts, comment, not_selectable, name, last_modified, datetime_added) SELECT id, parent_id, id_preview_attachement, order_quantity, order_only_missing_parts, comment, not_selectable, name, last_modified, datetime_added FROM __temp__devices');
        $this->addSql('DROP TABLE __temp__devices');
        $this->addSql('CREATE INDEX IDX_11074E9A727ACA70 ON "devices" (parent_id)');
        $this->addSql('CREATE INDEX IDX_11074E9A6DEDCEC2 ON "devices" (id_preview_attachement)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__footprints AS SELECT id, parent_id, id_footprint_3d, id_preview_attachement, comment, not_selectable, name, last_modified, datetime_added FROM "footprints"');
        $this->addSql('DROP TABLE "footprints"');
        $this->addSql('CREATE TABLE "footprints" (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, parent_id INTEGER DEFAULT NULL, id_footprint_3d INTEGER DEFAULT NULL, id_preview_attachement INTEGER DEFAULT NULL, comment CLOB NOT NULL, not_selectable BOOLEAN NOT NULL, name VARCHAR(255) NOT NULL, last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL)');
        $this->addSql('INSERT INTO "footprints" (id, parent_id, id_footprint_3d, id_preview_attachement, comment, not_selectable, name, last_modified, datetime_added) SELECT id, parent_id, id_footprint_3d, id_preview_attachement, comment, not_selectable, name, last_modified, datetime_added FROM __temp__footprints');
        $this->addSql('DROP TABLE __temp__footprints');
        $this->addSql('CREATE INDEX IDX_A34D68A2727ACA70 ON "footprints" (parent_id)');
        $this->addSql('CREATE INDEX IDX_A34D68A232A38C34 ON "footprints" (id_footprint_3d)');
        $this->addSql('CREATE INDEX IDX_A34D68A26DEDCEC2 ON "footprints" (id_preview_attachement)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__groups AS SELECT id, parent_id, id_preview_attachement, enforce_2fa, comment, not_selectable, name, last_modified, datetime_added, perms_system, perms_groups, perms_users, perms_self, perms_system_config, perms_system_database, perms_parts, perms_parts_name, perms_parts_category, perms_parts_description, perms_parts_minamount, perms_parts_footprint, perms_parts_lots, perms_parts_tags, perms_parts_unit, perms_parts_mass, perms_parts_manufacturer, perms_parts_status, perms_parts_mpn, perms_parts_comment, perms_parts_order, perms_parts_orderdetails, perms_parts_prices, perms_parts_parameters, perms_parts_attachements, perms_devices, perms_devices_parts, perms_storelocations, perms_footprints, perms_categories, perms_suppliers, perms_manufacturers, perms_attachement_types, perms_currencies, perms_measurement_units, perms_tools, perms_labels FROM "groups"');
        $this->addSql('DROP TABLE "groups"');
        $this->addSql('CREATE TABLE "groups" (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, parent_id INTEGER DEFAULT NULL, id_preview_attachement INTEGER DEFAULT NULL, enforce_2fa BOOLEAN NOT NULL, comment CLOB NOT NULL, not_selectable BOOLEAN NOT NULL, name VARCHAR(255) NOT NULL, last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, perms_system INTEGER NOT NULL, perms_groups INTEGER NOT NULL, perms_users INTEGER NOT NULL, perms_self INTEGER NOT NULL, perms_system_config INTEGER NOT NULL, perms_system_database INTEGER NOT NULL, perms_parts BIGINT NOT NULL, perms_parts_name SMALLINT NOT NULL, perms_parts_category SMALLINT NOT NULL, perms_parts_description SMALLINT NOT NULL, perms_parts_minamount SMALLINT NOT NULL, perms_parts_footprint SMALLINT NOT NULL, perms_parts_lots SMALLINT NOT NULL, perms_parts_tags SMALLINT NOT NULL, perms_parts_unit SMALLINT NOT NULL, perms_parts_mass SMALLINT NOT NULL, perms_parts_manufacturer SMALLINT NOT NULL, perms_parts_status SMALLINT NOT NULL, perms_parts_mpn SMALLINT NOT NULL, perms_parts_comment SMALLINT NOT NULL, perms_parts_order SMALLINT NOT NULL, perms_parts_orderdetails SMALLINT NOT NULL, perms_parts_prices SMALLINT NOT NULL, perms_parts_parameters SMALLINT NOT NULL, perms_parts_attachements SMALLINT NOT NULL, perms_devices INTEGER NOT NULL, perms_devices_parts INTEGER NOT NULL, perms_storelocations INTEGER NOT NULL, perms_footprints INTEGER NOT NULL, perms_categories INTEGER NOT NULL, perms_suppliers INTEGER NOT NULL, perms_manufacturers INTEGER NOT NULL, perms_attachement_types INTEGER NOT NULL, perms_currencies INTEGER NOT NULL, perms_measurement_units INTEGER NOT NULL, perms_tools INTEGER NOT NULL, perms_labels INTEGER NOT NULL)');
        $this->addSql('INSERT INTO "groups" (id, parent_id, id_preview_attachement, enforce_2fa, comment, not_selectable, name, last_modified, datetime_added, perms_system, perms_groups, perms_users, perms_self, perms_system_config, perms_system_database, perms_parts, perms_parts_name, perms_parts_category, perms_parts_description, perms_parts_minamount, perms_parts_footprint, perms_parts_lots, perms_parts_tags, perms_parts_unit, perms_parts_mass, perms_parts_manufacturer, perms_parts_status, perms_parts_mpn, perms_parts_comment, perms_parts_order, perms_parts_orderdetails, perms_parts_prices, perms_parts_parameters, perms_parts_attachements, perms_devices, perms_devices_parts, perms_storelocations, perms_footprints, perms_categories, perms_suppliers, perms_manufacturers, perms_attachement_types, perms_currencies, perms_measurement_units, perms_tools, perms_labels) SELECT id, parent_id, id_preview_attachement, enforce_2fa, comment, not_selectable, name, last_modified, datetime_added, perms_system, perms_groups, perms_users, perms_self, perms_system_config, perms_system_database, perms_parts, perms_parts_name, perms_parts_category, perms_parts_description, perms_parts_minamount, perms_parts_footprint, perms_parts_lots, perms_parts_tags, perms_parts_unit, perms_parts_mass, perms_parts_manufacturer, perms_parts_status, perms_parts_mpn, perms_parts_comment, perms_parts_order, perms_parts_orderdetails, perms_parts_prices, perms_parts_parameters, perms_parts_attachements, perms_devices, perms_devices_parts, perms_storelocations, perms_footprints, perms_categories, perms_suppliers, perms_manufacturers, perms_attachement_types, perms_currencies, perms_measurement_units, perms_tools, perms_labels FROM __temp__groups');
        $this->addSql('DROP TABLE __temp__groups');
        $this->addSql('CREATE INDEX IDX_F06D3970727ACA70 ON "groups" (parent_id)');
        $this->addSql('CREATE INDEX IDX_F06D39706DEDCEC2 ON "groups" (id_preview_attachement)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__label_profiles AS SELECT id, id_preview_attachement, comment, show_in_dropdown, name, last_modified, datetime_added, options_width, options_height, options_barcode_type, options_picture_type, options_supported_element, options_additional_css, options_lines_mode, options_lines FROM label_profiles');
        $this->addSql('DROP TABLE label_profiles');
        $this->addSql('CREATE TABLE label_profiles (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, id_preview_attachement INTEGER DEFAULT NULL, comment CLOB NOT NULL, show_in_dropdown BOOLEAN NOT NULL, name VARCHAR(255) NOT NULL, last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, options_width DOUBLE PRECISION NOT NULL, options_height DOUBLE PRECISION NOT NULL, options_barcode_type VARCHAR(255) NOT NULL, options_picture_type VARCHAR(255) NOT NULL, options_supported_element VARCHAR(255) NOT NULL, options_additional_css CLOB NOT NULL, options_lines_mode VARCHAR(255) NOT NULL, options_lines CLOB NOT NULL)');
        $this->addSql('INSERT INTO label_profiles (id, id_preview_attachement, comment, show_in_dropdown, name, last_modified, datetime_added, options_width, options_height, options_barcode_type, options_picture_type, options_supported_element, options_additional_css, options_lines_mode, options_lines) SELECT id, id_preview_attachement, comment, show_in_dropdown, name, last_modified, datetime_added, options_width, options_height, options_barcode_type, options_picture_type, options_supported_element, options_additional_css, options_lines_mode, options_lines FROM __temp__label_profiles');
        $this->addSql('DROP TABLE __temp__label_profiles');
        $this->addSql('CREATE INDEX IDX_C93E9CF56DEDCEC2 ON label_profiles (id_preview_attachement)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__log AS SELECT id, id_user, datetime, level, target_id, target_type, extra, type FROM log');
        $this->addSql('DROP TABLE log');
        $this->addSql('CREATE TABLE log (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, id_user INTEGER NOT NULL, datetime DATETIME NOT NULL, level BOOLEAN NOT NULL, target_id INTEGER NOT NULL, target_type SMALLINT NOT NULL, extra CLOB NOT NULL --
(DC2Type:json)
        , type SMALLINT NOT NULL)');
        $this->addSql('INSERT INTO log (id, id_user, datetime, level, target_id, target_type, extra, type) SELECT id, id_user, datetime, level, target_id, target_type, extra, type FROM __temp__log');
        $this->addSql('DROP TABLE __temp__log');
        $this->addSql('CREATE INDEX IDX_8F3F68C56B3CA4B ON log (id_user)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__manufacturers AS SELECT id, parent_id, id_preview_attachement, address, phone_number, fax_number, email_address, website, auto_product_url, comment, not_selectable, name, last_modified, datetime_added FROM "manufacturers"');
        $this->addSql('DROP TABLE "manufacturers"');
        $this->addSql('CREATE TABLE "manufacturers" (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, parent_id INTEGER DEFAULT NULL, id_preview_attachement INTEGER DEFAULT NULL, address VARCHAR(255) NOT NULL, phone_number VARCHAR(255) NOT NULL, fax_number VARCHAR(255) NOT NULL, email_address VARCHAR(255) NOT NULL, website VARCHAR(255) NOT NULL, auto_product_url VARCHAR(255) NOT NULL, comment CLOB NOT NULL, not_selectable BOOLEAN NOT NULL, name VARCHAR(255) NOT NULL, last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL)');
        $this->addSql('INSERT INTO "manufacturers" (id, parent_id, id_preview_attachement, address, phone_number, fax_number, email_address, website, auto_product_url, comment, not_selectable, name, last_modified, datetime_added) SELECT id, parent_id, id_preview_attachement, address, phone_number, fax_number, email_address, website, auto_product_url, comment, not_selectable, name, last_modified, datetime_added FROM __temp__manufacturers');
        $this->addSql('DROP TABLE __temp__manufacturers');
        $this->addSql('CREATE INDEX IDX_94565B12727ACA70 ON "manufacturers" (parent_id)');
        $this->addSql('CREATE INDEX IDX_94565B126DEDCEC2 ON "manufacturers" (id_preview_attachement)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__measurement_units AS SELECT id, parent_id, id_preview_attachement, unit, is_integer, use_si_prefix, comment, not_selectable, name, last_modified, datetime_added FROM "measurement_units"');
        $this->addSql('DROP TABLE "measurement_units"');
        $this->addSql('CREATE TABLE "measurement_units" (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, parent_id INTEGER DEFAULT NULL, id_preview_attachement INTEGER DEFAULT NULL, unit VARCHAR(255) DEFAULT NULL, is_integer BOOLEAN NOT NULL, use_si_prefix BOOLEAN NOT NULL, comment CLOB NOT NULL, not_selectable BOOLEAN NOT NULL, name VARCHAR(255) NOT NULL, last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL)');
        $this->addSql('INSERT INTO "measurement_units" (id, parent_id, id_preview_attachement, unit, is_integer, use_si_prefix, comment, not_selectable, name, last_modified, datetime_added) SELECT id, parent_id, id_preview_attachement, unit, is_integer, use_si_prefix, comment, not_selectable, name, last_modified, datetime_added FROM __temp__measurement_units');
        $this->addSql('DROP TABLE __temp__measurement_units');
        $this->addSql('CREATE INDEX IDX_F5AF83CF727ACA70 ON "measurement_units" (parent_id)');
        $this->addSql('CREATE INDEX IDX_F5AF83CF6DEDCEC2 ON "measurement_units" (id_preview_attachement)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__orderdetails AS SELECT id, part_id, id_supplier, supplierpartnr, obsolete, supplier_product_url, last_modified, datetime_added FROM "orderdetails"');
        $this->addSql('DROP TABLE "orderdetails"');
        $this->addSql('CREATE TABLE "orderdetails" (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, part_id INTEGER NOT NULL, id_supplier INTEGER DEFAULT NULL, supplierpartnr VARCHAR(255) NOT NULL, obsolete BOOLEAN NOT NULL, supplier_product_url VARCHAR(255) NOT NULL, last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL)');
        $this->addSql('INSERT INTO "orderdetails" (id, part_id, id_supplier, supplierpartnr, obsolete, supplier_product_url, last_modified, datetime_added) SELECT id, part_id, id_supplier, supplierpartnr, obsolete, supplier_product_url, last_modified, datetime_added FROM __temp__orderdetails');
        $this->addSql('DROP TABLE __temp__orderdetails');
        $this->addSql('CREATE INDEX IDX_489AFCDC4CE34BEC ON "orderdetails" (part_id)');
        $this->addSql('CREATE INDEX IDX_489AFCDCCBF180EB ON "orderdetails" (id_supplier)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__parameters AS SELECT id, symbol, value_min, value_typical, value_max, unit, value_text, param_group, name, last_modified, datetime_added, type, element_id FROM parameters');
        $this->addSql('DROP TABLE parameters');
        $this->addSql('CREATE TABLE parameters (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, symbol VARCHAR(255) NOT NULL, value_min DOUBLE PRECISION DEFAULT NULL, value_typical DOUBLE PRECISION DEFAULT NULL, value_max DOUBLE PRECISION DEFAULT NULL, unit VARCHAR(255) NOT NULL, value_text VARCHAR(255) NOT NULL, param_group VARCHAR(255) NOT NULL, name VARCHAR(255) NOT NULL, last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, type SMALLINT NOT NULL, element_id INTEGER NOT NULL)');
        $this->addSql('INSERT INTO parameters (id, symbol, value_min, value_typical, value_max, unit, value_text, param_group, name, last_modified, datetime_added, type, element_id) SELECT id, symbol, value_min, value_typical, value_max, unit, value_text, param_group, name, last_modified, datetime_added, type, element_id FROM __temp__parameters');
        $this->addSql('DROP TABLE __temp__parameters');
        $this->addSql('CREATE INDEX IDX_69348FE1F1F2A24 ON parameters (element_id)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__part_lots AS SELECT id, id_store_location, id_part, description, comment, expiration_date, instock_unknown, amount, needs_refill, last_modified, datetime_added FROM part_lots');
        $this->addSql('DROP TABLE part_lots');
        $this->addSql('CREATE TABLE part_lots (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, id_store_location INTEGER DEFAULT NULL, id_part INTEGER NOT NULL, description CLOB NOT NULL, comment CLOB NOT NULL, expiration_date DATETIME DEFAULT NULL, instock_unknown BOOLEAN NOT NULL, amount DOUBLE PRECISION NOT NULL, needs_refill BOOLEAN NOT NULL, last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL)');
        $this->addSql('INSERT INTO part_lots (id, id_store_location, id_part, description, comment, expiration_date, instock_unknown, amount, needs_refill, last_modified, datetime_added) SELECT id, id_store_location, id_part, description, comment, expiration_date, instock_unknown, amount, needs_refill, last_modified, datetime_added FROM __temp__part_lots');
        $this->addSql('DROP TABLE __temp__part_lots');
        $this->addSql('CREATE INDEX IDX_EBC8F9435D8F4B37 ON part_lots (id_store_location)');
        $this->addSql('CREATE INDEX IDX_EBC8F943C22F6CC4 ON part_lots (id_part)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__parts AS SELECT id, id_preview_attachement, id_category, id_footprint, id_part_unit, id_manufacturer, order_orderdetails_id, datetime_added, name, last_modified, needs_review, tags, mass, description, comment, visible, favorite, minamount, manufacturer_product_url, manufacturer_product_number, manufacturing_status, order_quantity, manual_order FROM "parts"');
        $this->addSql('DROP TABLE "parts"');
        $this->addSql('CREATE TABLE "parts" (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, id_preview_attachement INTEGER DEFAULT NULL, id_category INTEGER NOT NULL, id_footprint INTEGER DEFAULT NULL, id_part_unit INTEGER DEFAULT NULL, id_manufacturer INTEGER DEFAULT NULL, order_orderdetails_id INTEGER DEFAULT NULL, datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, name VARCHAR(255) NOT NULL, last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, needs_review BOOLEAN NOT NULL, tags CLOB NOT NULL, mass DOUBLE PRECISION DEFAULT NULL, description CLOB NOT NULL, comment CLOB NOT NULL, visible BOOLEAN NOT NULL, favorite BOOLEAN NOT NULL, minamount DOUBLE PRECISION NOT NULL, manufacturer_product_url VARCHAR(255) NOT NULL, manufacturer_product_number VARCHAR(255) NOT NULL, manufacturing_status VARCHAR(255) DEFAULT NULL, order_quantity INTEGER NOT NULL, manual_order BOOLEAN NOT NULL)');
        $this->addSql('INSERT INTO "parts" (id, id_preview_attachement, id_category, id_footprint, id_part_unit, id_manufacturer, order_orderdetails_id, datetime_added, name, last_modified, needs_review, tags, mass, description, comment, visible, favorite, minamount, manufacturer_product_url, manufacturer_product_number, manufacturing_status, order_quantity, manual_order) SELECT id, id_preview_attachement, id_category, id_footprint, id_part_unit, id_manufacturer, order_orderdetails_id, datetime_added, name, last_modified, needs_review, tags, mass, description, comment, visible, favorite, minamount, manufacturer_product_url, manufacturer_product_number, manufacturing_status, order_quantity, manual_order FROM __temp__parts');
        $this->addSql('DROP TABLE __temp__parts');
        $this->addSql('CREATE INDEX IDX_6940A7FE6DEDCEC2 ON "parts" (id_preview_attachement)');
        $this->addSql('CREATE INDEX IDX_6940A7FE5697F554 ON "parts" (id_category)');
        $this->addSql('CREATE INDEX IDX_6940A7FE7E371A10 ON "parts" (id_footprint)');
        $this->addSql('CREATE INDEX IDX_6940A7FE2626CEF9 ON "parts" (id_part_unit)');
        $this->addSql('CREATE INDEX IDX_6940A7FE1ECB93AE ON "parts" (id_manufacturer)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_6940A7FE81081E9B ON "parts" (order_orderdetails_id)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__pricedetails AS SELECT id, id_currency, orderdetails_id, price, price_related_quantity, min_discount_quantity, manual_input, last_modified, datetime_added FROM "pricedetails"');
        $this->addSql('DROP TABLE "pricedetails"');
        $this->addSql('CREATE TABLE "pricedetails" (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, id_currency INTEGER DEFAULT NULL, orderdetails_id INTEGER NOT NULL, price NUMERIC(11, 5) NOT NULL --
(DC2Type:big_decimal)
        , price_related_quantity DOUBLE PRECISION NOT NULL, min_discount_quantity DOUBLE PRECISION NOT NULL, manual_input BOOLEAN NOT NULL, last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL)');
        $this->addSql('INSERT INTO "pricedetails" (id, id_currency, orderdetails_id, price, price_related_quantity, min_discount_quantity, manual_input, last_modified, datetime_added) SELECT id, id_currency, orderdetails_id, price, price_related_quantity, min_discount_quantity, manual_input, last_modified, datetime_added FROM __temp__pricedetails');
        $this->addSql('DROP TABLE __temp__pricedetails');
        $this->addSql('CREATE INDEX IDX_C68C4459398D64AA ON "pricedetails" (id_currency)');
        $this->addSql('CREATE INDEX IDX_C68C44594A01DDC7 ON "pricedetails" (orderdetails_id)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__storelocations AS SELECT id, parent_id, storage_type_id, id_preview_attachement, is_full, only_single_part, limit_to_existing_parts, comment, not_selectable, name, last_modified, datetime_added FROM "storelocations"');
        $this->addSql('DROP TABLE "storelocations"');
        $this->addSql('CREATE TABLE "storelocations" (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, parent_id INTEGER DEFAULT NULL, storage_type_id INTEGER DEFAULT NULL, id_preview_attachement INTEGER DEFAULT NULL, is_full BOOLEAN NOT NULL, only_single_part BOOLEAN NOT NULL, limit_to_existing_parts BOOLEAN NOT NULL, comment CLOB NOT NULL, not_selectable BOOLEAN NOT NULL, name VARCHAR(255) NOT NULL, last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL)');
        $this->addSql('INSERT INTO "storelocations" (id, parent_id, storage_type_id, id_preview_attachement, is_full, only_single_part, limit_to_existing_parts, comment, not_selectable, name, last_modified, datetime_added) SELECT id, parent_id, storage_type_id, id_preview_attachement, is_full, only_single_part, limit_to_existing_parts, comment, not_selectable, name, last_modified, datetime_added FROM __temp__storelocations');
        $this->addSql('DROP TABLE __temp__storelocations');
        $this->addSql('CREATE INDEX IDX_7517020727ACA70 ON "storelocations" (parent_id)');
        $this->addSql('CREATE INDEX IDX_7517020B270BFF1 ON "storelocations" (storage_type_id)');
        $this->addSql('CREATE INDEX IDX_75170206DEDCEC2 ON "storelocations" (id_preview_attachement)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__suppliers AS SELECT id, parent_id, default_currency_id, id_preview_attachement, shipping_costs, address, phone_number, fax_number, email_address, website, auto_product_url, comment, not_selectable, name, last_modified, datetime_added FROM "suppliers"');
        $this->addSql('DROP TABLE "suppliers"');
        $this->addSql('CREATE TABLE "suppliers" (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, parent_id INTEGER DEFAULT NULL, default_currency_id INTEGER DEFAULT NULL, id_preview_attachement INTEGER DEFAULT NULL, shipping_costs NUMERIC(11, 5) DEFAULT NULL --
(DC2Type:big_decimal)
        , address VARCHAR(255) NOT NULL, phone_number VARCHAR(255) NOT NULL, fax_number VARCHAR(255) NOT NULL, email_address VARCHAR(255) NOT NULL, website VARCHAR(255) NOT NULL, auto_product_url VARCHAR(255) NOT NULL, comment CLOB NOT NULL, not_selectable BOOLEAN NOT NULL, name VARCHAR(255) NOT NULL, last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL)');
        $this->addSql('INSERT INTO "suppliers" (id, parent_id, default_currency_id, id_preview_attachement, shipping_costs, address, phone_number, fax_number, email_address, website, auto_product_url, comment, not_selectable, name, last_modified, datetime_added) SELECT id, parent_id, default_currency_id, id_preview_attachement, shipping_costs, address, phone_number, fax_number, email_address, website, auto_product_url, comment, not_selectable, name, last_modified, datetime_added FROM __temp__suppliers');
        $this->addSql('DROP TABLE __temp__suppliers');
        $this->addSql('CREATE INDEX IDX_AC28B95C727ACA70 ON "suppliers" (parent_id)');
        $this->addSql('CREATE INDEX IDX_AC28B95CECD792C0 ON "suppliers" (default_currency_id)');
        $this->addSql('CREATE INDEX IDX_AC28B95C6DEDCEC2 ON "suppliers" (id_preview_attachement)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__u2f_keys AS SELECT id, user_id, key_handle, public_key, certificate, counter, name, last_modified, datetime_added FROM u2f_keys');
        $this->addSql('DROP TABLE u2f_keys');
        $this->addSql('CREATE TABLE u2f_keys (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, user_id INTEGER DEFAULT NULL, key_handle VARCHAR(128) NOT NULL, public_key VARCHAR(255) NOT NULL, certificate CLOB NOT NULL, counter VARCHAR(255) NOT NULL, name VARCHAR(255) NOT NULL, last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL)');
        $this->addSql('INSERT INTO u2f_keys (id, user_id, key_handle, public_key, certificate, counter, name, last_modified, datetime_added) SELECT id, user_id, key_handle, public_key, certificate, counter, name, last_modified, datetime_added FROM __temp__u2f_keys');
        $this->addSql('DROP TABLE __temp__u2f_keys');
        $this->addSql('CREATE INDEX IDX_4F4ADB4BA76ED395 ON u2f_keys (user_id)');
        $this->addSql('CREATE UNIQUE INDEX user_unique ON u2f_keys (user_id, key_handle)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__users AS SELECT id, group_id, currency_id, id_preview_attachement, disabled, config_theme, pw_reset_token, config_instock_comment_a, config_instock_comment_w, trusted_device_cookie_version, backup_codes, google_authenticator_secret, config_timezone, config_language, email, department, last_name, first_name, need_pw_change, password, name, settings, backup_codes_generation_date, pw_reset_expires, last_modified, datetime_added, perms_system, perms_groups, perms_users, perms_self, perms_system_config, perms_system_database, perms_parts, perms_parts_name, perms_parts_category, perms_parts_description, perms_parts_minamount, perms_parts_footprint, perms_parts_lots, perms_parts_tags, perms_parts_unit, perms_parts_mass, perms_parts_manufacturer, perms_parts_status, perms_parts_mpn, perms_parts_comment, perms_parts_order, perms_parts_orderdetails, perms_parts_prices, perms_parts_parameters, perms_parts_attachements, perms_devices, perms_devices_parts, perms_storelocations, perms_footprints, perms_categories, perms_suppliers, perms_manufacturers, perms_attachement_types, perms_currencies, perms_measurement_units, perms_tools, perms_labels FROM "users"');
        $this->addSql('DROP TABLE "users"');
        $this->addSql('CREATE TABLE "users" (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, group_id INTEGER DEFAULT NULL, currency_id INTEGER DEFAULT NULL, id_preview_attachement INTEGER DEFAULT NULL, disabled BOOLEAN NOT NULL, config_theme VARCHAR(255) DEFAULT NULL, pw_reset_token VARCHAR(255) DEFAULT NULL, config_instock_comment_a CLOB NOT NULL, config_instock_comment_w CLOB NOT NULL, trusted_device_cookie_version INTEGER NOT NULL, backup_codes CLOB NOT NULL --
(DC2Type:json)
        , google_authenticator_secret VARCHAR(255) DEFAULT NULL, config_timezone VARCHAR(255) DEFAULT NULL, config_language VARCHAR(255) DEFAULT NULL, email VARCHAR(255) DEFAULT NULL, department VARCHAR(255) DEFAULT NULL, last_name VARCHAR(255) DEFAULT NULL, first_name VARCHAR(255) DEFAULT NULL, need_pw_change BOOLEAN NOT NULL, password VARCHAR(255) DEFAULT NULL, name VARCHAR(180) NOT NULL, settings CLOB NOT NULL --
(DC2Type:json)
        , backup_codes_generation_date DATETIME DEFAULT NULL, pw_reset_expires DATETIME DEFAULT NULL, last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, perms_system INTEGER NOT NULL, perms_groups INTEGER NOT NULL, perms_users INTEGER NOT NULL, perms_self INTEGER NOT NULL, perms_system_config INTEGER NOT NULL, perms_system_database INTEGER NOT NULL, perms_parts BIGINT NOT NULL, perms_parts_name SMALLINT NOT NULL, perms_parts_category SMALLINT NOT NULL, perms_parts_description SMALLINT NOT NULL, perms_parts_minamount SMALLINT NOT NULL, perms_parts_footprint SMALLINT NOT NULL, perms_parts_lots SMALLINT NOT NULL, perms_parts_tags SMALLINT NOT NULL, perms_parts_unit SMALLINT NOT NULL, perms_parts_mass SMALLINT NOT NULL, perms_parts_manufacturer SMALLINT NOT NULL, perms_parts_status SMALLINT NOT NULL, perms_parts_mpn SMALLINT NOT NULL, perms_parts_comment SMALLINT NOT NULL, perms_parts_order SMALLINT NOT NULL, perms_parts_orderdetails SMALLINT NOT NULL, perms_parts_prices SMALLINT NOT NULL, perms_parts_parameters SMALLINT NOT NULL, perms_parts_attachements SMALLINT NOT NULL, perms_devices INTEGER NOT NULL, perms_devices_parts INTEGER NOT NULL, perms_storelocations INTEGER NOT NULL, perms_footprints INTEGER NOT NULL, perms_categories INTEGER NOT NULL, perms_suppliers INTEGER NOT NULL, perms_manufacturers INTEGER NOT NULL, perms_attachement_types INTEGER NOT NULL, perms_currencies INTEGER NOT NULL, perms_measurement_units INTEGER NOT NULL, perms_tools INTEGER NOT NULL, perms_labels INTEGER NOT NULL)');
        $this->addSql('INSERT INTO "users" (id, group_id, currency_id, id_preview_attachement, disabled, config_theme, pw_reset_token, config_instock_comment_a, config_instock_comment_w, trusted_device_cookie_version, backup_codes, google_authenticator_secret, config_timezone, config_language, email, department, last_name, first_name, need_pw_change, password, name, settings, backup_codes_generation_date, pw_reset_expires, last_modified, datetime_added, perms_system, perms_groups, perms_users, perms_self, perms_system_config, perms_system_database, perms_parts, perms_parts_name, perms_parts_category, perms_parts_description, perms_parts_minamount, perms_parts_footprint, perms_parts_lots, perms_parts_tags, perms_parts_unit, perms_parts_mass, perms_parts_manufacturer, perms_parts_status, perms_parts_mpn, perms_parts_comment, perms_parts_order, perms_parts_orderdetails, perms_parts_prices, perms_parts_parameters, perms_parts_attachements, perms_devices, perms_devices_parts, perms_storelocations, perms_footprints, perms_categories, perms_suppliers, perms_manufacturers, perms_attachement_types, perms_currencies, perms_measurement_units, perms_tools, perms_labels) SELECT id, group_id, currency_id, id_preview_attachement, disabled, config_theme, pw_reset_token, config_instock_comment_a, config_instock_comment_w, trusted_device_cookie_version, backup_codes, google_authenticator_secret, config_timezone, config_language, email, department, last_name, first_name, need_pw_change, password, name, settings, backup_codes_generation_date, pw_reset_expires, last_modified, datetime_added, perms_system, perms_groups, perms_users, perms_self, perms_system_config, perms_system_database, perms_parts, perms_parts_name, perms_parts_category, perms_parts_description, perms_parts_minamount, perms_parts_footprint, perms_parts_lots, perms_parts_tags, perms_parts_unit, perms_parts_mass, perms_parts_manufacturer, perms_parts_status, perms_parts_mpn, perms_parts_comment, perms_parts_order, perms_parts_orderdetails, perms_parts_prices, perms_parts_parameters, perms_parts_attachements, perms_devices, perms_devices_parts, perms_storelocations, perms_footprints, perms_categories, perms_suppliers, perms_manufacturers, perms_attachement_types, perms_currencies, perms_measurement_units, perms_tools, perms_labels FROM __temp__users');
        $this->addSql('DROP TABLE __temp__users');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_1483A5E95E237E06 ON "users" (name)');
        $this->addSql('CREATE INDEX IDX_1483A5E9FE54D947 ON "users" (group_id)');
        $this->addSql('CREATE INDEX IDX_1483A5E938248176 ON "users" (currency_id)');
        $this->addSql('CREATE INDEX IDX_1483A5E96DEDCEC2 ON "users" (id_preview_attachement)');
    }

    public function postgreSQLUp(Schema $schema): void
    {
        $this->warnIf(true, "Migration not needed for Postgres. Skipping...");
    }

    public function postgreSQLDown(Schema $schema): void
    {
        $this->warnIf(true, "Migration not needed for Postgres. Skipping...");
    }
}
