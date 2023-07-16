<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Migration\AbstractMultiPlatformMigration;
use Doctrine\DBAL\Schema\Schema;

final class Version20230716184033 extends AbstractMultiPlatformMigration
{
    public function getDescription(): string
    {
        return 'Create the structure needed for the information provider system';
    }

    public function mySQLUp(Schema $schema): void
    {
        $this->addSql('CREATE TABLE oauth_tokens (id INT AUTO_INCREMENT NOT NULL, token VARCHAR(255) DEFAULT NULL, expires_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', refresh_token VARCHAR(255) NOT NULL, name VARCHAR(255) NOT NULL, last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, UNIQUE INDEX oauth_tokens_unique_name (name), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE attachment_types ADD alternative_names LONGTEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE categories ADD alternative_names LONGTEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE currencies ADD alternative_names LONGTEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE footprints ADD alternative_names LONGTEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE groups ADD alternative_names LONGTEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE manufacturers ADD alternative_names LONGTEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE measurement_units ADD alternative_names LONGTEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE parts ADD provider_reference_provider_key VARCHAR(255) DEFAULT NULL, ADD provider_reference_provider_id VARCHAR(255) DEFAULT NULL, ADD provider_reference_provider_url VARCHAR(255) DEFAULT NULL, ADD provider_reference_last_updated DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL');
        $this->addSql('ALTER TABLE projects ADD alternative_names LONGTEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE storelocations ADD alternative_names LONGTEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE suppliers ADD alternative_names LONGTEXT DEFAULT NULL');
    }

    public function mySQLDown(Schema $schema): void
    {
        $this->addSql('DROP TABLE oauth_tokens');
        $this->addSql('ALTER TABLE `attachment_types` DROP alternative_names');
        $this->addSql('ALTER TABLE `categories` DROP alternative_names');
        $this->addSql('ALTER TABLE currencies DROP alternative_names');
        $this->addSql('ALTER TABLE `footprints` DROP alternative_names');
        $this->addSql('ALTER TABLE `groups` DROP alternative_names');
        $this->addSql('ALTER TABLE `manufacturers` DROP alternative_names');
        $this->addSql('ALTER TABLE `measurement_units` DROP alternative_names');
        $this->addSql('ALTER TABLE `parts` DROP provider_reference_provider_key, DROP provider_reference_provider_id, DROP provider_reference_provider_url, DROP provider_reference_last_updated');
        $this->addSql('ALTER TABLE projects DROP alternative_names');
        $this->addSql('ALTER TABLE `storelocations` DROP alternative_names');
        $this->addSql('ALTER TABLE `suppliers` DROP alternative_names');
    }

    public function sqLiteUp(Schema $schema): void
    {
        $this->addSql('CREATE TABLE oauth_tokens (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, token VARCHAR(255) DEFAULT NULL, expires_at DATETIME DEFAULT NULL --(DC2Type:datetime_immutable)
        , refresh_token VARCHAR(255) NOT NULL, name VARCHAR(255) NOT NULL, last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL)');
        $this->addSql('CREATE UNIQUE INDEX oauth_tokens_unique_name ON oauth_tokens (name)');
        $this->addSql('ALTER TABLE attachment_types ADD COLUMN alternative_names CLOB DEFAULT NULL');
        $this->addSql('ALTER TABLE categories ADD COLUMN alternative_names CLOB DEFAULT NULL');
        $this->addSql('CREATE TEMPORARY TABLE __temp__currencies AS SELECT id, parent_id, id_preview_attachment, exchange_rate, iso_code, comment, not_selectable, name, last_modified, datetime_added FROM currencies');
        $this->addSql('DROP TABLE currencies');
        $this->addSql('CREATE TABLE currencies (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, parent_id INTEGER DEFAULT NULL, id_preview_attachment INTEGER DEFAULT NULL, exchange_rate NUMERIC(11, 5) DEFAULT NULL --(DC2Type:big_decimal)
        , iso_code VARCHAR(255) NOT NULL, comment CLOB NOT NULL, not_selectable BOOLEAN NOT NULL, name VARCHAR(255) NOT NULL, last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, alternative_names CLOB DEFAULT NULL, CONSTRAINT FK_37C44693727ACA70 FOREIGN KEY (parent_id) REFERENCES currencies (id) ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_37C44693EA7100A1 FOREIGN KEY (id_preview_attachment) REFERENCES attachments (id) ON UPDATE NO ACTION ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO currencies (id, parent_id, id_preview_attachment, exchange_rate, iso_code, comment, not_selectable, name, last_modified, datetime_added) SELECT id, parent_id, id_preview_attachment, exchange_rate, iso_code, comment, not_selectable, name, last_modified, datetime_added FROM __temp__currencies');
        $this->addSql('DROP TABLE __temp__currencies');
        $this->addSql('CREATE INDEX IDX_37C44693727ACA70 ON currencies (parent_id)');
        $this->addSql('CREATE INDEX currency_idx_name ON currencies (name)');
        $this->addSql('CREATE INDEX currency_idx_parent_name ON currencies (parent_id, name)');
        $this->addSql('CREATE INDEX IDX_37C44693EA7100A1 ON currencies (id_preview_attachment)');
        $this->addSql('ALTER TABLE footprints ADD COLUMN alternative_names CLOB DEFAULT NULL');
        $this->addSql('CREATE TEMPORARY TABLE __temp__groups AS SELECT id, parent_id, id_preview_attachment, enforce_2fa, comment, not_selectable, name, last_modified, datetime_added, permissions_data FROM groups');
        $this->addSql('DROP TABLE groups');
        $this->addSql('CREATE TABLE groups (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, parent_id INTEGER DEFAULT NULL, id_preview_attachment INTEGER DEFAULT NULL, enforce_2fa BOOLEAN NOT NULL, comment CLOB NOT NULL, not_selectable BOOLEAN NOT NULL, name VARCHAR(255) NOT NULL, last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, permissions_data CLOB NOT NULL --(DC2Type:json)
        , alternative_names CLOB DEFAULT NULL, CONSTRAINT FK_F06D3970727ACA70 FOREIGN KEY (parent_id) REFERENCES groups (id) ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_F06D3970EA7100A1 FOREIGN KEY (id_preview_attachment) REFERENCES attachments (id) ON UPDATE NO ACTION ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO groups (id, parent_id, id_preview_attachment, enforce_2fa, comment, not_selectable, name, last_modified, datetime_added, permissions_data) SELECT id, parent_id, id_preview_attachment, enforce_2fa, comment, not_selectable, name, last_modified, datetime_added, permissions_data FROM __temp__groups');
        $this->addSql('DROP TABLE __temp__groups');
        $this->addSql('CREATE INDEX group_idx_parent_name ON groups (parent_id, name)');
        $this->addSql('CREATE INDEX group_idx_name ON groups (name)');
        $this->addSql('CREATE INDEX IDX_F06D3970727ACA70 ON groups (parent_id)');
        $this->addSql('CREATE INDEX IDX_F06D3970EA7100A1 ON groups (id_preview_attachment)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__log AS SELECT id, id_user, datetime, level, target_id, target_type, extra, type, username FROM log');
        $this->addSql('DROP TABLE log');
        $this->addSql('CREATE TABLE log (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, id_user INTEGER DEFAULT NULL, datetime DATETIME NOT NULL, level TINYINT NOT NULL --(DC2Type:tinyint)
        , target_id INTEGER NOT NULL, target_type SMALLINT NOT NULL, extra CLOB NOT NULL --(DC2Type:json)
        , type SMALLINT NOT NULL, username VARCHAR(255) NOT NULL, CONSTRAINT FK_8F3F68C56B3CA4B FOREIGN KEY (id_user) REFERENCES users (id) ON UPDATE NO ACTION ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO log (id, id_user, datetime, level, target_id, target_type, extra, type, username) SELECT id, id_user, datetime, level, target_id, target_type, extra, type, username FROM __temp__log');
        $this->addSql('DROP TABLE __temp__log');
        $this->addSql('CREATE INDEX log_idx_datetime ON log (datetime)');
        $this->addSql('CREATE INDEX log_idx_type_target ON log (type, target_type, target_id)');
        $this->addSql('CREATE INDEX log_idx_type ON log (type)');
        $this->addSql('CREATE INDEX IDX_8F3F68C56B3CA4B ON log (id_user)');
        $this->addSql('ALTER TABLE manufacturers ADD COLUMN alternative_names CLOB DEFAULT NULL');
        $this->addSql('ALTER TABLE measurement_units ADD COLUMN alternative_names CLOB DEFAULT NULL');
        $this->addSql('CREATE TEMPORARY TABLE __temp__parts AS SELECT id, id_preview_attachment, id_category, id_footprint, id_part_unit, id_manufacturer, order_orderdetails_id, built_project_id, datetime_added, name, last_modified, needs_review, tags, mass, description, comment, visible, favorite, minamount, manufacturer_product_url, manufacturer_product_number, manufacturing_status, order_quantity, manual_order, ipn FROM parts');
        $this->addSql('DROP TABLE parts');
        $this->addSql('CREATE TABLE parts (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, id_preview_attachment INTEGER DEFAULT NULL, id_category INTEGER NOT NULL, id_footprint INTEGER DEFAULT NULL, id_part_unit INTEGER DEFAULT NULL, id_manufacturer INTEGER DEFAULT NULL, order_orderdetails_id INTEGER DEFAULT NULL, built_project_id INTEGER DEFAULT NULL, datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, name VARCHAR(255) NOT NULL, last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, needs_review BOOLEAN NOT NULL, tags CLOB NOT NULL, mass DOUBLE PRECISION DEFAULT NULL, description CLOB NOT NULL, comment CLOB NOT NULL, visible BOOLEAN NOT NULL, favorite BOOLEAN NOT NULL, minamount DOUBLE PRECISION NOT NULL, manufacturer_product_url VARCHAR(255) NOT NULL, manufacturer_product_number VARCHAR(255) NOT NULL, manufacturing_status VARCHAR(255) DEFAULT NULL, order_quantity INTEGER NOT NULL, manual_order BOOLEAN NOT NULL, ipn VARCHAR(100) DEFAULT NULL, provider_reference_provider_key VARCHAR(255) DEFAULT NULL, provider_reference_provider_id VARCHAR(255) DEFAULT NULL, provider_reference_provider_url VARCHAR(255) DEFAULT NULL, provider_reference_last_updated DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, CONSTRAINT FK_6940A7FE5697F554 FOREIGN KEY (id_category) REFERENCES categories (id) ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_6940A7FE7E371A10 FOREIGN KEY (id_footprint) REFERENCES footprints (id) ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_6940A7FE2626CEF9 FOREIGN KEY (id_part_unit) REFERENCES measurement_units (id) ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_6940A7FE1ECB93AE FOREIGN KEY (id_manufacturer) REFERENCES manufacturers (id) ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_6940A7FE81081E9B FOREIGN KEY (order_orderdetails_id) REFERENCES orderdetails (id) ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_6940A7FEE8AE70D9 FOREIGN KEY (built_project_id) REFERENCES projects (id) ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_6940A7FEEA7100A1 FOREIGN KEY (id_preview_attachment) REFERENCES attachments (id) ON UPDATE NO ACTION ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO parts (id, id_preview_attachment, id_category, id_footprint, id_part_unit, id_manufacturer, order_orderdetails_id, built_project_id, datetime_added, name, last_modified, needs_review, tags, mass, description, comment, visible, favorite, minamount, manufacturer_product_url, manufacturer_product_number, manufacturing_status, order_quantity, manual_order, ipn) SELECT id, id_preview_attachment, id_category, id_footprint, id_part_unit, id_manufacturer, order_orderdetails_id, built_project_id, datetime_added, name, last_modified, needs_review, tags, mass, description, comment, visible, favorite, minamount, manufacturer_product_url, manufacturer_product_number, manufacturing_status, order_quantity, manual_order, ipn FROM __temp__parts');
        $this->addSql('DROP TABLE __temp__parts');
        $this->addSql('CREATE INDEX IDX_6940A7FEEA7100A1 ON parts (id_preview_attachment)');
        $this->addSql('CREATE INDEX parts_idx_ipn ON parts (ipn)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_6940A7FE3D721C14 ON parts (ipn)');
        $this->addSql('CREATE INDEX parts_idx_name ON parts (name)');
        $this->addSql('CREATE INDEX parts_idx_datet_name_last_id_needs ON parts (datetime_added, name, last_modified, id, needs_review)');
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
        $this->addSql('CREATE INDEX IDX_C68C44594A01DDC7 ON pricedetails (orderdetails_id)');
        $this->addSql('CREATE INDEX IDX_C68C4459398D64AA ON pricedetails (id_currency)');
        $this->addSql('CREATE INDEX pricedetails_idx_min_discount ON pricedetails (min_discount_quantity)');
        $this->addSql('CREATE INDEX pricedetails_idx_min_discount_price_qty ON pricedetails (min_discount_quantity, price_related_quantity)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__project_bom_entries AS SELECT id, id_device, id_part, price_currency_id, quantity, mountnames, name, comment, price, last_modified, datetime_added FROM project_bom_entries');
        $this->addSql('DROP TABLE project_bom_entries');
        $this->addSql('CREATE TABLE project_bom_entries (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, id_device INTEGER DEFAULT NULL, id_part INTEGER DEFAULT NULL, price_currency_id INTEGER DEFAULT NULL, quantity DOUBLE PRECISION NOT NULL, mountnames CLOB NOT NULL, name VARCHAR(255) DEFAULT NULL, comment CLOB NOT NULL, price NUMERIC(11, 5) DEFAULT NULL --(DC2Type:big_decimal)
        , last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, CONSTRAINT FK_AFC547992F180363 FOREIGN KEY (id_device) REFERENCES projects (id) ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_AFC54799C22F6CC4 FOREIGN KEY (id_part) REFERENCES parts (id) ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_1AA2DD313FFDCD60 FOREIGN KEY (price_currency_id) REFERENCES currencies (id) ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO project_bom_entries (id, id_device, id_part, price_currency_id, quantity, mountnames, name, comment, price, last_modified, datetime_added) SELECT id, id_device, id_part, price_currency_id, quantity, mountnames, name, comment, price, last_modified, datetime_added FROM __temp__project_bom_entries');
        $this->addSql('DROP TABLE __temp__project_bom_entries');
        $this->addSql('CREATE INDEX IDX_1AA2DD31C22F6CC4 ON project_bom_entries (id_part)');
        $this->addSql('CREATE INDEX IDX_1AA2DD312F180363 ON project_bom_entries (id_device)');
        $this->addSql('CREATE INDEX IDX_1AA2DD313FFDCD60 ON project_bom_entries (price_currency_id)');
        $this->addSql('ALTER TABLE projects ADD COLUMN alternative_names CLOB DEFAULT NULL');
        $this->addSql('ALTER TABLE storelocations ADD COLUMN alternative_names CLOB DEFAULT NULL');
        $this->addSql('CREATE TEMPORARY TABLE __temp__suppliers AS SELECT id, parent_id, default_currency_id, id_preview_attachment, shipping_costs, address, phone_number, fax_number, email_address, website, auto_product_url, comment, not_selectable, name, last_modified, datetime_added FROM suppliers');
        $this->addSql('DROP TABLE suppliers');
        $this->addSql('CREATE TABLE suppliers (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, parent_id INTEGER DEFAULT NULL, default_currency_id INTEGER DEFAULT NULL, id_preview_attachment INTEGER DEFAULT NULL, shipping_costs NUMERIC(11, 5) DEFAULT NULL --(DC2Type:big_decimal)
        , address VARCHAR(255) NOT NULL, phone_number VARCHAR(255) NOT NULL, fax_number VARCHAR(255) NOT NULL, email_address VARCHAR(255) NOT NULL, website VARCHAR(255) NOT NULL, auto_product_url VARCHAR(255) NOT NULL, comment CLOB NOT NULL, not_selectable BOOLEAN NOT NULL, name VARCHAR(255) NOT NULL, last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, alternative_names CLOB DEFAULT NULL, CONSTRAINT FK_AC28B95C727ACA70 FOREIGN KEY (parent_id) REFERENCES suppliers (id) ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_AC28B95CECD792C0 FOREIGN KEY (default_currency_id) REFERENCES currencies (id) ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_AC28B95CEA7100A1 FOREIGN KEY (id_preview_attachment) REFERENCES attachments (id) ON UPDATE NO ACTION ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO suppliers (id, parent_id, default_currency_id, id_preview_attachment, shipping_costs, address, phone_number, fax_number, email_address, website, auto_product_url, comment, not_selectable, name, last_modified, datetime_added) SELECT id, parent_id, default_currency_id, id_preview_attachment, shipping_costs, address, phone_number, fax_number, email_address, website, auto_product_url, comment, not_selectable, name, last_modified, datetime_added FROM __temp__suppliers');
        $this->addSql('DROP TABLE __temp__suppliers');
        $this->addSql('CREATE INDEX IDX_AC28B95CECD792C0 ON suppliers (default_currency_id)');
        $this->addSql('CREATE INDEX IDX_AC28B95C727ACA70 ON suppliers (parent_id)');
        $this->addSql('CREATE INDEX supplier_idx_name ON suppliers (name)');
        $this->addSql('CREATE INDEX supplier_idx_parent_name ON suppliers (parent_id, name)');
        $this->addSql('CREATE INDEX IDX_AC28B95CEA7100A1 ON suppliers (id_preview_attachment)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__users AS SELECT id, group_id, currency_id, id_preview_attachment, disabled, config_theme, pw_reset_token, config_instock_comment_a, config_instock_comment_w, trusted_device_cookie_version, backup_codes, google_authenticator_secret, config_timezone, config_language, email, department, last_name, first_name, need_pw_change, password, name, settings, backup_codes_generation_date, pw_reset_expires, last_modified, datetime_added, permissions_data, saml_user, about_me, show_email_on_profile FROM users');
        $this->addSql('DROP TABLE users');
        $this->addSql('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, group_id INTEGER DEFAULT NULL, currency_id INTEGER DEFAULT NULL, id_preview_attachment INTEGER DEFAULT NULL, disabled BOOLEAN NOT NULL, config_theme VARCHAR(255) DEFAULT NULL, pw_reset_token VARCHAR(255) DEFAULT NULL, config_instock_comment_a CLOB NOT NULL, config_instock_comment_w CLOB NOT NULL, trusted_device_cookie_version INTEGER NOT NULL, backup_codes CLOB NOT NULL --(DC2Type:json)
        , google_authenticator_secret VARCHAR(255) DEFAULT NULL, config_timezone VARCHAR(255) DEFAULT NULL, config_language VARCHAR(255) DEFAULT NULL, email VARCHAR(255) DEFAULT NULL, department VARCHAR(255) DEFAULT NULL, last_name VARCHAR(255) DEFAULT NULL, first_name VARCHAR(255) DEFAULT NULL, need_pw_change BOOLEAN NOT NULL, password VARCHAR(255) DEFAULT NULL, name VARCHAR(180) NOT NULL, settings CLOB NOT NULL --(DC2Type:json)
        , backup_codes_generation_date DATETIME DEFAULT NULL, pw_reset_expires DATETIME DEFAULT NULL, last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, permissions_data CLOB NOT NULL --(DC2Type:json)
        , saml_user BOOLEAN NOT NULL, about_me CLOB NOT NULL, show_email_on_profile BOOLEAN DEFAULT 0 NOT NULL, CONSTRAINT FK_1483A5E9FE54D947 FOREIGN KEY (group_id) REFERENCES groups (id) ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_1483A5E938248176 FOREIGN KEY (currency_id) REFERENCES currencies (id) ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_1483A5E9EA7100A1 FOREIGN KEY (id_preview_attachment) REFERENCES attachments (id) ON UPDATE NO ACTION ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO users (id, group_id, currency_id, id_preview_attachment, disabled, config_theme, pw_reset_token, config_instock_comment_a, config_instock_comment_w, trusted_device_cookie_version, backup_codes, google_authenticator_secret, config_timezone, config_language, email, department, last_name, first_name, need_pw_change, password, name, settings, backup_codes_generation_date, pw_reset_expires, last_modified, datetime_added, permissions_data, saml_user, about_me, show_email_on_profile) SELECT id, group_id, currency_id, id_preview_attachment, disabled, config_theme, pw_reset_token, config_instock_comment_a, config_instock_comment_w, trusted_device_cookie_version, backup_codes, google_authenticator_secret, config_timezone, config_language, email, department, last_name, first_name, need_pw_change, password, name, settings, backup_codes_generation_date, pw_reset_expires, last_modified, datetime_added, permissions_data, saml_user, about_me, show_email_on_profile FROM __temp__users');
        $this->addSql('DROP TABLE __temp__users');
        $this->addSql('CREATE INDEX user_idx_username ON users (name)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_1483A5E95E237E06 ON users (name)');
        $this->addSql('CREATE INDEX IDX_1483A5E9FE54D947 ON users (group_id)');
        $this->addSql('CREATE INDEX IDX_1483A5E938248176 ON users (currency_id)');
        $this->addSql('CREATE INDEX IDX_1483A5E9EA7100A1 ON users (id_preview_attachment)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__webauthn_keys AS SELECT id, user_id, public_key_credential_id, type, transports, attestation_type, trust_path, aaguid, credential_public_key, user_handle, counter, name, last_modified, datetime_added, other_ui FROM webauthn_keys');
        $this->addSql('DROP TABLE webauthn_keys');
        $this->addSql('CREATE TABLE webauthn_keys (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, user_id INTEGER DEFAULT NULL, public_key_credential_id CLOB NOT NULL --(DC2Type:base64)
        , type VARCHAR(255) NOT NULL, transports CLOB NOT NULL --(DC2Type:array)
        , attestation_type VARCHAR(255) NOT NULL, trust_path CLOB NOT NULL --(DC2Type:trust_path)
        , aaguid CLOB NOT NULL --(DC2Type:aaguid)
        , credential_public_key CLOB NOT NULL --(DC2Type:base64)
        , user_handle VARCHAR(255) NOT NULL, counter INTEGER NOT NULL, name VARCHAR(255) NOT NULL, last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, other_ui CLOB DEFAULT NULL --(DC2Type:array)
        , CONSTRAINT FK_799FD143A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO webauthn_keys (id, user_id, public_key_credential_id, type, transports, attestation_type, trust_path, aaguid, credential_public_key, user_handle, counter, name, last_modified, datetime_added, other_ui) SELECT id, user_id, public_key_credential_id, type, transports, attestation_type, trust_path, aaguid, credential_public_key, user_handle, counter, name, last_modified, datetime_added, other_ui FROM __temp__webauthn_keys');
        $this->addSql('DROP TABLE __temp__webauthn_keys');
        $this->addSql('CREATE INDEX IDX_799FD143A76ED395 ON webauthn_keys (user_id)');
    }

    public function sqLiteDown(Schema $schema): void
    {
        $this->addSql('DROP TABLE oauth_tokens');
        $this->addSql('CREATE TEMPORARY TABLE __temp__attachment_types AS SELECT id, parent_id, id_preview_attachment, filetype_filter, comment, not_selectable, name, last_modified, datetime_added FROM "attachment_types"');
        $this->addSql('DROP TABLE "attachment_types"');
        $this->addSql('CREATE TABLE "attachment_types" (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, parent_id INTEGER DEFAULT NULL, id_preview_attachment INTEGER DEFAULT NULL, filetype_filter CLOB NOT NULL, comment CLOB NOT NULL, not_selectable BOOLEAN NOT NULL, name VARCHAR(255) NOT NULL, last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, CONSTRAINT FK_EFAED719727ACA70 FOREIGN KEY (parent_id) REFERENCES "attachment_types" (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_EFAED719EA7100A1 FOREIGN KEY (id_preview_attachment) REFERENCES "attachments" (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO "attachment_types" (id, parent_id, id_preview_attachment, filetype_filter, comment, not_selectable, name, last_modified, datetime_added) SELECT id, parent_id, id_preview_attachment, filetype_filter, comment, not_selectable, name, last_modified, datetime_added FROM __temp__attachment_types');
        $this->addSql('DROP TABLE __temp__attachment_types');
        $this->addSql('CREATE INDEX IDX_EFAED719727ACA70 ON "attachment_types" (parent_id)');
        $this->addSql('CREATE INDEX IDX_EFAED719EA7100A1 ON "attachment_types" (id_preview_attachment)');
        $this->addSql('CREATE INDEX attachment_types_idx_name ON "attachment_types" (name)');
        $this->addSql('CREATE INDEX attachment_types_idx_parent_name ON "attachment_types" (parent_id, name)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__categories AS SELECT id, parent_id, id_preview_attachment, partname_hint, partname_regex, disable_footprints, disable_manufacturers, disable_autodatasheets, disable_properties, default_description, default_comment, comment, not_selectable, name, last_modified, datetime_added FROM "categories"');
        $this->addSql('DROP TABLE "categories"');
        $this->addSql('CREATE TABLE "categories" (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, parent_id INTEGER DEFAULT NULL, id_preview_attachment INTEGER DEFAULT NULL, partname_hint CLOB NOT NULL, partname_regex CLOB NOT NULL, disable_footprints BOOLEAN NOT NULL, disable_manufacturers BOOLEAN NOT NULL, disable_autodatasheets BOOLEAN NOT NULL, disable_properties BOOLEAN NOT NULL, default_description CLOB NOT NULL, default_comment CLOB NOT NULL, comment CLOB NOT NULL, not_selectable BOOLEAN NOT NULL, name VARCHAR(255) NOT NULL, last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, CONSTRAINT FK_3AF34668727ACA70 FOREIGN KEY (parent_id) REFERENCES "categories" (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_3AF34668EA7100A1 FOREIGN KEY (id_preview_attachment) REFERENCES "attachments" (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO "categories" (id, parent_id, id_preview_attachment, partname_hint, partname_regex, disable_footprints, disable_manufacturers, disable_autodatasheets, disable_properties, default_description, default_comment, comment, not_selectable, name, last_modified, datetime_added) SELECT id, parent_id, id_preview_attachment, partname_hint, partname_regex, disable_footprints, disable_manufacturers, disable_autodatasheets, disable_properties, default_description, default_comment, comment, not_selectable, name, last_modified, datetime_added FROM __temp__categories');
        $this->addSql('DROP TABLE __temp__categories');
        $this->addSql('CREATE INDEX IDX_3AF34668727ACA70 ON "categories" (parent_id)');
        $this->addSql('CREATE INDEX IDX_3AF34668EA7100A1 ON "categories" (id_preview_attachment)');
        $this->addSql('CREATE INDEX category_idx_name ON "categories" (name)');
        $this->addSql('CREATE INDEX category_idx_parent_name ON "categories" (parent_id, name)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__currencies AS SELECT id, parent_id, id_preview_attachment, exchange_rate, iso_code, comment, not_selectable, name, last_modified, datetime_added FROM currencies');
        $this->addSql('DROP TABLE currencies');
        $this->addSql('CREATE TABLE currencies (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, parent_id INTEGER DEFAULT NULL, id_preview_attachment INTEGER DEFAULT NULL, exchange_rate NUMERIC(11, 5) DEFAULT NULL --
(DC2Type:big_decimal)
        , iso_code VARCHAR(255) NOT NULL, comment CLOB NOT NULL, not_selectable BOOLEAN NOT NULL, name VARCHAR(255) NOT NULL, last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, CONSTRAINT FK_37C44693727ACA70 FOREIGN KEY (parent_id) REFERENCES currencies (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_37C44693EA7100A1 FOREIGN KEY (id_preview_attachment) REFERENCES "attachments" (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO currencies (id, parent_id, id_preview_attachment, exchange_rate, iso_code, comment, not_selectable, name, last_modified, datetime_added) SELECT id, parent_id, id_preview_attachment, exchange_rate, iso_code, comment, not_selectable, name, last_modified, datetime_added FROM __temp__currencies');
        $this->addSql('DROP TABLE __temp__currencies');
        $this->addSql('CREATE INDEX IDX_37C44693727ACA70 ON currencies (parent_id)');
        $this->addSql('CREATE INDEX IDX_37C44693EA7100A1 ON currencies (id_preview_attachment)');
        $this->addSql('CREATE INDEX currency_idx_name ON currencies (name)');
        $this->addSql('CREATE INDEX currency_idx_parent_name ON currencies (parent_id, name)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__footprints AS SELECT id, parent_id, id_footprint_3d, id_preview_attachment, comment, not_selectable, name, last_modified, datetime_added FROM "footprints"');
        $this->addSql('DROP TABLE "footprints"');
        $this->addSql('CREATE TABLE "footprints" (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, parent_id INTEGER DEFAULT NULL, id_footprint_3d INTEGER DEFAULT NULL, id_preview_attachment INTEGER DEFAULT NULL, comment CLOB NOT NULL, not_selectable BOOLEAN NOT NULL, name VARCHAR(255) NOT NULL, last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, CONSTRAINT FK_A34D68A2727ACA70 FOREIGN KEY (parent_id) REFERENCES "footprints" (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_A34D68A232A38C34 FOREIGN KEY (id_footprint_3d) REFERENCES "attachments" (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_A34D68A2EA7100A1 FOREIGN KEY (id_preview_attachment) REFERENCES "attachments" (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO "footprints" (id, parent_id, id_footprint_3d, id_preview_attachment, comment, not_selectable, name, last_modified, datetime_added) SELECT id, parent_id, id_footprint_3d, id_preview_attachment, comment, not_selectable, name, last_modified, datetime_added FROM __temp__footprints');
        $this->addSql('DROP TABLE __temp__footprints');
        $this->addSql('CREATE INDEX IDX_A34D68A2727ACA70 ON "footprints" (parent_id)');
        $this->addSql('CREATE INDEX IDX_A34D68A232A38C34 ON "footprints" (id_footprint_3d)');
        $this->addSql('CREATE INDEX IDX_A34D68A2EA7100A1 ON "footprints" (id_preview_attachment)');
        $this->addSql('CREATE INDEX footprint_idx_name ON "footprints" (name)');
        $this->addSql('CREATE INDEX footprint_idx_parent_name ON "footprints" (parent_id, name)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__groups AS SELECT id, parent_id, id_preview_attachment, enforce_2fa, comment, not_selectable, name, last_modified, datetime_added, permissions_data FROM "groups"');
        $this->addSql('DROP TABLE "groups"');
        $this->addSql('CREATE TABLE "groups" (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, parent_id INTEGER DEFAULT NULL, id_preview_attachment INTEGER DEFAULT NULL, enforce_2fa BOOLEAN NOT NULL, comment CLOB NOT NULL, not_selectable BOOLEAN NOT NULL, name VARCHAR(255) NOT NULL, last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, permissions_data CLOB NOT NULL --
(DC2Type:json)
        , CONSTRAINT FK_F06D3970727ACA70 FOREIGN KEY (parent_id) REFERENCES "groups" (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_F06D3970EA7100A1 FOREIGN KEY (id_preview_attachment) REFERENCES "attachments" (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO "groups" (id, parent_id, id_preview_attachment, enforce_2fa, comment, not_selectable, name, last_modified, datetime_added, permissions_data) SELECT id, parent_id, id_preview_attachment, enforce_2fa, comment, not_selectable, name, last_modified, datetime_added, permissions_data FROM __temp__groups');
        $this->addSql('DROP TABLE __temp__groups');
        $this->addSql('CREATE INDEX IDX_F06D3970727ACA70 ON "groups" (parent_id)');
        $this->addSql('CREATE INDEX IDX_F06D3970EA7100A1 ON "groups" (id_preview_attachment)');
        $this->addSql('CREATE INDEX group_idx_name ON "groups" (name)');
        $this->addSql('CREATE INDEX group_idx_parent_name ON "groups" (parent_id, name)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__log AS SELECT id, id_user, username, datetime, level, target_id, target_type, extra, type FROM log');
        $this->addSql('DROP TABLE log');
        $this->addSql('CREATE TABLE log (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, id_user INTEGER DEFAULT NULL, username VARCHAR(255) NOT NULL, datetime DATETIME NOT NULL, level TINYINT NOT NULL --
(DC2Type:tinyint)
        , target_id INTEGER NOT NULL, target_type SMALLINT NOT NULL, extra CLOB NOT NULL --
(DC2Type:json)
        , type SMALLINT NOT NULL, CONSTRAINT FK_8F3F68C56B3CA4B FOREIGN KEY (id_user) REFERENCES "users" (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO log (id, id_user, username, datetime, level, target_id, target_type, extra, type) SELECT id, id_user, username, datetime, level, target_id, target_type, extra, type FROM __temp__log');
        $this->addSql('DROP TABLE __temp__log');
        $this->addSql('CREATE INDEX IDX_8F3F68C56B3CA4B ON log (id_user)');
        $this->addSql('CREATE INDEX log_idx_type ON log (type)');
        $this->addSql('CREATE INDEX log_idx_type_target ON log (type, target_type, target_id)');
        $this->addSql('CREATE INDEX log_idx_datetime ON log (datetime)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__manufacturers AS SELECT id, parent_id, id_preview_attachment, address, phone_number, fax_number, email_address, website, auto_product_url, comment, not_selectable, name, last_modified, datetime_added FROM "manufacturers"');
        $this->addSql('DROP TABLE "manufacturers"');
        $this->addSql('CREATE TABLE "manufacturers" (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, parent_id INTEGER DEFAULT NULL, id_preview_attachment INTEGER DEFAULT NULL, address VARCHAR(255) NOT NULL, phone_number VARCHAR(255) NOT NULL, fax_number VARCHAR(255) NOT NULL, email_address VARCHAR(255) NOT NULL, website VARCHAR(255) NOT NULL, auto_product_url VARCHAR(255) NOT NULL, comment CLOB NOT NULL, not_selectable BOOLEAN NOT NULL, name VARCHAR(255) NOT NULL, last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, CONSTRAINT FK_94565B12727ACA70 FOREIGN KEY (parent_id) REFERENCES "manufacturers" (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_94565B12EA7100A1 FOREIGN KEY (id_preview_attachment) REFERENCES "attachments" (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO "manufacturers" (id, parent_id, id_preview_attachment, address, phone_number, fax_number, email_address, website, auto_product_url, comment, not_selectable, name, last_modified, datetime_added) SELECT id, parent_id, id_preview_attachment, address, phone_number, fax_number, email_address, website, auto_product_url, comment, not_selectable, name, last_modified, datetime_added FROM __temp__manufacturers');
        $this->addSql('DROP TABLE __temp__manufacturers');
        $this->addSql('CREATE INDEX IDX_94565B12727ACA70 ON "manufacturers" (parent_id)');
        $this->addSql('CREATE INDEX IDX_94565B12EA7100A1 ON "manufacturers" (id_preview_attachment)');
        $this->addSql('CREATE INDEX manufacturer_name ON "manufacturers" (name)');
        $this->addSql('CREATE INDEX manufacturer_idx_parent_name ON "manufacturers" (parent_id, name)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__measurement_units AS SELECT id, parent_id, id_preview_attachment, unit, is_integer, use_si_prefix, comment, not_selectable, name, last_modified, datetime_added FROM "measurement_units"');
        $this->addSql('DROP TABLE "measurement_units"');
        $this->addSql('CREATE TABLE "measurement_units" (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, parent_id INTEGER DEFAULT NULL, id_preview_attachment INTEGER DEFAULT NULL, unit VARCHAR(255) DEFAULT NULL, is_integer BOOLEAN NOT NULL, use_si_prefix BOOLEAN NOT NULL, comment CLOB NOT NULL, not_selectable BOOLEAN NOT NULL, name VARCHAR(255) NOT NULL, last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, CONSTRAINT FK_F5AF83CF727ACA70 FOREIGN KEY (parent_id) REFERENCES "measurement_units" (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_F5AF83CFEA7100A1 FOREIGN KEY (id_preview_attachment) REFERENCES "attachments" (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO "measurement_units" (id, parent_id, id_preview_attachment, unit, is_integer, use_si_prefix, comment, not_selectable, name, last_modified, datetime_added) SELECT id, parent_id, id_preview_attachment, unit, is_integer, use_si_prefix, comment, not_selectable, name, last_modified, datetime_added FROM __temp__measurement_units');
        $this->addSql('DROP TABLE __temp__measurement_units');
        $this->addSql('CREATE INDEX IDX_F5AF83CF727ACA70 ON "measurement_units" (parent_id)');
        $this->addSql('CREATE INDEX IDX_F5AF83CFEA7100A1 ON "measurement_units" (id_preview_attachment)');
        $this->addSql('CREATE INDEX unit_idx_name ON "measurement_units" (name)');
        $this->addSql('CREATE INDEX unit_idx_parent_name ON "measurement_units" (parent_id, name)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__parts AS SELECT id, id_preview_attachment, id_category, id_footprint, id_part_unit, id_manufacturer, order_orderdetails_id, built_project_id, name, last_modified, datetime_added, needs_review, tags, mass, ipn, description, comment, visible, favorite, minamount, manufacturer_product_url, manufacturer_product_number, manufacturing_status, order_quantity, manual_order FROM "parts"');
        $this->addSql('DROP TABLE "parts"');
        $this->addSql('CREATE TABLE "parts" (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, id_preview_attachment INTEGER DEFAULT NULL, id_category INTEGER NOT NULL, id_footprint INTEGER DEFAULT NULL, id_part_unit INTEGER DEFAULT NULL, id_manufacturer INTEGER DEFAULT NULL, order_orderdetails_id INTEGER DEFAULT NULL, built_project_id INTEGER DEFAULT NULL, name VARCHAR(255) NOT NULL, last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, needs_review BOOLEAN NOT NULL, tags CLOB NOT NULL, mass DOUBLE PRECISION DEFAULT NULL, ipn VARCHAR(100) DEFAULT NULL, description CLOB NOT NULL, comment CLOB NOT NULL, visible BOOLEAN NOT NULL, favorite BOOLEAN NOT NULL, minamount DOUBLE PRECISION NOT NULL, manufacturer_product_url VARCHAR(255) NOT NULL, manufacturer_product_number VARCHAR(255) NOT NULL, manufacturing_status VARCHAR(255) DEFAULT NULL, order_quantity INTEGER NOT NULL, manual_order BOOLEAN NOT NULL, CONSTRAINT FK_6940A7FEEA7100A1 FOREIGN KEY (id_preview_attachment) REFERENCES "attachments" (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_6940A7FE5697F554 FOREIGN KEY (id_category) REFERENCES "categories" (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_6940A7FE7E371A10 FOREIGN KEY (id_footprint) REFERENCES "footprints" (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_6940A7FE2626CEF9 FOREIGN KEY (id_part_unit) REFERENCES "measurement_units" (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_6940A7FE1ECB93AE FOREIGN KEY (id_manufacturer) REFERENCES "manufacturers" (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_6940A7FE81081E9B FOREIGN KEY (order_orderdetails_id) REFERENCES "orderdetails" (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_6940A7FEE8AE70D9 FOREIGN KEY (built_project_id) REFERENCES projects (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO "parts" (id, id_preview_attachment, id_category, id_footprint, id_part_unit, id_manufacturer, order_orderdetails_id, built_project_id, name, last_modified, datetime_added, needs_review, tags, mass, ipn, description, comment, visible, favorite, minamount, manufacturer_product_url, manufacturer_product_number, manufacturing_status, order_quantity, manual_order) SELECT id, id_preview_attachment, id_category, id_footprint, id_part_unit, id_manufacturer, order_orderdetails_id, built_project_id, name, last_modified, datetime_added, needs_review, tags, mass, ipn, description, comment, visible, favorite, minamount, manufacturer_product_url, manufacturer_product_number, manufacturing_status, order_quantity, manual_order FROM __temp__parts');
        $this->addSql('DROP TABLE __temp__parts');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_6940A7FE3D721C14 ON "parts" (ipn)');
        $this->addSql('CREATE INDEX IDX_6940A7FEEA7100A1 ON "parts" (id_preview_attachment)');
        $this->addSql('CREATE INDEX IDX_6940A7FE5697F554 ON "parts" (id_category)');
        $this->addSql('CREATE INDEX IDX_6940A7FE7E371A10 ON "parts" (id_footprint)');
        $this->addSql('CREATE INDEX IDX_6940A7FE2626CEF9 ON "parts" (id_part_unit)');
        $this->addSql('CREATE INDEX IDX_6940A7FE1ECB93AE ON "parts" (id_manufacturer)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_6940A7FE81081E9B ON "parts" (order_orderdetails_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_6940A7FEE8AE70D9 ON "parts" (built_project_id)');
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
        $this->addSql('CREATE TEMPORARY TABLE __temp__project_bom_entries AS SELECT id, id_device, id_part, price_currency_id, quantity, mountnames, name, comment, price, last_modified, datetime_added FROM project_bom_entries');
        $this->addSql('DROP TABLE project_bom_entries');
        $this->addSql('CREATE TABLE project_bom_entries (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, id_device INTEGER DEFAULT NULL, id_part INTEGER DEFAULT NULL, price_currency_id INTEGER DEFAULT NULL, quantity DOUBLE PRECISION NOT NULL, mountnames CLOB NOT NULL, name VARCHAR(255) DEFAULT NULL, comment CLOB NOT NULL, price NUMERIC(11, 5) DEFAULT NULL --
(DC2Type:big_decimal)
        , last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, CONSTRAINT FK_1AA2DD312F180363 FOREIGN KEY (id_device) REFERENCES projects (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_1AA2DD31C22F6CC4 FOREIGN KEY (id_part) REFERENCES "parts" (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_1AA2DD313FFDCD60 FOREIGN KEY (price_currency_id) REFERENCES currencies (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO project_bom_entries (id, id_device, id_part, price_currency_id, quantity, mountnames, name, comment, price, last_modified, datetime_added) SELECT id, id_device, id_part, price_currency_id, quantity, mountnames, name, comment, price, last_modified, datetime_added FROM __temp__project_bom_entries');
        $this->addSql('DROP TABLE __temp__project_bom_entries');
        $this->addSql('CREATE INDEX IDX_1AA2DD312F180363 ON project_bom_entries (id_device)');
        $this->addSql('CREATE INDEX IDX_1AA2DD31C22F6CC4 ON project_bom_entries (id_part)');
        $this->addSql('CREATE INDEX IDX_1AA2DD313FFDCD60 ON project_bom_entries (price_currency_id)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__projects AS SELECT id, parent_id, id_preview_attachment, order_quantity, status, order_only_missing_parts, description, comment, not_selectable, name, last_modified, datetime_added FROM projects');
        $this->addSql('DROP TABLE projects');
        $this->addSql('CREATE TABLE projects (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, parent_id INTEGER DEFAULT NULL, id_preview_attachment INTEGER DEFAULT NULL, order_quantity INTEGER NOT NULL, status VARCHAR(64) DEFAULT NULL, order_only_missing_parts BOOLEAN NOT NULL, description CLOB NOT NULL, comment CLOB NOT NULL, not_selectable BOOLEAN NOT NULL, name VARCHAR(255) NOT NULL, last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, CONSTRAINT FK_5C93B3A4727ACA70 FOREIGN KEY (parent_id) REFERENCES projects (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_5C93B3A4EA7100A1 FOREIGN KEY (id_preview_attachment) REFERENCES "attachments" (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO projects (id, parent_id, id_preview_attachment, order_quantity, status, order_only_missing_parts, description, comment, not_selectable, name, last_modified, datetime_added) SELECT id, parent_id, id_preview_attachment, order_quantity, status, order_only_missing_parts, description, comment, not_selectable, name, last_modified, datetime_added FROM __temp__projects');
        $this->addSql('DROP TABLE __temp__projects');
        $this->addSql('CREATE INDEX IDX_5C93B3A4727ACA70 ON projects (parent_id)');
        $this->addSql('CREATE INDEX IDX_5C93B3A4EA7100A1 ON projects (id_preview_attachment)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__storelocations AS SELECT id, parent_id, storage_type_id, id_owner, id_preview_attachment, is_full, only_single_part, limit_to_existing_parts, part_owner_must_match, comment, not_selectable, name, last_modified, datetime_added FROM "storelocations"');
        $this->addSql('DROP TABLE "storelocations"');
        $this->addSql('CREATE TABLE "storelocations" (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, parent_id INTEGER DEFAULT NULL, storage_type_id INTEGER DEFAULT NULL, id_owner INTEGER DEFAULT NULL, id_preview_attachment INTEGER DEFAULT NULL, is_full BOOLEAN NOT NULL, only_single_part BOOLEAN NOT NULL, limit_to_existing_parts BOOLEAN NOT NULL, part_owner_must_match BOOLEAN DEFAULT 0 NOT NULL, comment CLOB NOT NULL, not_selectable BOOLEAN NOT NULL, name VARCHAR(255) NOT NULL, last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, CONSTRAINT FK_7517020727ACA70 FOREIGN KEY (parent_id) REFERENCES "storelocations" (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_7517020B270BFF1 FOREIGN KEY (storage_type_id) REFERENCES "measurement_units" (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_751702021E5A74C FOREIGN KEY (id_owner) REFERENCES "users" (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_7517020EA7100A1 FOREIGN KEY (id_preview_attachment) REFERENCES "attachments" (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO "storelocations" (id, parent_id, storage_type_id, id_owner, id_preview_attachment, is_full, only_single_part, limit_to_existing_parts, part_owner_must_match, comment, not_selectable, name, last_modified, datetime_added) SELECT id, parent_id, storage_type_id, id_owner, id_preview_attachment, is_full, only_single_part, limit_to_existing_parts, part_owner_must_match, comment, not_selectable, name, last_modified, datetime_added FROM __temp__storelocations');
        $this->addSql('DROP TABLE __temp__storelocations');
        $this->addSql('CREATE INDEX IDX_7517020727ACA70 ON "storelocations" (parent_id)');
        $this->addSql('CREATE INDEX IDX_7517020B270BFF1 ON "storelocations" (storage_type_id)');
        $this->addSql('CREATE INDEX IDX_751702021E5A74C ON "storelocations" (id_owner)');
        $this->addSql('CREATE INDEX IDX_7517020EA7100A1 ON "storelocations" (id_preview_attachment)');
        $this->addSql('CREATE INDEX location_idx_name ON "storelocations" (name)');
        $this->addSql('CREATE INDEX location_idx_parent_name ON "storelocations" (parent_id, name)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__suppliers AS SELECT id, parent_id, default_currency_id, id_preview_attachment, shipping_costs, address, phone_number, fax_number, email_address, website, auto_product_url, comment, not_selectable, name, last_modified, datetime_added FROM "suppliers"');
        $this->addSql('DROP TABLE "suppliers"');
        $this->addSql('CREATE TABLE "suppliers" (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, parent_id INTEGER DEFAULT NULL, default_currency_id INTEGER DEFAULT NULL, id_preview_attachment INTEGER DEFAULT NULL, shipping_costs NUMERIC(11, 5) DEFAULT NULL --
(DC2Type:big_decimal)
        , address VARCHAR(255) NOT NULL, phone_number VARCHAR(255) NOT NULL, fax_number VARCHAR(255) NOT NULL, email_address VARCHAR(255) NOT NULL, website VARCHAR(255) NOT NULL, auto_product_url VARCHAR(255) NOT NULL, comment CLOB NOT NULL, not_selectable BOOLEAN NOT NULL, name VARCHAR(255) NOT NULL, last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, CONSTRAINT FK_AC28B95C727ACA70 FOREIGN KEY (parent_id) REFERENCES "suppliers" (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_AC28B95CECD792C0 FOREIGN KEY (default_currency_id) REFERENCES currencies (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_AC28B95CEA7100A1 FOREIGN KEY (id_preview_attachment) REFERENCES "attachments" (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO "suppliers" (id, parent_id, default_currency_id, id_preview_attachment, shipping_costs, address, phone_number, fax_number, email_address, website, auto_product_url, comment, not_selectable, name, last_modified, datetime_added) SELECT id, parent_id, default_currency_id, id_preview_attachment, shipping_costs, address, phone_number, fax_number, email_address, website, auto_product_url, comment, not_selectable, name, last_modified, datetime_added FROM __temp__suppliers');
        $this->addSql('DROP TABLE __temp__suppliers');
        $this->addSql('CREATE INDEX IDX_AC28B95C727ACA70 ON "suppliers" (parent_id)');
        $this->addSql('CREATE INDEX IDX_AC28B95CECD792C0 ON "suppliers" (default_currency_id)');
        $this->addSql('CREATE INDEX IDX_AC28B95CEA7100A1 ON "suppliers" (id_preview_attachment)');
        $this->addSql('CREATE INDEX supplier_idx_name ON "suppliers" (name)');
        $this->addSql('CREATE INDEX supplier_idx_parent_name ON "suppliers" (parent_id, name)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__users AS SELECT id, group_id, currency_id, id_preview_attachment, disabled, config_theme, pw_reset_token, config_instock_comment_a, config_instock_comment_w, about_me, trusted_device_cookie_version, backup_codes, google_authenticator_secret, config_timezone, config_language, email, show_email_on_profile, department, last_name, first_name, need_pw_change, password, name, settings, backup_codes_generation_date, pw_reset_expires, saml_user, last_modified, datetime_added, permissions_data FROM "users"');
        $this->addSql('DROP TABLE "users"');
        $this->addSql('CREATE TABLE "users" (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, group_id INTEGER DEFAULT NULL, currency_id INTEGER DEFAULT NULL, id_preview_attachment INTEGER DEFAULT NULL, disabled BOOLEAN NOT NULL, config_theme VARCHAR(255) DEFAULT NULL, pw_reset_token VARCHAR(255) DEFAULT NULL, config_instock_comment_a CLOB NOT NULL, config_instock_comment_w CLOB NOT NULL, about_me CLOB NOT NULL, trusted_device_cookie_version INTEGER NOT NULL, backup_codes CLOB NOT NULL --
(DC2Type:json)
        , google_authenticator_secret VARCHAR(255) DEFAULT NULL, config_timezone VARCHAR(255) DEFAULT NULL, config_language VARCHAR(255) DEFAULT NULL, email VARCHAR(255) DEFAULT NULL, show_email_on_profile BOOLEAN DEFAULT 0 NOT NULL, department VARCHAR(255) DEFAULT NULL, last_name VARCHAR(255) DEFAULT NULL, first_name VARCHAR(255) DEFAULT NULL, need_pw_change BOOLEAN NOT NULL, password VARCHAR(255) DEFAULT NULL, name VARCHAR(180) NOT NULL, settings CLOB NOT NULL --
(DC2Type:json)
        , backup_codes_generation_date DATETIME DEFAULT NULL, pw_reset_expires DATETIME DEFAULT NULL, saml_user BOOLEAN NOT NULL, last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, permissions_data CLOB NOT NULL --
(DC2Type:json)
        , CONSTRAINT FK_1483A5E9FE54D947 FOREIGN KEY (group_id) REFERENCES "groups" (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_1483A5E938248176 FOREIGN KEY (currency_id) REFERENCES currencies (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_1483A5E9EA7100A1 FOREIGN KEY (id_preview_attachment) REFERENCES "attachments" (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO "users" (id, group_id, currency_id, id_preview_attachment, disabled, config_theme, pw_reset_token, config_instock_comment_a, config_instock_comment_w, about_me, trusted_device_cookie_version, backup_codes, google_authenticator_secret, config_timezone, config_language, email, show_email_on_profile, department, last_name, first_name, need_pw_change, password, name, settings, backup_codes_generation_date, pw_reset_expires, saml_user, last_modified, datetime_added, permissions_data) SELECT id, group_id, currency_id, id_preview_attachment, disabled, config_theme, pw_reset_token, config_instock_comment_a, config_instock_comment_w, about_me, trusted_device_cookie_version, backup_codes, google_authenticator_secret, config_timezone, config_language, email, show_email_on_profile, department, last_name, first_name, need_pw_change, password, name, settings, backup_codes_generation_date, pw_reset_expires, saml_user, last_modified, datetime_added, permissions_data FROM __temp__users');
        $this->addSql('DROP TABLE __temp__users');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_1483A5E95E237E06 ON "users" (name)');
        $this->addSql('CREATE INDEX IDX_1483A5E9FE54D947 ON "users" (group_id)');
        $this->addSql('CREATE INDEX IDX_1483A5E938248176 ON "users" (currency_id)');
        $this->addSql('CREATE INDEX IDX_1483A5E9EA7100A1 ON "users" (id_preview_attachment)');
        $this->addSql('CREATE INDEX user_idx_username ON "users" (name)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__webauthn_keys AS SELECT id, user_id, public_key_credential_id, type, transports, attestation_type, trust_path, aaguid, credential_public_key, user_handle, counter, other_ui, name, last_modified, datetime_added FROM webauthn_keys');
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
        , user_handle VARCHAR(255) NOT NULL, counter INTEGER NOT NULL, other_ui CLOB DEFAULT NULL --
(DC2Type:array)
        , name VARCHAR(255) NOT NULL, last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, CONSTRAINT FK_799FD143A76ED395 FOREIGN KEY (user_id) REFERENCES "users" (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO webauthn_keys (id, user_id, public_key_credential_id, type, transports, attestation_type, trust_path, aaguid, credential_public_key, user_handle, counter, other_ui, name, last_modified, datetime_added) SELECT id, user_id, public_key_credential_id, type, transports, attestation_type, trust_path, aaguid, credential_public_key, user_handle, counter, other_ui, name, last_modified, datetime_added FROM __temp__webauthn_keys');
        $this->addSql('DROP TABLE __temp__webauthn_keys');
        $this->addSql('CREATE INDEX IDX_799FD143A76ED395 ON webauthn_keys (user_id)');
    }
}
