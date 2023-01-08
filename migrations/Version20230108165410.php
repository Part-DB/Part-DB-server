<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Migration\AbstractMultiPlatformMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20230108165410 extends AbstractMultiPlatformMigration
{
    public function getDescription(): string
    {
        return 'Create schema for project system. For SQLite indices and foreign keys are created for the whole DB.';
    }

    public function mySQLUp(Schema $schema): void
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

        //On old DBs migrated from the legacy Part-DB version the key devices_parent_id_fk is still existing
        if ($this->getOldDBVersion() === 99) {
            $this->addSql('ALTER TABLE projects DROP FOREIGN KEY devices_parent_id_fk');
        }
        $this->addSql('ALTER TABLE projects ADD status VARCHAR(64) DEFAULT NULL, ADD description LONGTEXT NOT NULL');
        $this->addSql('CREATE INDEX IDX_5C93B3A4727ACA70 ON projects (parent_id)');
        $this->addSql('DROP INDEX idx_11074e9a727aca70 ON projects');
        $this->addSql('CREATE INDEX IDX_5C93B3A46DEDCEC2 ON projects (id_preview_attachement)');
        $this->addSql('DROP INDEX idx_11074e9a6dedcec2 ON projects');
        $this->addSql('ALTER TABLE projects ADD CONSTRAINT FK_11074E9A6DEDCEC2 FOREIGN KEY (id_preview_attachement) REFERENCES attachments (id)');
        $this->addSql('ALTER TABLE projects ADD CONSTRAINT FK_5C93B3A4727ACA70 FOREIGN KEY (parent_id) REFERENCES projects (id)');
    }

    public function mySQLDown(Schema $schema): void
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

    public function sqLiteUp(Schema $schema): void
    {
        //Rename the table from devices to projects
        $this->addSql('ALTER TABLE devices RENAME TO projects');
        $this->addSql('ALTER TABLE device_parts RENAME TO project_bom_entries');

        $this->addSql('CREATE TEMPORARY TABLE __temp__currencies AS SELECT id, parent_id, id_preview_attachement, exchange_rate, iso_code, comment, not_selectable, name, last_modified, datetime_added FROM currencies');
        $this->addSql('DROP TABLE currencies');
        $this->addSql('CREATE TABLE currencies (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, parent_id INTEGER DEFAULT NULL, id_preview_attachement INTEGER DEFAULT NULL, exchange_rate NUMERIC(11, 5) DEFAULT NULL --(DC2Type:big_decimal)
        , iso_code VARCHAR(255) NOT NULL, comment CLOB NOT NULL, not_selectable BOOLEAN NOT NULL, name VARCHAR(255) NOT NULL, last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, CONSTRAINT FK_37C44693727ACA70 FOREIGN KEY (parent_id) REFERENCES currencies (id) ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_37C446936DEDCEC2 FOREIGN KEY (id_preview_attachement) REFERENCES attachments (id) ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO currencies (id, parent_id, id_preview_attachement, exchange_rate, iso_code, comment, not_selectable, name, last_modified, datetime_added) SELECT id, parent_id, id_preview_attachement, exchange_rate, iso_code, comment, not_selectable, name, last_modified, datetime_added FROM __temp__currencies');
        $this->addSql('DROP TABLE __temp__currencies');
        $this->addSql('CREATE INDEX currency_idx_parent_name ON currencies (parent_id, name)');
        $this->addSql('CREATE INDEX currency_idx_name ON currencies (name)');
        $this->addSql('CREATE INDEX IDX_37C44693727ACA70 ON currencies (parent_id)');
        $this->addSql('CREATE INDEX IDX_37C446936DEDCEC2 ON currencies (id_preview_attachement)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__groups AS SELECT id, parent_id, id_preview_attachement, enforce_2fa, comment, not_selectable, name, last_modified, datetime_added, permissions_data FROM groups');
        $this->addSql('DROP TABLE groups');
        $this->addSql('CREATE TABLE groups (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, parent_id INTEGER DEFAULT NULL, id_preview_attachement INTEGER DEFAULT NULL, enforce_2fa BOOLEAN NOT NULL, comment CLOB NOT NULL, not_selectable BOOLEAN NOT NULL, name VARCHAR(255) NOT NULL, last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, permissions_data CLOB DEFAULT \'[]\' NOT NULL --(DC2Type:json)
        , CONSTRAINT FK_F06D3970727ACA70 FOREIGN KEY (parent_id) REFERENCES groups (id) ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_F06D39706DEDCEC2 FOREIGN KEY (id_preview_attachement) REFERENCES attachments (id) ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO groups (id, parent_id, id_preview_attachement, enforce_2fa, comment, not_selectable, name, last_modified, datetime_added, permissions_data) SELECT id, parent_id, id_preview_attachement, enforce_2fa, comment, not_selectable, name, last_modified, datetime_added, permissions_data FROM __temp__groups');
        $this->addSql('DROP TABLE __temp__groups');
        $this->addSql('CREATE INDEX IDX_F06D39706DEDCEC2 ON groups (id_preview_attachement)');
        $this->addSql('CREATE INDEX IDX_F06D3970727ACA70 ON groups (parent_id)');
        $this->addSql('CREATE INDEX group_idx_name ON groups (name)');
        $this->addSql('CREATE INDEX group_idx_parent_name ON groups (parent_id, name)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__log AS SELECT id, id_user, datetime, level, target_id, target_type, extra, type, username FROM log');
        $this->addSql('DROP TABLE log');
        $this->addSql('CREATE TABLE log (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, id_user INTEGER DEFAULT NULL, datetime DATETIME NOT NULL, level TINYINT(4) NOT NULL, target_id INTEGER NOT NULL, target_type SMALLINT NOT NULL, extra CLOB NOT NULL --(DC2Type:json)
        , type SMALLINT NOT NULL, username VARCHAR(255) NOT NULL, CONSTRAINT FK_8F3F68C56B3CA4B FOREIGN KEY (id_user) REFERENCES users (id) ON UPDATE NO ACTION ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO log (id, id_user, datetime, level, target_id, target_type, extra, type, username) SELECT id, id_user, datetime, level, target_id, target_type, extra, type, username FROM __temp__log');
        $this->addSql('DROP TABLE __temp__log');
        $this->addSql('CREATE INDEX IDX_8F3F68C56B3CA4B ON log (id_user)');
        $this->addSql('CREATE INDEX log_idx_type ON log (type)');
        $this->addSql('CREATE INDEX log_idx_type_target ON log (type, target_type, target_id)');
        $this->addSql('CREATE INDEX log_idx_datetime ON log (datetime)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__parts AS SELECT id, id_preview_attachement, id_category, id_footprint, id_part_unit, id_manufacturer, order_orderdetails_id, datetime_added, name, last_modified, needs_review, tags, mass, description, comment, visible, favorite, minamount, manufacturer_product_url, manufacturer_product_number, manufacturing_status, order_quantity, manual_order, ipn FROM parts');
        $this->addSql('DROP TABLE parts');
        $this->addSql('CREATE TABLE parts (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, id_preview_attachement INTEGER DEFAULT NULL, id_category INTEGER NOT NULL, id_footprint INTEGER DEFAULT NULL, id_part_unit INTEGER DEFAULT NULL, id_manufacturer INTEGER DEFAULT NULL, order_orderdetails_id INTEGER DEFAULT NULL, built_project_id INTEGER DEFAULT NULL, datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, name VARCHAR(255) NOT NULL, last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, needs_review BOOLEAN NOT NULL, tags CLOB NOT NULL, mass DOUBLE PRECISION DEFAULT NULL, description CLOB NOT NULL, comment CLOB NOT NULL, visible BOOLEAN NOT NULL, favorite BOOLEAN NOT NULL, minamount DOUBLE PRECISION NOT NULL, manufacturer_product_url VARCHAR(255) NOT NULL, manufacturer_product_number VARCHAR(255) NOT NULL, manufacturing_status VARCHAR(255) DEFAULT NULL, order_quantity INTEGER NOT NULL, manual_order BOOLEAN NOT NULL, ipn VARCHAR(100) DEFAULT NULL, CONSTRAINT FK_6940A7FE6DEDCEC2 FOREIGN KEY (id_preview_attachement) REFERENCES attachments (id) ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_6940A7FE5697F554 FOREIGN KEY (id_category) REFERENCES categories (id) ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_6940A7FE7E371A10 FOREIGN KEY (id_footprint) REFERENCES footprints (id) ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_6940A7FE2626CEF9 FOREIGN KEY (id_part_unit) REFERENCES measurement_units (id) ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_6940A7FE1ECB93AE FOREIGN KEY (id_manufacturer) REFERENCES manufacturers (id) ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_6940A7FE81081E9B FOREIGN KEY (order_orderdetails_id) REFERENCES orderdetails (id) ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_6940A7FEE8AE70D9 FOREIGN KEY (built_project_id) REFERENCES projects (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO parts (id, id_preview_attachement, id_category, id_footprint, id_part_unit, id_manufacturer, order_orderdetails_id, datetime_added, name, last_modified, needs_review, tags, mass, description, comment, visible, favorite, minamount, manufacturer_product_url, manufacturer_product_number, manufacturing_status, order_quantity, manual_order, ipn) SELECT id, id_preview_attachement, id_category, id_footprint, id_part_unit, id_manufacturer, order_orderdetails_id, datetime_added, name, last_modified, needs_review, tags, mass, description, comment, visible, favorite, minamount, manufacturer_product_url, manufacturer_product_number, manufacturing_status, order_quantity, manual_order, ipn FROM __temp__parts');
        $this->addSql('DROP TABLE __temp__parts');
        $this->addSql('CREATE INDEX parts_idx_ipn ON parts (ipn)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_6940A7FE3D721C14 ON parts (ipn)');
        $this->addSql('CREATE INDEX parts_idx_name ON parts (name)');
        $this->addSql('CREATE INDEX parts_idx_datet_name_last_id_needs ON parts (datetime_added, name, last_modified, id, needs_review)');
        $this->addSql('CREATE INDEX IDX_6940A7FE6DEDCEC2 ON parts (id_preview_attachement)');
        $this->addSql('CREATE INDEX IDX_6940A7FE5697F554 ON parts (id_category)');
        $this->addSql('CREATE INDEX IDX_6940A7FE7E371A10 ON parts (id_footprint)');
        $this->addSql('CREATE INDEX IDX_6940A7FE2626CEF9 ON parts (id_part_unit)');
        $this->addSql('CREATE INDEX IDX_6940A7FE1ECB93AE ON parts (id_manufacturer)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_6940A7FE81081E9B ON parts (order_orderdetails_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_6940A7FEE8AE70D9 ON parts (built_project_id)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__pricedetails AS SELECT id, id_currency, orderdetails_id, price, price_related_quantity, min_discount_quantity, manual_input, last_modified, datetime_added FROM pricedetails');
        $this->addSql('DROP TABLE pricedetails');
        $this->addSql('CREATE TABLE pricedetails (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, id_currency INTEGER DEFAULT NULL, orderdetails_id INTEGER NOT NULL, price NUMERIC(11, 5) NOT NULL --(DC2Type:big_decimal)
        , price_related_quantity DOUBLE PRECISION NOT NULL, min_discount_quantity DOUBLE PRECISION NOT NULL, manual_input BOOLEAN NOT NULL, last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, CONSTRAINT FK_C68C4459398D64AA FOREIGN KEY (id_currency) REFERENCES currencies (id) ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_C68C44594A01DDC7 FOREIGN KEY (orderdetails_id) REFERENCES orderdetails (id) ON UPDATE NO ACTION ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO pricedetails (id, id_currency, orderdetails_id, price, price_related_quantity, min_discount_quantity, manual_input, last_modified, datetime_added) SELECT id, id_currency, orderdetails_id, price, price_related_quantity, min_discount_quantity, manual_input, last_modified, datetime_added FROM __temp__pricedetails');
        $this->addSql('DROP TABLE __temp__pricedetails');
        $this->addSql('CREATE INDEX pricedetails_idx_min_discount_price_qty ON pricedetails (min_discount_quantity, price_related_quantity)');
        $this->addSql('CREATE INDEX pricedetails_idx_min_discount ON pricedetails (min_discount_quantity)');
        $this->addSql('CREATE INDEX IDX_C68C4459398D64AA ON pricedetails (id_currency)');
        $this->addSql('CREATE INDEX IDX_C68C44594A01DDC7 ON pricedetails (orderdetails_id)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__project_bom_entries AS SELECT id, id_device, id_part, quantity, mountnames FROM project_bom_entries');
        $this->addSql('DROP TABLE project_bom_entries');
        $this->addSql('CREATE TABLE project_bom_entries (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, id_device INTEGER DEFAULT NULL, id_part INTEGER DEFAULT NULL, price_currency_id INTEGER DEFAULT NULL, quantity DOUBLE PRECISION NOT NULL, mountnames CLOB NOT NULL, name VARCHAR(255) DEFAULT NULL, comment CLOB NOT NULL, price NUMERIC(11, 5) DEFAULT NULL --(DC2Type:big_decimal)
        , last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, CONSTRAINT FK_AFC547992F180363 FOREIGN KEY (id_device) REFERENCES projects (id) ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_AFC54799C22F6CC4 FOREIGN KEY (id_part) REFERENCES parts (id) ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_1AA2DD313FFDCD60 FOREIGN KEY (price_currency_id) REFERENCES currencies (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO project_bom_entries (id, id_device, id_part, quantity, mountnames) SELECT id, id_device, id_part, quantity, mountnames FROM __temp__project_bom_entries');
        $this->addSql('DROP TABLE __temp__project_bom_entries');
        $this->addSql('CREATE INDEX IDX_1AA2DD313FFDCD60 ON project_bom_entries (price_currency_id)');
        $this->addSql('CREATE INDEX IDX_1AA2DD312F180363 ON project_bom_entries (id_device)');
        $this->addSql('CREATE INDEX IDX_1AA2DD31C22F6CC4 ON project_bom_entries (id_part)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__projects AS SELECT id, parent_id, id_preview_attachement, order_quantity, order_only_missing_parts, comment, not_selectable, name, last_modified, datetime_added FROM projects');
        $this->addSql('DROP TABLE projects');
        $this->addSql('CREATE TABLE projects (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, parent_id INTEGER DEFAULT NULL, id_preview_attachement INTEGER DEFAULT NULL, order_quantity INTEGER NOT NULL, order_only_missing_parts BOOLEAN NOT NULL, comment CLOB NOT NULL, not_selectable BOOLEAN NOT NULL, name VARCHAR(255) NOT NULL, last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, status VARCHAR(64) DEFAULT NULL, description CLOB NOT NULL DEFAULT "", CONSTRAINT FK_11074E9A727ACA70 FOREIGN KEY (parent_id) REFERENCES projects (id) ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_11074E9A6DEDCEC2 FOREIGN KEY (id_preview_attachement) REFERENCES attachments (id) ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO projects (id, parent_id, id_preview_attachement, order_quantity, order_only_missing_parts, comment, not_selectable, name, last_modified, datetime_added) SELECT id, parent_id, id_preview_attachement, order_quantity, order_only_missing_parts, comment, not_selectable, name, last_modified, datetime_added FROM __temp__projects');
        $this->addSql('DROP TABLE __temp__projects');
        $this->addSql('CREATE INDEX IDX_5C93B3A4727ACA70 ON projects (parent_id)');
        $this->addSql('CREATE INDEX IDX_5C93B3A46DEDCEC2 ON projects (id_preview_attachement)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__suppliers AS SELECT id, parent_id, default_currency_id, id_preview_attachement, shipping_costs, address, phone_number, fax_number, email_address, website, auto_product_url, comment, not_selectable, name, last_modified, datetime_added FROM suppliers');
        $this->addSql('DROP TABLE suppliers');
        $this->addSql('CREATE TABLE suppliers (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, parent_id INTEGER DEFAULT NULL, default_currency_id INTEGER DEFAULT NULL, id_preview_attachement INTEGER DEFAULT NULL, shipping_costs NUMERIC(11, 5) DEFAULT NULL --(DC2Type:big_decimal)
        , address VARCHAR(255) NOT NULL, phone_number VARCHAR(255) NOT NULL, fax_number VARCHAR(255) NOT NULL, email_address VARCHAR(255) NOT NULL, website VARCHAR(255) NOT NULL, auto_product_url VARCHAR(255) NOT NULL, comment CLOB NOT NULL, not_selectable BOOLEAN NOT NULL, name VARCHAR(255) NOT NULL, last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, CONSTRAINT FK_AC28B95C727ACA70 FOREIGN KEY (parent_id) REFERENCES suppliers (id) ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_AC28B95CECD792C0 FOREIGN KEY (default_currency_id) REFERENCES currencies (id) ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_AC28B95C6DEDCEC2 FOREIGN KEY (id_preview_attachement) REFERENCES attachments (id) ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO suppliers (id, parent_id, default_currency_id, id_preview_attachement, shipping_costs, address, phone_number, fax_number, email_address, website, auto_product_url, comment, not_selectable, name, last_modified, datetime_added) SELECT id, parent_id, default_currency_id, id_preview_attachement, shipping_costs, address, phone_number, fax_number, email_address, website, auto_product_url, comment, not_selectable, name, last_modified, datetime_added FROM __temp__suppliers');
        $this->addSql('DROP TABLE __temp__suppliers');
        $this->addSql('CREATE INDEX supplier_idx_parent_name ON suppliers (parent_id, name)');
        $this->addSql('CREATE INDEX supplier_idx_name ON suppliers (name)');
        $this->addSql('CREATE INDEX IDX_AC28B95C727ACA70 ON suppliers (parent_id)');
        $this->addSql('CREATE INDEX IDX_AC28B95CECD792C0 ON suppliers (default_currency_id)');
        $this->addSql('CREATE INDEX IDX_AC28B95C6DEDCEC2 ON suppliers (id_preview_attachement)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__users AS SELECT id, group_id, currency_id, id_preview_attachement, disabled, config_theme, pw_reset_token, config_instock_comment_a, config_instock_comment_w, trusted_device_cookie_version, backup_codes, google_authenticator_secret, config_timezone, config_language, email, department, last_name, first_name, need_pw_change, password, name, settings, backup_codes_generation_date, pw_reset_expires, last_modified, datetime_added, permissions_data FROM users');
        $this->addSql('DROP TABLE users');
        $this->addSql('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, group_id INTEGER DEFAULT NULL, currency_id INTEGER DEFAULT NULL, id_preview_attachement INTEGER DEFAULT NULL, disabled BOOLEAN NOT NULL, config_theme VARCHAR(255) DEFAULT NULL, pw_reset_token VARCHAR(255) DEFAULT NULL, config_instock_comment_a CLOB NOT NULL, config_instock_comment_w CLOB NOT NULL, trusted_device_cookie_version INTEGER NOT NULL, backup_codes CLOB NOT NULL --(DC2Type:json)
        , google_authenticator_secret VARCHAR(255) DEFAULT NULL, config_timezone VARCHAR(255) DEFAULT NULL, config_language VARCHAR(255) DEFAULT NULL, email VARCHAR(255) DEFAULT NULL, department VARCHAR(255) DEFAULT NULL, last_name VARCHAR(255) DEFAULT NULL, first_name VARCHAR(255) DEFAULT NULL, need_pw_change BOOLEAN NOT NULL, password VARCHAR(255) DEFAULT NULL, name VARCHAR(180) NOT NULL, settings CLOB NOT NULL --(DC2Type:json)
        , backup_codes_generation_date DATETIME DEFAULT NULL, pw_reset_expires DATETIME DEFAULT NULL, last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, permissions_data CLOB DEFAULT \'[]\' NOT NULL --(DC2Type:json)
        , CONSTRAINT FK_1483A5E9FE54D947 FOREIGN KEY (group_id) REFERENCES groups (id) ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_1483A5E938248176 FOREIGN KEY (currency_id) REFERENCES currencies (id) ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_1483A5E96DEDCEC2 FOREIGN KEY (id_preview_attachement) REFERENCES attachments (id) ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO users (id, group_id, currency_id, id_preview_attachement, disabled, config_theme, pw_reset_token, config_instock_comment_a, config_instock_comment_w, trusted_device_cookie_version, backup_codes, google_authenticator_secret, config_timezone, config_language, email, department, last_name, first_name, need_pw_change, password, name, settings, backup_codes_generation_date, pw_reset_expires, last_modified, datetime_added, permissions_data) SELECT id, group_id, currency_id, id_preview_attachement, disabled, config_theme, pw_reset_token, config_instock_comment_a, config_instock_comment_w, trusted_device_cookie_version, backup_codes, google_authenticator_secret, config_timezone, config_language, email, department, last_name, first_name, need_pw_change, password, name, settings, backup_codes_generation_date, pw_reset_expires, last_modified, datetime_added, permissions_data FROM __temp__users');
        $this->addSql('DROP TABLE __temp__users');
        $this->addSql('CREATE INDEX IDX_1483A5E96DEDCEC2 ON users (id_preview_attachement)');
        $this->addSql('CREATE INDEX IDX_1483A5E938248176 ON users (currency_id)');
        $this->addSql('CREATE INDEX IDX_1483A5E9FE54D947 ON users (group_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_1483A5E95E237E06 ON users (name)');
        $this->addSql('CREATE INDEX user_idx_username ON users (name)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__webauthn_keys AS SELECT id, user_id, public_key_credential_id, type, transports, attestation_type, trust_path, aaguid, credential_public_key, user_handle, counter, name, last_modified, datetime_added FROM webauthn_keys');
        $this->addSql('DROP TABLE webauthn_keys');
        $this->addSql('CREATE TABLE webauthn_keys (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, user_id INTEGER DEFAULT NULL, public_key_credential_id CLOB NOT NULL --(DC2Type:base64)
        , type VARCHAR(255) NOT NULL, transports CLOB NOT NULL --(DC2Type:array)
        , attestation_type VARCHAR(255) NOT NULL, trust_path CLOB NOT NULL --(DC2Type:trust_path)
        , aaguid CLOB NOT NULL --(DC2Type:aaguid)
        , credential_public_key CLOB NOT NULL --(DC2Type:base64)
        , user_handle VARCHAR(255) NOT NULL, counter INTEGER NOT NULL, name VARCHAR(255) NOT NULL, last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, CONSTRAINT FK_799FD143A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO webauthn_keys (id, user_id, public_key_credential_id, type, transports, attestation_type, trust_path, aaguid, credential_public_key, user_handle, counter, name, last_modified, datetime_added) SELECT id, user_id, public_key_credential_id, type, transports, attestation_type, trust_path, aaguid, credential_public_key, user_handle, counter, name, last_modified, datetime_added FROM __temp__webauthn_keys');
        $this->addSql('DROP TABLE __temp__webauthn_keys');
        $this->addSql('CREATE INDEX IDX_799FD143A76ED395 ON webauthn_keys (user_id)');
    }

    public function sqLiteDown(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TEMPORARY TABLE __temp__currencies AS SELECT id, parent_id, id_preview_attachement, exchange_rate, iso_code, comment, not_selectable, name, last_modified, datetime_added FROM currencies');
        $this->addSql('DROP TABLE currencies');
        $this->addSql('CREATE TABLE currencies (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, parent_id INTEGER DEFAULT NULL, id_preview_attachement INTEGER DEFAULT NULL, exchange_rate NUMERIC(11, 5) DEFAULT NULL --
(DC2Type:big_decimal)
        , iso_code VARCHAR(255) NOT NULL, comment CLOB NOT NULL, not_selectable BOOLEAN NOT NULL, name VARCHAR(255) NOT NULL, last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, CONSTRAINT FK_37C44693727ACA70 FOREIGN KEY (parent_id) REFERENCES currencies (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_37C446936DEDCEC2 FOREIGN KEY (id_preview_attachement) REFERENCES "attachments" (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO currencies (id, parent_id, id_preview_attachement, exchange_rate, iso_code, comment, not_selectable, name, last_modified, datetime_added) SELECT id, parent_id, id_preview_attachement, exchange_rate, iso_code, comment, not_selectable, name, last_modified, datetime_added FROM __temp__currencies');
        $this->addSql('DROP TABLE __temp__currencies');
        $this->addSql('CREATE INDEX IDX_37C44693727ACA70 ON currencies (parent_id)');
        $this->addSql('CREATE INDEX IDX_37C446936DEDCEC2 ON currencies (id_preview_attachement)');
        $this->addSql('CREATE INDEX currency_idx_name ON currencies (name)');
        $this->addSql('CREATE INDEX currency_idx_parent_name ON currencies (parent_id, name)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__groups AS SELECT id, parent_id, id_preview_attachement, enforce_2fa, comment, not_selectable, name, last_modified, datetime_added, permissions_data FROM "groups"');
        $this->addSql('DROP TABLE "groups"');
        $this->addSql('CREATE TABLE "groups" (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, parent_id INTEGER DEFAULT NULL, id_preview_attachement INTEGER DEFAULT NULL, enforce_2fa BOOLEAN NOT NULL, comment CLOB NOT NULL, not_selectable BOOLEAN NOT NULL, name VARCHAR(255) NOT NULL, last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, permissions_data CLOB DEFAULT \'[]\' NOT NULL --
(DC2Type:json)
        , CONSTRAINT FK_F06D3970727ACA70 FOREIGN KEY (parent_id) REFERENCES "groups" (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_F06D39706DEDCEC2 FOREIGN KEY (id_preview_attachement) REFERENCES "attachments" (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO "groups" (id, parent_id, id_preview_attachement, enforce_2fa, comment, not_selectable, name, last_modified, datetime_added, permissions_data) SELECT id, parent_id, id_preview_attachement, enforce_2fa, comment, not_selectable, name, last_modified, datetime_added, permissions_data FROM __temp__groups');
        $this->addSql('DROP TABLE __temp__groups');
        $this->addSql('CREATE INDEX IDX_F06D3970727ACA70 ON "groups" (parent_id)');
        $this->addSql('CREATE INDEX IDX_F06D39706DEDCEC2 ON "groups" (id_preview_attachement)');
        $this->addSql('CREATE INDEX group_idx_name ON "groups" (name)');
        $this->addSql('CREATE INDEX group_idx_parent_name ON "groups" (parent_id, name)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__log AS SELECT id, id_user, username, datetime, level, target_id, target_type, extra, type FROM log');
        $this->addSql('DROP TABLE log');
        $this->addSql('CREATE TABLE log (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, id_user INTEGER DEFAULT NULL, username VARCHAR(255) DEFAULT \'""\' NOT NULL, datetime DATETIME NOT NULL, level BOOLEAN NOT NULL, target_id INTEGER NOT NULL, target_type SMALLINT NOT NULL, extra CLOB NOT NULL --(DC2Type:json)
        , type SMALLINT NOT NULL, CONSTRAINT FK_8F3F68C56B3CA4B FOREIGN KEY (id_user) REFERENCES "users" (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO log (id, id_user, username, datetime, level, target_id, target_type, extra, type) SELECT id, id_user, username, datetime, level, target_id, target_type, extra, type FROM __temp__log');
        $this->addSql('DROP TABLE __temp__log');
        $this->addSql('CREATE INDEX IDX_8F3F68C56B3CA4B ON log (id_user)');
        $this->addSql('CREATE INDEX log_idx_type ON log (type)');
        $this->addSql('CREATE INDEX log_idx_type_target ON log (type, target_type, target_id)');
        $this->addSql('CREATE INDEX log_idx_datetime ON log (datetime)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__parts AS SELECT id, id_preview_attachement, id_category, id_footprint, id_part_unit, id_manufacturer, order_orderdetails_id, datetime_added, name, last_modified, needs_review, tags, mass, ipn, description, comment, visible, favorite, minamount, manufacturer_product_url, manufacturer_product_number, manufacturing_status, order_quantity, manual_order FROM "parts"');
        $this->addSql('DROP TABLE "parts"');
        $this->addSql('CREATE TABLE "parts" (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, id_preview_attachement INTEGER DEFAULT NULL, id_category INTEGER NOT NULL, id_footprint INTEGER DEFAULT NULL, id_part_unit INTEGER DEFAULT NULL, id_manufacturer INTEGER DEFAULT NULL, order_orderdetails_id INTEGER DEFAULT NULL, datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, name VARCHAR(255) NOT NULL, last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, needs_review BOOLEAN NOT NULL, tags CLOB NOT NULL, mass DOUBLE PRECISION DEFAULT NULL, ipn VARCHAR(100) DEFAULT NULL, description CLOB NOT NULL, comment CLOB NOT NULL, visible BOOLEAN NOT NULL, favorite BOOLEAN NOT NULL, minamount DOUBLE PRECISION NOT NULL, manufacturer_product_url VARCHAR(255) NOT NULL, manufacturer_product_number VARCHAR(255) NOT NULL, manufacturing_status VARCHAR(255) DEFAULT NULL, order_quantity INTEGER NOT NULL, manual_order BOOLEAN NOT NULL, CONSTRAINT FK_6940A7FE6DEDCEC2 FOREIGN KEY (id_preview_attachement) REFERENCES "attachments" (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_6940A7FE5697F554 FOREIGN KEY (id_category) REFERENCES "categories" (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_6940A7FE7E371A10 FOREIGN KEY (id_footprint) REFERENCES "footprints" (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_6940A7FE2626CEF9 FOREIGN KEY (id_part_unit) REFERENCES "measurement_units" (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_6940A7FE1ECB93AE FOREIGN KEY (id_manufacturer) REFERENCES "manufacturers" (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_6940A7FE81081E9B FOREIGN KEY (order_orderdetails_id) REFERENCES "orderdetails" (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO "parts" (id, id_preview_attachement, id_category, id_footprint, id_part_unit, id_manufacturer, order_orderdetails_id, datetime_added, name, last_modified, needs_review, tags, mass, ipn, description, comment, visible, favorite, minamount, manufacturer_product_url, manufacturer_product_number, manufacturing_status, order_quantity, manual_order) SELECT id, id_preview_attachement, id_category, id_footprint, id_part_unit, id_manufacturer, order_orderdetails_id, datetime_added, name, last_modified, needs_review, tags, mass, ipn, description, comment, visible, favorite, minamount, manufacturer_product_url, manufacturer_product_number, manufacturing_status, order_quantity, manual_order FROM __temp__parts');
        $this->addSql('DROP TABLE __temp__parts');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_6940A7FE3D721C14 ON "parts" (ipn)');
        $this->addSql('CREATE INDEX IDX_6940A7FE6DEDCEC2 ON "parts" (id_preview_attachement)');
        $this->addSql('CREATE INDEX IDX_6940A7FE5697F554 ON "parts" (id_category)');
        $this->addSql('CREATE INDEX IDX_6940A7FE7E371A10 ON "parts" (id_footprint)');
        $this->addSql('CREATE INDEX IDX_6940A7FE2626CEF9 ON "parts" (id_part_unit)');
        $this->addSql('CREATE INDEX IDX_6940A7FE1ECB93AE ON "parts" (id_manufacturer)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_6940A7FE81081E9B ON "parts" (order_orderdetails_id)');
        $this->addSql('CREATE INDEX parts_idx_datet_name_last_id_needs ON "parts" (datetime_added, name, last_modified, id, needs_review)');
        $this->addSql('CREATE INDEX parts_idx_name ON "parts" (name)');
        $this->addSql('CREATE INDEX parts_idx_ipn ON "parts" (ipn)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__pricedetails AS SELECT id, id_currency, orderdetails_id, price, price_related_quantity, min_discount_quantity, manual_input, last_modified, datetime_added FROM "pricedetails"');
        $this->addSql('DROP TABLE "pricedetails"');
        $this->addSql('CREATE TABLE "pricedetails" (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, id_currency INTEGER DEFAULT NULL, orderdetails_id INTEGER NOT NULL, price NUMERIC(11, 5) NOT NULL --
(DC2Type:big_decimal)
        , price_related_quantity DOUBLE PRECISION NOT NULL, min_discount_quantity DOUBLE PRECISION NOT NULL, manual_input BOOLEAN NOT NULL, last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, CONSTRAINT FK_C68C4459398D64AA FOREIGN KEY (id_currency) REFERENCES currencies (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_C68C44594A01DDC7 FOREIGN KEY (orderdetails_id) REFERENCES "orderdetails" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO "pricedetails" (id, id_currency, orderdetails_id, price, price_related_quantity, min_discount_quantity, manual_input, last_modified, datetime_added) SELECT id, id_currency, orderdetails_id, price, price_related_quantity, min_discount_quantity, manual_input, last_modified, datetime_added FROM __temp__pricedetails');
        $this->addSql('DROP TABLE __temp__pricedetails');
        $this->addSql('CREATE INDEX IDX_C68C4459398D64AA ON "pricedetails" (id_currency)');
        $this->addSql('CREATE INDEX IDX_C68C44594A01DDC7 ON "pricedetails" (orderdetails_id)');
        $this->addSql('CREATE INDEX pricedetails_idx_min_discount ON "pricedetails" (min_discount_quantity)');
        $this->addSql('CREATE INDEX pricedetails_idx_min_discount_price_qty ON "pricedetails" (min_discount_quantity, price_related_quantity)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__project_bom_entries AS SELECT id, id_device, id_part, quantity, mountnames FROM project_bom_entries');
        $this->addSql('DROP TABLE project_bom_entries');
        $this->addSql('CREATE TABLE project_bom_entries (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, id_device INTEGER DEFAULT NULL, id_part INTEGER DEFAULT NULL, quantity INTEGER NOT NULL, mountnames CLOB NOT NULL, CONSTRAINT FK_1AA2DD312F180363 FOREIGN KEY (id_device) REFERENCES projects (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_1AA2DD31C22F6CC4 FOREIGN KEY (id_part) REFERENCES "parts" (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO project_bom_entries (id, id_device, id_part, quantity, mountnames) SELECT id, id_device, id_part, quantity, mountnames FROM __temp__project_bom_entries');
        $this->addSql('DROP TABLE __temp__project_bom_entries');
        $this->addSql('CREATE INDEX IDX_AFC547992F180363 ON project_bom_entries (id_device)');
        $this->addSql('CREATE INDEX IDX_AFC54799C22F6CC4 ON project_bom_entries (id_part)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__projects AS SELECT id, parent_id, id_preview_attachement, order_quantity, order_only_missing_parts, comment, not_selectable, name, last_modified, datetime_added FROM projects');
        $this->addSql('DROP TABLE projects');
        $this->addSql('CREATE TABLE projects (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, parent_id INTEGER DEFAULT NULL, id_preview_attachement INTEGER DEFAULT NULL, order_quantity INTEGER NOT NULL, order_only_missing_parts BOOLEAN NOT NULL, comment CLOB NOT NULL, not_selectable BOOLEAN NOT NULL, name VARCHAR(255) NOT NULL, last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, CONSTRAINT FK_5C93B3A4727ACA70 FOREIGN KEY (parent_id) REFERENCES projects (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_5C93B3A46DEDCEC2 FOREIGN KEY (id_preview_attachement) REFERENCES "attachments" (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO projects (id, parent_id, id_preview_attachement, order_quantity, order_only_missing_parts, comment, not_selectable, name, last_modified, datetime_added) SELECT id, parent_id, id_preview_attachement, order_quantity, order_only_missing_parts, comment, not_selectable, name, last_modified, datetime_added FROM __temp__projects');
        $this->addSql('DROP TABLE __temp__projects');
        $this->addSql('CREATE INDEX IDX_11074E9A727ACA70 ON projects (parent_id)');
        $this->addSql('CREATE INDEX IDX_11074E9A6DEDCEC2 ON projects (id_preview_attachement)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__suppliers AS SELECT id, parent_id, default_currency_id, id_preview_attachement, shipping_costs, address, phone_number, fax_number, email_address, website, auto_product_url, comment, not_selectable, name, last_modified, datetime_added FROM "suppliers"');
        $this->addSql('DROP TABLE "suppliers"');
        $this->addSql('CREATE TABLE "suppliers" (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, parent_id INTEGER DEFAULT NULL, default_currency_id INTEGER DEFAULT NULL, id_preview_attachement INTEGER DEFAULT NULL, shipping_costs NUMERIC(11, 5) DEFAULT NULL --
(DC2Type:big_decimal)
        , address VARCHAR(255) NOT NULL, phone_number VARCHAR(255) NOT NULL, fax_number VARCHAR(255) NOT NULL, email_address VARCHAR(255) NOT NULL, website VARCHAR(255) NOT NULL, auto_product_url VARCHAR(255) NOT NULL, comment CLOB NOT NULL, not_selectable BOOLEAN NOT NULL, name VARCHAR(255) NOT NULL, last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, CONSTRAINT FK_AC28B95C727ACA70 FOREIGN KEY (parent_id) REFERENCES "suppliers" (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_AC28B95CECD792C0 FOREIGN KEY (default_currency_id) REFERENCES currencies (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_AC28B95C6DEDCEC2 FOREIGN KEY (id_preview_attachement) REFERENCES "attachments" (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO "suppliers" (id, parent_id, default_currency_id, id_preview_attachement, shipping_costs, address, phone_number, fax_number, email_address, website, auto_product_url, comment, not_selectable, name, last_modified, datetime_added) SELECT id, parent_id, default_currency_id, id_preview_attachement, shipping_costs, address, phone_number, fax_number, email_address, website, auto_product_url, comment, not_selectable, name, last_modified, datetime_added FROM __temp__suppliers');
        $this->addSql('DROP TABLE __temp__suppliers');
        $this->addSql('CREATE INDEX IDX_AC28B95C727ACA70 ON "suppliers" (parent_id)');
        $this->addSql('CREATE INDEX IDX_AC28B95CECD792C0 ON "suppliers" (default_currency_id)');
        $this->addSql('CREATE INDEX IDX_AC28B95C6DEDCEC2 ON "suppliers" (id_preview_attachement)');
        $this->addSql('CREATE INDEX supplier_idx_name ON "suppliers" (name)');
        $this->addSql('CREATE INDEX supplier_idx_parent_name ON "suppliers" (parent_id, name)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__users AS SELECT id, group_id, currency_id, id_preview_attachement, disabled, config_theme, pw_reset_token, config_instock_comment_a, config_instock_comment_w, trusted_device_cookie_version, backup_codes, google_authenticator_secret, config_timezone, config_language, email, department, last_name, first_name, need_pw_change, password, name, settings, backup_codes_generation_date, pw_reset_expires, last_modified, datetime_added, permissions_data FROM "users"');
        $this->addSql('DROP TABLE "users"');
        $this->addSql('CREATE TABLE "users" (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, group_id INTEGER DEFAULT NULL, currency_id INTEGER DEFAULT NULL, id_preview_attachement INTEGER DEFAULT NULL, disabled BOOLEAN NOT NULL, config_theme VARCHAR(255) DEFAULT NULL, pw_reset_token VARCHAR(255) DEFAULT NULL, config_instock_comment_a CLOB NOT NULL, config_instock_comment_w CLOB NOT NULL, trusted_device_cookie_version INTEGER NOT NULL, backup_codes CLOB NOT NULL --
(DC2Type:json)
        , google_authenticator_secret VARCHAR(255) DEFAULT NULL, config_timezone VARCHAR(255) DEFAULT NULL, config_language VARCHAR(255) DEFAULT NULL, email VARCHAR(255) DEFAULT NULL, department VARCHAR(255) DEFAULT NULL, last_name VARCHAR(255) DEFAULT NULL, first_name VARCHAR(255) DEFAULT NULL, need_pw_change BOOLEAN NOT NULL, password VARCHAR(255) DEFAULT NULL, name VARCHAR(180) NOT NULL, settings CLOB NOT NULL --
(DC2Type:json)
        , backup_codes_generation_date DATETIME DEFAULT NULL, pw_reset_expires DATETIME DEFAULT NULL, last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, permissions_data CLOB DEFAULT \'[]\' NOT NULL --
(DC2Type:json)
        , CONSTRAINT FK_1483A5E9FE54D947 FOREIGN KEY (group_id) REFERENCES "groups" (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_1483A5E938248176 FOREIGN KEY (currency_id) REFERENCES currencies (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_1483A5E96DEDCEC2 FOREIGN KEY (id_preview_attachement) REFERENCES "attachments" (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO "users" (id, group_id, currency_id, id_preview_attachement, disabled, config_theme, pw_reset_token, config_instock_comment_a, config_instock_comment_w, trusted_device_cookie_version, backup_codes, google_authenticator_secret, config_timezone, config_language, email, department, last_name, first_name, need_pw_change, password, name, settings, backup_codes_generation_date, pw_reset_expires, last_modified, datetime_added, permissions_data) SELECT id, group_id, currency_id, id_preview_attachement, disabled, config_theme, pw_reset_token, config_instock_comment_a, config_instock_comment_w, trusted_device_cookie_version, backup_codes, google_authenticator_secret, config_timezone, config_language, email, department, last_name, first_name, need_pw_change, password, name, settings, backup_codes_generation_date, pw_reset_expires, last_modified, datetime_added, permissions_data FROM __temp__users');
        $this->addSql('DROP TABLE __temp__users');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_1483A5E95E237E06 ON "users" (name)');
        $this->addSql('CREATE INDEX IDX_1483A5E9FE54D947 ON "users" (group_id)');
        $this->addSql('CREATE INDEX IDX_1483A5E938248176 ON "users" (currency_id)');
        $this->addSql('CREATE INDEX IDX_1483A5E96DEDCEC2 ON "users" (id_preview_attachement)');
        $this->addSql('CREATE INDEX user_idx_username ON "users" (name)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__webauthn_keys AS SELECT id, user_id, public_key_credential_id, type, transports, attestation_type, trust_path, aaguid, credential_public_key, user_handle, counter, name, last_modified, datetime_added FROM webauthn_keys');
        $this->addSql('DROP TABLE webauthn_keys');
        $this->addSql('CREATE TABLE webauthn_keys (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, user_id INTEGER DEFAULT NULL, public_key_credential_id CLOB NOT NULL --
(DC2Type:base64)
        , type VARCHAR(255) NOT NULL, transports CLOB NOT NULL --
(DC2Type:array)
        , attestation_type VARCHAR(255) NOT NULL, trust_path CLOB NOT NULL --
(DC2Type:trust_path)
        , aaguid CLOB NOT NULL --
(DC2Type:aaguid)
        , credential_public_key CLOB NOT NULL --
(DC2Type:base64)
        , user_handle VARCHAR(255) NOT NULL, counter INTEGER NOT NULL, name VARCHAR(255) NOT NULL, last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, CONSTRAINT FK_799FD143A76ED395 FOREIGN KEY (user_id) REFERENCES "users" (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO webauthn_keys (id, user_id, public_key_credential_id, type, transports, attestation_type, trust_path, aaguid, credential_public_key, user_handle, counter, name, last_modified, datetime_added) SELECT id, user_id, public_key_credential_id, type, transports, attestation_type, trust_path, aaguid, credential_public_key, user_handle, counter, name, last_modified, datetime_added FROM __temp__webauthn_keys');
        $this->addSql('DROP TABLE __temp__webauthn_keys');
        $this->addSql('CREATE INDEX IDX_799FD143A76ED395 ON webauthn_keys (user_id)');

        $this->addSql('ALTER TABLE projects RENAME TO devices');
        $this->addSql('ALTER TABLE project_bom_entries RENAME TO device_parts');
    }
}
