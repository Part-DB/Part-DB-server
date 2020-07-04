<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Migration\AbstractMultiPlatformMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200502161750 extends AbstractMultiPlatformMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function mySQLUp(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf('mysql' !== $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('CREATE TABLE label_profiles (id INT AUTO_INCREMENT NOT NULL, id_preview_attachement INT DEFAULT NULL, comment LONGTEXT NOT NULL, show_in_dropdown TINYINT(1) NOT NULL, name VARCHAR(255) NOT NULL, last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, options_width DOUBLE PRECISION NOT NULL, options_height DOUBLE PRECISION NOT NULL, options_barcode_type VARCHAR(255) NOT NULL, options_picture_type VARCHAR(255) NOT NULL, options_supported_element VARCHAR(255) NOT NULL, options_additional_css LONGTEXT NOT NULL, options_lines_mode VARCHAR(255) NOT NULL, options_lines LONGTEXT NOT NULL, INDEX IDX_C93E9CF56DEDCEC2 (id_preview_attachement), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE label_profiles ADD CONSTRAINT FK_C93E9CF56DEDCEC2 FOREIGN KEY (id_preview_attachement) REFERENCES `attachments` (id)');
        $this->addSql('ALTER TABLE log CHANGE level level TINYINT(4) NOT NULL');
    }

    public function mySQLDown(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf('mysql' !== $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('DROP TABLE label_profiles');
        $this->addSql('ALTER TABLE log CHANGE level level TINYINT(1) DEFAULT NULL');
    }

    public function sqLiteUp(Schema $schema): void
    {
        /* Create inital DB schema for SQLite */
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'sqlite', 'Migration can only be executed safely on \'sqlite\'.');

        $this->addSql('CREATE TABLE "attachments" (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, type_id INTEGER DEFAULT NULL, original_filename VARCHAR(255) DEFAULT NULL, path VARCHAR(255) NOT NULL, show_in_table BOOLEAN NOT NULL, name VARCHAR(255) NOT NULL, last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, class_name VARCHAR(255) NOT NULL, element_id INTEGER NOT NULL)');
        $this->addSql('CREATE INDEX IDX_47C4FAD6C54C8C93 ON "attachments" (type_id)');
        $this->addSql('CREATE INDEX IDX_47C4FAD61F1F2A24 ON "attachments" (element_id)');
        $this->addSql('CREATE TABLE "attachment_types" (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, parent_id INTEGER DEFAULT NULL, id_preview_attachement INTEGER DEFAULT NULL, filetype_filter CLOB NOT NULL, comment CLOB NOT NULL, not_selectable BOOLEAN NOT NULL, name VARCHAR(255) NOT NULL, last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL)');
        $this->addSql('CREATE INDEX IDX_EFAED719727ACA70 ON "attachment_types" (parent_id)');
        $this->addSql('CREATE INDEX IDX_EFAED7196DEDCEC2 ON "attachment_types" (id_preview_attachement)');
        $this->addSql('CREATE TABLE "devices" (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, parent_id INTEGER DEFAULT NULL, id_preview_attachement INTEGER DEFAULT NULL, order_quantity INTEGER NOT NULL, order_only_missing_parts BOOLEAN NOT NULL, comment CLOB NOT NULL, not_selectable BOOLEAN NOT NULL, name VARCHAR(255) NOT NULL, last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL)');
        $this->addSql('CREATE INDEX IDX_11074E9A727ACA70 ON "devices" (parent_id)');
        $this->addSql('CREATE INDEX IDX_11074E9A6DEDCEC2 ON "devices" (id_preview_attachement)');
        $this->addSql('CREATE TABLE "device_parts" (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, id_device INTEGER DEFAULT NULL, id_part INTEGER DEFAULT NULL, quantity INTEGER NOT NULL, mountnames CLOB NOT NULL)');
        $this->addSql('CREATE INDEX IDX_AFC547992F180363 ON "device_parts" (id_device)');
        $this->addSql('CREATE INDEX IDX_AFC54799C22F6CC4 ON "device_parts" (id_part)');
        $this->addSql('CREATE TABLE label_profiles (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, id_preview_attachement INTEGER DEFAULT NULL, comment CLOB NOT NULL, show_in_dropdown BOOLEAN NOT NULL, name VARCHAR(255) NOT NULL, last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, options_width DOUBLE PRECISION NOT NULL, options_height DOUBLE PRECISION NOT NULL, options_barcode_type VARCHAR(255) NOT NULL, options_picture_type VARCHAR(255) NOT NULL, options_supported_element VARCHAR(255) NOT NULL, options_additional_css CLOB NOT NULL, options_lines_mode VARCHAR(255) NOT NULL, options_lines CLOB NOT NULL)');
        $this->addSql('CREATE INDEX IDX_C93E9CF56DEDCEC2 ON label_profiles (id_preview_attachement)');
        $this->addSql('CREATE TABLE log (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, id_user INTEGER NOT NULL, datetime DATETIME NOT NULL, level TINYINT(4) NOT NULL, target_id INTEGER NOT NULL, target_type SMALLINT NOT NULL, extra CLOB NOT NULL --(DC2Type:json)
        , type SMALLINT NOT NULL)');
        $this->addSql('CREATE INDEX IDX_8F3F68C56B3CA4B ON log (id_user)');
        $this->addSql('CREATE TABLE parameters (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, symbol VARCHAR(255) NOT NULL, value_min DOUBLE PRECISION DEFAULT NULL, value_typical DOUBLE PRECISION DEFAULT NULL, value_max DOUBLE PRECISION DEFAULT NULL, unit VARCHAR(255) NOT NULL, value_text VARCHAR(255) NOT NULL, param_group VARCHAR(255) NOT NULL, name VARCHAR(255) NOT NULL, last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, type SMALLINT NOT NULL, element_id INTEGER NOT NULL)');
        $this->addSql('CREATE INDEX IDX_69348FE1F1F2A24 ON parameters (element_id)');
        $this->addSql('CREATE TABLE "categories" (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, parent_id INTEGER DEFAULT NULL, id_preview_attachement INTEGER DEFAULT NULL, partname_hint CLOB NOT NULL, partname_regex CLOB NOT NULL, disable_footprints BOOLEAN NOT NULL, disable_manufacturers BOOLEAN NOT NULL, disable_autodatasheets BOOLEAN NOT NULL, disable_properties BOOLEAN NOT NULL, default_description CLOB NOT NULL, default_comment CLOB NOT NULL, comment CLOB NOT NULL, not_selectable BOOLEAN NOT NULL, name VARCHAR(255) NOT NULL, last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL)');
        $this->addSql('CREATE INDEX IDX_3AF34668727ACA70 ON "categories" (parent_id)');
        $this->addSql('CREATE INDEX IDX_3AF346686DEDCEC2 ON "categories" (id_preview_attachement)');
        $this->addSql('CREATE TABLE "footprints" (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, parent_id INTEGER DEFAULT NULL, id_footprint_3d INTEGER DEFAULT NULL, id_preview_attachement INTEGER DEFAULT NULL, comment CLOB NOT NULL, not_selectable BOOLEAN NOT NULL, name VARCHAR(255) NOT NULL, last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL)');
        $this->addSql('CREATE INDEX IDX_A34D68A2727ACA70 ON "footprints" (parent_id)');
        $this->addSql('CREATE INDEX IDX_A34D68A232A38C34 ON "footprints" (id_footprint_3d)');
        $this->addSql('CREATE INDEX IDX_A34D68A26DEDCEC2 ON "footprints" (id_preview_attachement)');
        $this->addSql('CREATE TABLE "manufacturers" (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, parent_id INTEGER DEFAULT NULL, id_preview_attachement INTEGER DEFAULT NULL, address VARCHAR(255) NOT NULL, phone_number VARCHAR(255) NOT NULL, fax_number VARCHAR(255) NOT NULL, email_address VARCHAR(255) NOT NULL, website VARCHAR(255) NOT NULL, auto_product_url VARCHAR(255) NOT NULL, comment CLOB NOT NULL, not_selectable BOOLEAN NOT NULL, name VARCHAR(255) NOT NULL, last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL)');
        $this->addSql('CREATE INDEX IDX_94565B12727ACA70 ON "manufacturers" (parent_id)');
        $this->addSql('CREATE INDEX IDX_94565B126DEDCEC2 ON "manufacturers" (id_preview_attachement)');
        $this->addSql('CREATE TABLE "measurement_units" (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, parent_id INTEGER DEFAULT NULL, id_preview_attachement INTEGER DEFAULT NULL, unit VARCHAR(255) DEFAULT NULL, is_integer BOOLEAN NOT NULL, use_si_prefix BOOLEAN NOT NULL, comment CLOB NOT NULL, not_selectable BOOLEAN NOT NULL, name VARCHAR(255) NOT NULL, last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL)');
        $this->addSql('CREATE INDEX IDX_F5AF83CF727ACA70 ON "measurement_units" (parent_id)');
        $this->addSql('CREATE INDEX IDX_F5AF83CF6DEDCEC2 ON "measurement_units" (id_preview_attachement)');
        $this->addSql('CREATE TABLE "parts" (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, id_preview_attachement INTEGER DEFAULT NULL, id_category INTEGER NOT NULL, id_footprint INTEGER DEFAULT NULL, id_part_unit INTEGER DEFAULT NULL, id_manufacturer INTEGER DEFAULT NULL, order_orderdetails_id INTEGER DEFAULT NULL, datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, name VARCHAR(255) NOT NULL, last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, needs_review BOOLEAN NOT NULL, tags CLOB NOT NULL, mass DOUBLE PRECISION DEFAULT NULL, description CLOB NOT NULL, comment CLOB NOT NULL, visible BOOLEAN NOT NULL, favorite BOOLEAN NOT NULL, minamount DOUBLE PRECISION NOT NULL, manufacturer_product_url VARCHAR(255) NOT NULL, manufacturer_product_number VARCHAR(255) NOT NULL, manufacturing_status VARCHAR(255) DEFAULT NULL, order_quantity INTEGER NOT NULL, manual_order BOOLEAN NOT NULL)');
        $this->addSql('CREATE INDEX IDX_6940A7FE6DEDCEC2 ON "parts" (id_preview_attachement)');
        $this->addSql('CREATE INDEX IDX_6940A7FE5697F554 ON "parts" (id_category)');
        $this->addSql('CREATE INDEX IDX_6940A7FE7E371A10 ON "parts" (id_footprint)');
        $this->addSql('CREATE INDEX IDX_6940A7FE2626CEF9 ON "parts" (id_part_unit)');
        $this->addSql('CREATE INDEX IDX_6940A7FE1ECB93AE ON "parts" (id_manufacturer)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_6940A7FE81081E9B ON "parts" (order_orderdetails_id)');
        $this->addSql('CREATE TABLE part_lots (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, id_store_location INTEGER DEFAULT NULL, id_part INTEGER NOT NULL, description CLOB NOT NULL, comment CLOB NOT NULL, expiration_date DATETIME DEFAULT NULL, instock_unknown BOOLEAN NOT NULL, amount DOUBLE PRECISION NOT NULL, needs_refill BOOLEAN NOT NULL, last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL)');
        $this->addSql('CREATE INDEX IDX_EBC8F9435D8F4B37 ON part_lots (id_store_location)');
        $this->addSql('CREATE INDEX IDX_EBC8F943C22F6CC4 ON part_lots (id_part)');
        $this->addSql('CREATE TABLE "storelocations" (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, parent_id INTEGER DEFAULT NULL, storage_type_id INTEGER DEFAULT NULL, id_preview_attachement INTEGER DEFAULT NULL, is_full BOOLEAN NOT NULL, only_single_part BOOLEAN NOT NULL, limit_to_existing_parts BOOLEAN NOT NULL, comment CLOB NOT NULL, not_selectable BOOLEAN NOT NULL, name VARCHAR(255) NOT NULL, last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL)');
        $this->addSql('CREATE INDEX IDX_7517020727ACA70 ON "storelocations" (parent_id)');
        $this->addSql('CREATE INDEX IDX_7517020B270BFF1 ON "storelocations" (storage_type_id)');
        $this->addSql('CREATE INDEX IDX_75170206DEDCEC2 ON "storelocations" (id_preview_attachement)');
        $this->addSql('CREATE TABLE "suppliers" (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, parent_id INTEGER DEFAULT NULL, default_currency_id INTEGER DEFAULT NULL, id_preview_attachement INTEGER DEFAULT NULL, shipping_costs NUMERIC(11, 5) DEFAULT NULL --(DC2Type:big_decimal)
        , address VARCHAR(255) NOT NULL, phone_number VARCHAR(255) NOT NULL, fax_number VARCHAR(255) NOT NULL, email_address VARCHAR(255) NOT NULL, website VARCHAR(255) NOT NULL, auto_product_url VARCHAR(255) NOT NULL, comment CLOB NOT NULL, not_selectable BOOLEAN NOT NULL, name VARCHAR(255) NOT NULL, last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL)');
        $this->addSql('CREATE INDEX IDX_AC28B95C727ACA70 ON "suppliers" (parent_id)');
        $this->addSql('CREATE INDEX IDX_AC28B95CECD792C0 ON "suppliers" (default_currency_id)');
        $this->addSql('CREATE INDEX IDX_AC28B95C6DEDCEC2 ON "suppliers" (id_preview_attachement)');
        $this->addSql('CREATE TABLE currencies (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, parent_id INTEGER DEFAULT NULL, id_preview_attachement INTEGER DEFAULT NULL, exchange_rate NUMERIC(11, 5) DEFAULT NULL --(DC2Type:big_decimal)
        , iso_code VARCHAR(255) NOT NULL, comment CLOB NOT NULL, not_selectable BOOLEAN NOT NULL, name VARCHAR(255) NOT NULL, last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL)');
        $this->addSql('CREATE INDEX IDX_37C44693727ACA70 ON currencies (parent_id)');
        $this->addSql('CREATE INDEX IDX_37C446936DEDCEC2 ON currencies (id_preview_attachement)');
        $this->addSql('CREATE TABLE "orderdetails" (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, part_id INTEGER NOT NULL, id_supplier INTEGER DEFAULT NULL, supplierpartnr VARCHAR(255) NOT NULL, obsolete BOOLEAN NOT NULL, supplier_product_url VARCHAR(255) NOT NULL, last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL)');
        $this->addSql('CREATE INDEX IDX_489AFCDC4CE34BEC ON "orderdetails" (part_id)');
        $this->addSql('CREATE INDEX IDX_489AFCDCCBF180EB ON "orderdetails" (id_supplier)');
        $this->addSql('CREATE TABLE "pricedetails" (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, id_currency INTEGER DEFAULT NULL, orderdetails_id INTEGER NOT NULL, price NUMERIC(11, 5) NOT NULL --(DC2Type:big_decimal)
        , price_related_quantity DOUBLE PRECISION NOT NULL, min_discount_quantity DOUBLE PRECISION NOT NULL, manual_input BOOLEAN NOT NULL, last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL)');
        $this->addSql('CREATE INDEX IDX_C68C4459398D64AA ON "pricedetails" (id_currency)');
        $this->addSql('CREATE INDEX IDX_C68C44594A01DDC7 ON "pricedetails" (orderdetails_id)');
        $this->addSql('CREATE TABLE "groups" (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, parent_id INTEGER DEFAULT NULL, id_preview_attachement INTEGER DEFAULT NULL, enforce_2fa BOOLEAN NOT NULL, comment CLOB NOT NULL, not_selectable BOOLEAN NOT NULL, name VARCHAR(255) NOT NULL, last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, perms_system INTEGER NOT NULL, perms_groups INTEGER NOT NULL, perms_users INTEGER NOT NULL, perms_self INTEGER NOT NULL, perms_system_config INTEGER NOT NULL, perms_system_database INTEGER NOT NULL, perms_parts BIGINT NOT NULL, perms_parts_name SMALLINT NOT NULL, perms_parts_category SMALLINT NOT NULL, perms_parts_description SMALLINT NOT NULL, perms_parts_minamount SMALLINT NOT NULL, perms_parts_footprint SMALLINT NOT NULL, perms_parts_lots SMALLINT NOT NULL, perms_parts_tags SMALLINT NOT NULL, perms_parts_unit SMALLINT NOT NULL, perms_parts_mass SMALLINT NOT NULL, perms_parts_manufacturer SMALLINT NOT NULL, perms_parts_status SMALLINT NOT NULL, perms_parts_mpn SMALLINT NOT NULL, perms_parts_comment SMALLINT NOT NULL, perms_parts_order SMALLINT NOT NULL, perms_parts_orderdetails SMALLINT NOT NULL, perms_parts_prices SMALLINT NOT NULL, perms_parts_parameters SMALLINT NOT NULL, perms_parts_attachements SMALLINT NOT NULL, perms_devices INTEGER NOT NULL, perms_devices_parts INTEGER NOT NULL, perms_storelocations INTEGER NOT NULL, perms_footprints INTEGER NOT NULL, perms_categories INTEGER NOT NULL, perms_suppliers INTEGER NOT NULL, perms_manufacturers INTEGER NOT NULL, perms_attachement_types INTEGER NOT NULL, perms_currencies INTEGER NOT NULL, perms_measurement_units INTEGER NOT NULL, perms_tools INTEGER NOT NULL, perms_labels INTEGER NOT NULL)');
        $this->addSql('CREATE INDEX IDX_F06D3970727ACA70 ON "groups" (parent_id)');
        $this->addSql('CREATE INDEX IDX_F06D39706DEDCEC2 ON "groups" (id_preview_attachement)');
        $this->addSql('CREATE TABLE u2f_keys (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, user_id INTEGER DEFAULT NULL, key_handle VARCHAR(128) NOT NULL, public_key VARCHAR(255) NOT NULL, certificate CLOB NOT NULL, counter VARCHAR(255) NOT NULL, name VARCHAR(255) NOT NULL, last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL)');
        $this->addSql('CREATE INDEX IDX_4F4ADB4BA76ED395 ON u2f_keys (user_id)');
        $this->addSql('CREATE UNIQUE INDEX user_unique ON u2f_keys (user_id, key_handle)');
        $this->addSql('CREATE TABLE "users" (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, group_id INTEGER DEFAULT NULL, currency_id INTEGER DEFAULT NULL, id_preview_attachement INTEGER DEFAULT NULL, disabled BOOLEAN NOT NULL, config_theme VARCHAR(255) DEFAULT NULL, pw_reset_token VARCHAR(255) DEFAULT NULL, config_instock_comment_a CLOB NOT NULL, config_instock_comment_w CLOB NOT NULL, trusted_device_cookie_version INTEGER NOT NULL, backup_codes CLOB NOT NULL --(DC2Type:json)
        , google_authenticator_secret VARCHAR(255) DEFAULT NULL, config_timezone VARCHAR(255) DEFAULT NULL, config_language VARCHAR(255) DEFAULT NULL, email VARCHAR(255) DEFAULT NULL, department VARCHAR(255) DEFAULT NULL, last_name VARCHAR(255) DEFAULT NULL, first_name VARCHAR(255) DEFAULT NULL, need_pw_change BOOLEAN NOT NULL, password VARCHAR(255) DEFAULT NULL, name VARCHAR(180) NOT NULL, settings CLOB NOT NULL --(DC2Type:json)
        , backup_codes_generation_date DATETIME DEFAULT NULL, pw_reset_expires DATETIME DEFAULT NULL, last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, perms_system INTEGER NOT NULL, perms_groups INTEGER NOT NULL, perms_users INTEGER NOT NULL, perms_self INTEGER NOT NULL, perms_system_config INTEGER NOT NULL, perms_system_database INTEGER NOT NULL, perms_parts BIGINT NOT NULL, perms_parts_name SMALLINT NOT NULL, perms_parts_category SMALLINT NOT NULL, perms_parts_description SMALLINT NOT NULL, perms_parts_minamount SMALLINT NOT NULL, perms_parts_footprint SMALLINT NOT NULL, perms_parts_lots SMALLINT NOT NULL, perms_parts_tags SMALLINT NOT NULL, perms_parts_unit SMALLINT NOT NULL, perms_parts_mass SMALLINT NOT NULL, perms_parts_manufacturer SMALLINT NOT NULL, perms_parts_status SMALLINT NOT NULL, perms_parts_mpn SMALLINT NOT NULL, perms_parts_comment SMALLINT NOT NULL, perms_parts_order SMALLINT NOT NULL, perms_parts_orderdetails SMALLINT NOT NULL, perms_parts_prices SMALLINT NOT NULL, perms_parts_parameters SMALLINT NOT NULL, perms_parts_attachements SMALLINT NOT NULL, perms_devices INTEGER NOT NULL, perms_devices_parts INTEGER NOT NULL, perms_storelocations INTEGER NOT NULL, perms_footprints INTEGER NOT NULL, perms_categories INTEGER NOT NULL, perms_suppliers INTEGER NOT NULL, perms_manufacturers INTEGER NOT NULL, perms_attachement_types INTEGER NOT NULL, perms_currencies INTEGER NOT NULL, perms_measurement_units INTEGER NOT NULL, perms_tools INTEGER NOT NULL, perms_labels INTEGER NOT NULL)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_1483A5E95E237E06 ON "users" (name)');
        $this->addSql('CREATE INDEX IDX_1483A5E9FE54D947 ON "users" (group_id)');
        $this->addSql('CREATE INDEX IDX_1483A5E938248176 ON "users" (currency_id)');
        $this->addSql('CREATE INDEX IDX_1483A5E96DEDCEC2 ON "users" (id_preview_attachement)');

        $sql = <<<EOD
        INSERT INTO `groups` (`id`, `parent_id`, `comment`, `not_selectable`, `name`, `last_modified`, `datetime_added`, `perms_system`, `perms_groups`, `perms_users`, `perms_self`, `perms_system_config`, `perms_system_database`, `perms_parts`, `perms_parts_name`, `perms_parts_description`, `perms_parts_footprint`, `perms_parts_manufacturer`, `perms_parts_comment`, `perms_parts_order`, `perms_parts_orderdetails`, `perms_parts_prices`, `perms_parts_attachements`, `perms_devices`, `perms_devices_parts`, `perms_storelocations`, `perms_footprints`, `perms_categories`, `perms_suppliers`, `perms_manufacturers`, `perms_attachement_types`, `perms_tools`, `perms_labels`, `perms_parts_category`, `perms_parts_minamount`, `perms_parts_lots`, `perms_parts_tags`, `perms_parts_unit`, `perms_parts_mass`, `perms_parts_status`, `perms_parts_mpn`, `perms_currencies`, `perms_measurement_units`, `id_preview_attachement`, `enforce_2fa`, `perms_parts_parameters`) VALUES
            (1, NULL, 'Users of this group can do everything: Read, Write and Administrative actions.', 0, 'admins', '2020-06-13 23:39:26', '2020-06-13 23:39:26', 21, 1365, 87381, 85, 85, 21, 1431655765, 5, 5, 5, 5, 5, 5, 341, 341, 341, 5461, 325, 5461, 5461, 5461, 5461, 5461, 1365, 1365, 85, 5, 5, 85, 5, 5, 5, 5, 5, 5461, 5461, NULL, 0, 341),
            (2, NULL, 'Users of this group can only read informations, use tools, and do not have access to administrative tools.', 0, 'readonly', '2020-06-13 23:39:26', '2020-06-13 23:39:26', 42, 2730, 174762, 154, 170, 42, -1516939607, 9, 9, 9, 9, 9, 9, 681, 681, 681, 1705, 649, 1705, 1705, 1705, 1705, 1705, 681, 1366, 165, 9, 9, 169, 9, 9, 9, 9, 9, 9897, 9897, NULL, 0, 681),
            (3, NULL, 'Users of this group, can edit part informations, create new ones, etc. but are not allowed to use administrative tools. (But can read current configuration, and see Server status)', 0, 'users', '2020-06-13 23:39:26', '2020-06-13 23:39:26', 42, 2730, 109226, 89, 105, 41, 1431655765, 5, 5, 5, 5, 5, 5, 341, 341, 341, 5461, 325, 5461, 5461, 5461, 5461, 5461, 1365, 1365, 85, 5, 5, 85, 5, 5, 5, 5, 5, 5461, 5461, NULL, 0, 341);
EOD;
        $this->addSql($sql);

        $admin_pw = $this->getInitalAdminPW();

        $sql = <<<EOD
        INSERT INTO `users` (`id`, `group_id`, `name`, `password`, `need_pw_change`, `first_name`, `last_name`, `department`, `email`, `config_language`, `config_timezone`, `config_theme`, `config_instock_comment_w`, `config_instock_comment_a`, `last_modified`, `datetime_added`, `perms_system`, `perms_groups`, `perms_users`, `perms_self`, `perms_system_config`, `perms_system_database`, `perms_parts`, `perms_parts_name`, `perms_parts_description`, `perms_parts_footprint`, `perms_parts_manufacturer`, `perms_parts_comment`, `perms_parts_order`, `perms_parts_orderdetails`, `perms_parts_prices`, `perms_parts_attachements`, `perms_devices`, `perms_devices_parts`, `perms_storelocations`, `perms_footprints`, `perms_categories`, `perms_suppliers`, `perms_manufacturers`, `perms_attachement_types`, `perms_tools`, `perms_labels`, `currency_id`, `settings`, `perms_parts_category`, `perms_parts_minamount`, `perms_parts_lots`, `perms_parts_tags`, `perms_parts_unit`, `perms_parts_mass`, `perms_parts_status`, `perms_parts_mpn`, `perms_currencies`, `perms_measurement_units`, `id_preview_attachement`, `pw_reset_token`, `pw_reset_expires`, `disabled`, `google_authenticator_secret`, `backup_codes`, `backup_codes_generation_date`, `trusted_device_cookie_version`, `perms_parts_parameters`) VALUES
            (1, 2, 'anonymous', '', 0, '', '', '', '', NULL, NULL, NULL, '', '', '2020-06-13 23:39:26', '2020-06-13 23:39:26', 21844, 20480, 0, 0, 0, 0, 0, 21840, 21840, 21840, 21840, 21840, 21840, 21520, 21520, 21520, 20480, 21520, 20480, 20480, 20480, 20480, 20480, 21504, 20480, 0, NULL, '', 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, NULL, NULL, 0, NULL, '', NULL, 0, 0),
        (2, 1, 'admin', '{$admin_pw}', 1, '', '', '', '', NULL, NULL, NULL, '', '', '2020-06-13 23:39:26', '2020-06-13 23:39:26', 21845, 21845, 21845, 21, 85, 21, 349525, 21845, 21845, 21845, 21845, 21845, 21845, 21845, 21845, 21845, 21845, 21845, 21845, 21845, 21845, 21845, 21845, 21845, 21845, 0, NULL, '', 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL, NULL, NULL, 0, NULL, '', NULL, 0, 0);
EOD;
        $this->addSql($sql);

        $this->printPermissionUpdateMessage();
    }

    public function sqLiteDown(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'sqlite', 'Migration can only be executed safely on \'sqlite\'.');

        $this->addSql('DROP TABLE "attachments"');
        $this->addSql('DROP TABLE "attachment_types"');
        $this->addSql('DROP TABLE "devices"');
        $this->addSql('DROP TABLE "device_parts"');
        $this->addSql('DROP TABLE label_profiles');
        $this->addSql('DROP TABLE log');
        $this->addSql('DROP TABLE parameters');
        $this->addSql('DROP TABLE "categories"');
        $this->addSql('DROP TABLE "footprints"');
        $this->addSql('DROP TABLE "manufacturers"');
        $this->addSql('DROP TABLE "measurement_units"');
        $this->addSql('DROP TABLE "parts"');
        $this->addSql('DROP TABLE part_lots');
        $this->addSql('DROP TABLE "storelocations"');
        $this->addSql('DROP TABLE "suppliers"');
        $this->addSql('DROP TABLE currencies');
        $this->addSql('DROP TABLE "orderdetails"');
        $this->addSql('DROP TABLE "pricedetails"');
        $this->addSql('DROP TABLE "groups"');
        $this->addSql('DROP TABLE u2f_keys');
        $this->addSql('DROP TABLE "users"');
    }
}
