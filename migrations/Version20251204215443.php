<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Migration\AbstractMultiPlatformMigration;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251204215443 extends AbstractMultiPlatformMigration
{
    public function getDescription(): string
    {
        return 'Increase URL field lengths to 2048 characters';
    }

    public function mySQLUp(Schema $schema): void
    {
        $this->addSql('ALTER TABLE attachments CHANGE external_path external_path VARCHAR(2048) DEFAULT NULL');
        $this->addSql('ALTER TABLE manufacturers CHANGE website website VARCHAR(2048) NOT NULL, CHANGE auto_product_url auto_product_url VARCHAR(2048) NOT NULL');
        $this->addSql('ALTER TABLE parts CHANGE provider_reference_provider_url provider_reference_provider_url VARCHAR(2048) DEFAULT NULL');
        $this->addSql('ALTER TABLE suppliers CHANGE website website VARCHAR(2048) NOT NULL, CHANGE auto_product_url auto_product_url VARCHAR(2048) NOT NULL');
    }

    public function mySQLDown(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `attachments` CHANGE external_path external_path VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE `manufacturers` CHANGE website website VARCHAR(255) NOT NULL, CHANGE auto_product_url auto_product_url VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE `parts` CHANGE provider_reference_provider_url provider_reference_provider_url VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE `suppliers` CHANGE website website VARCHAR(255) NOT NULL, CHANGE auto_product_url auto_product_url VARCHAR(255) NOT NULL');
    }

    public function sqLiteUp(Schema $schema): void
    {
        $this->addSql('CREATE TEMPORARY TABLE __temp__attachments AS SELECT id, type_id, original_filename, show_in_table, name, last_modified, datetime_added, class_name, element_id, internal_path, external_path FROM attachments');
        $this->addSql('DROP TABLE attachments');
        $this->addSql('CREATE TABLE attachments (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, type_id INTEGER NOT NULL, original_filename VARCHAR(255) DEFAULT NULL, show_in_table BOOLEAN NOT NULL, name VARCHAR(255) NOT NULL, last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, class_name VARCHAR(255) NOT NULL, element_id INTEGER NOT NULL, internal_path VARCHAR(255) DEFAULT NULL, external_path VARCHAR(2048) DEFAULT NULL, CONSTRAINT FK_47C4FAD6C54C8C93 FOREIGN KEY (type_id) REFERENCES attachment_types (id) ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO attachments (id, type_id, original_filename, show_in_table, name, last_modified, datetime_added, class_name, element_id, internal_path, external_path) SELECT id, type_id, original_filename, show_in_table, name, last_modified, datetime_added, class_name, element_id, internal_path, external_path FROM __temp__attachments');
        $this->addSql('DROP TABLE __temp__attachments');
        $this->addSql('CREATE INDEX attachment_element_idx ON attachments (class_name, element_id)');
        $this->addSql('CREATE INDEX attachment_name_idx ON attachments (name)');
        $this->addSql('CREATE INDEX attachments_idx_class_name_id ON attachments (class_name, id)');
        $this->addSql('CREATE INDEX attachments_idx_id_element_id_class_name ON attachments (id, element_id, class_name)');
        $this->addSql('CREATE INDEX IDX_47C4FAD6C54C8C93 ON attachments (type_id)');
        $this->addSql('CREATE INDEX IDX_47C4FAD61F1F2A24 ON attachments (element_id)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__manufacturers AS SELECT id, parent_id, id_preview_attachment, address, phone_number, fax_number, email_address, website, auto_product_url, comment, not_selectable, name, last_modified, datetime_added, alternative_names FROM manufacturers');
        $this->addSql('DROP TABLE manufacturers');
        $this->addSql('CREATE TABLE manufacturers (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, parent_id INTEGER DEFAULT NULL, id_preview_attachment INTEGER DEFAULT NULL, address VARCHAR(255) NOT NULL, phone_number VARCHAR(255) NOT NULL, fax_number VARCHAR(255) NOT NULL, email_address VARCHAR(255) NOT NULL, website VARCHAR(2048) NOT NULL, auto_product_url VARCHAR(2048) NOT NULL, comment CLOB NOT NULL, not_selectable BOOLEAN NOT NULL, name VARCHAR(255) NOT NULL, last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, alternative_names CLOB DEFAULT NULL, CONSTRAINT FK_94565B12727ACA70 FOREIGN KEY (parent_id) REFERENCES manufacturers (id) ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_94565B12EA7100A1 FOREIGN KEY (id_preview_attachment) REFERENCES attachments (id) ON UPDATE NO ACTION ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO manufacturers (id, parent_id, id_preview_attachment, address, phone_number, fax_number, email_address, website, auto_product_url, comment, not_selectable, name, last_modified, datetime_added, alternative_names) SELECT id, parent_id, id_preview_attachment, address, phone_number, fax_number, email_address, website, auto_product_url, comment, not_selectable, name, last_modified, datetime_added, alternative_names FROM __temp__manufacturers');
        $this->addSql('DROP TABLE __temp__manufacturers');
        $this->addSql('CREATE INDEX IDX_94565B12EA7100A1 ON manufacturers (id_preview_attachment)');
        $this->addSql('CREATE INDEX IDX_94565B12727ACA70 ON manufacturers (parent_id)');
        $this->addSql('CREATE INDEX manufacturer_name ON manufacturers (name)');
        $this->addSql('CREATE INDEX manufacturer_idx_parent_name ON manufacturers (parent_id, name)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__parts AS SELECT id, id_preview_attachment, id_category, id_footprint, id_part_unit, id_manufacturer, id_part_custom_state, order_orderdetails_id, built_project_id, datetime_added, name, last_modified, needs_review, tags, mass, description, comment, visible, favorite, minamount, manufacturer_product_url, manufacturer_product_number, manufacturing_status, order_quantity, manual_order, ipn, provider_reference_provider_key, provider_reference_provider_id, provider_reference_provider_url, provider_reference_last_updated, eda_info_reference_prefix, eda_info_value, eda_info_invisible, eda_info_exclude_from_bom, eda_info_exclude_from_board, eda_info_exclude_from_sim, eda_info_kicad_symbol, eda_info_kicad_footprint FROM parts');
        $this->addSql('DROP TABLE parts');
        $this->addSql('CREATE TABLE parts (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, id_preview_attachment INTEGER DEFAULT NULL, id_category INTEGER NOT NULL, id_footprint INTEGER DEFAULT NULL, id_part_unit INTEGER DEFAULT NULL, id_manufacturer INTEGER DEFAULT NULL, id_part_custom_state INTEGER DEFAULT NULL, order_orderdetails_id INTEGER DEFAULT NULL, built_project_id INTEGER DEFAULT NULL, datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, name VARCHAR(255) NOT NULL, last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, needs_review BOOLEAN NOT NULL, tags CLOB NOT NULL, mass DOUBLE PRECISION DEFAULT NULL, description CLOB NOT NULL, comment CLOB NOT NULL, visible BOOLEAN NOT NULL, favorite BOOLEAN NOT NULL, minamount DOUBLE PRECISION NOT NULL, manufacturer_product_url CLOB NOT NULL, manufacturer_product_number VARCHAR(255) NOT NULL, manufacturing_status VARCHAR(255) DEFAULT NULL, order_quantity INTEGER NOT NULL, manual_order BOOLEAN NOT NULL, ipn VARCHAR(100) DEFAULT NULL, provider_reference_provider_key VARCHAR(255) DEFAULT NULL, provider_reference_provider_id VARCHAR(255) DEFAULT NULL, provider_reference_provider_url VARCHAR(2048) DEFAULT NULL, provider_reference_last_updated DATETIME DEFAULT NULL, eda_info_reference_prefix VARCHAR(255) DEFAULT NULL, eda_info_value VARCHAR(255) DEFAULT NULL, eda_info_invisible BOOLEAN DEFAULT NULL, eda_info_exclude_from_bom BOOLEAN DEFAULT NULL, eda_info_exclude_from_board BOOLEAN DEFAULT NULL, eda_info_exclude_from_sim BOOLEAN DEFAULT NULL, eda_info_kicad_symbol VARCHAR(255) DEFAULT NULL, eda_info_kicad_footprint VARCHAR(255) DEFAULT NULL, CONSTRAINT FK_6940A7FEEA7100A1 FOREIGN KEY (id_preview_attachment) REFERENCES attachments (id) ON UPDATE NO ACTION ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_6940A7FE5697F554 FOREIGN KEY (id_category) REFERENCES categories (id) ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_6940A7FE7E371A10 FOREIGN KEY (id_footprint) REFERENCES footprints (id) ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_6940A7FE2626CEF9 FOREIGN KEY (id_part_unit) REFERENCES measurement_units (id) ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_6940A7FE1ECB93AE FOREIGN KEY (id_manufacturer) REFERENCES manufacturers (id) ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_6940A7FEA3ED1215 FOREIGN KEY (id_part_custom_state) REFERENCES part_custom_states (id) ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_6940A7FE81081E9B FOREIGN KEY (order_orderdetails_id) REFERENCES orderdetails (id) ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_6940A7FEE8AE70D9 FOREIGN KEY (built_project_id) REFERENCES projects (id) ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO parts (id, id_preview_attachment, id_category, id_footprint, id_part_unit, id_manufacturer, id_part_custom_state, order_orderdetails_id, built_project_id, datetime_added, name, last_modified, needs_review, tags, mass, description, comment, visible, favorite, minamount, manufacturer_product_url, manufacturer_product_number, manufacturing_status, order_quantity, manual_order, ipn, provider_reference_provider_key, provider_reference_provider_id, provider_reference_provider_url, provider_reference_last_updated, eda_info_reference_prefix, eda_info_value, eda_info_invisible, eda_info_exclude_from_bom, eda_info_exclude_from_board, eda_info_exclude_from_sim, eda_info_kicad_symbol, eda_info_kicad_footprint) SELECT id, id_preview_attachment, id_category, id_footprint, id_part_unit, id_manufacturer, id_part_custom_state, order_orderdetails_id, built_project_id, datetime_added, name, last_modified, needs_review, tags, mass, description, comment, visible, favorite, minamount, manufacturer_product_url, manufacturer_product_number, manufacturing_status, order_quantity, manual_order, ipn, provider_reference_provider_key, provider_reference_provider_id, provider_reference_provider_url, provider_reference_last_updated, eda_info_reference_prefix, eda_info_value, eda_info_invisible, eda_info_exclude_from_bom, eda_info_exclude_from_board, eda_info_exclude_from_sim, eda_info_kicad_symbol, eda_info_kicad_footprint FROM __temp__parts');
        $this->addSql('DROP TABLE __temp__parts');
        $this->addSql('CREATE INDEX IDX_6940A7FEA3ED1215 ON parts (id_part_custom_state)');
        $this->addSql('CREATE INDEX IDX_6940A7FE1ECB93AE ON parts (id_manufacturer)');
        $this->addSql('CREATE INDEX IDX_6940A7FE2626CEF9 ON parts (id_part_unit)');
        $this->addSql('CREATE INDEX IDX_6940A7FE5697F554 ON parts (id_category)');
        $this->addSql('CREATE INDEX IDX_6940A7FE7E371A10 ON parts (id_footprint)');
        $this->addSql('CREATE INDEX IDX_6940A7FEEA7100A1 ON parts (id_preview_attachment)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_6940A7FE3D721C14 ON parts (ipn)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_6940A7FE81081E9B ON parts (order_orderdetails_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_6940A7FEE8AE70D9 ON parts (built_project_id)');
        $this->addSql('CREATE INDEX parts_idx_datet_name_last_id_needs ON parts (datetime_added, name, last_modified, id, needs_review)');
        $this->addSql('CREATE INDEX parts_idx_ipn ON parts (ipn)');
        $this->addSql('CREATE INDEX parts_idx_name ON parts (name)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__suppliers AS SELECT id, parent_id, default_currency_id, id_preview_attachment, shipping_costs, address, phone_number, fax_number, email_address, website, auto_product_url, comment, not_selectable, name, last_modified, datetime_added, alternative_names FROM suppliers');
        $this->addSql('DROP TABLE suppliers');
        $this->addSql('CREATE TABLE suppliers (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, parent_id INTEGER DEFAULT NULL, default_currency_id INTEGER DEFAULT NULL, id_preview_attachment INTEGER DEFAULT NULL, shipping_costs NUMERIC(11, 5) DEFAULT NULL, address VARCHAR(255) NOT NULL, phone_number VARCHAR(255) NOT NULL, fax_number VARCHAR(255) NOT NULL, email_address VARCHAR(255) NOT NULL, website VARCHAR(2048) NOT NULL, auto_product_url VARCHAR(2048) NOT NULL, comment CLOB NOT NULL, not_selectable BOOLEAN NOT NULL, name VARCHAR(255) NOT NULL, last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, alternative_names CLOB DEFAULT NULL, CONSTRAINT FK_AC28B95C727ACA70 FOREIGN KEY (parent_id) REFERENCES suppliers (id) ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_AC28B95CECD792C0 FOREIGN KEY (default_currency_id) REFERENCES currencies (id) ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_AC28B95CEA7100A1 FOREIGN KEY (id_preview_attachment) REFERENCES attachments (id) ON UPDATE NO ACTION ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO suppliers (id, parent_id, default_currency_id, id_preview_attachment, shipping_costs, address, phone_number, fax_number, email_address, website, auto_product_url, comment, not_selectable, name, last_modified, datetime_added, alternative_names) SELECT id, parent_id, default_currency_id, id_preview_attachment, shipping_costs, address, phone_number, fax_number, email_address, website, auto_product_url, comment, not_selectable, name, last_modified, datetime_added, alternative_names FROM __temp__suppliers');
        $this->addSql('DROP TABLE __temp__suppliers');
        $this->addSql('CREATE INDEX IDX_AC28B95CECD792C0 ON suppliers (default_currency_id)');
        $this->addSql('CREATE INDEX IDX_AC28B95C727ACA70 ON suppliers (parent_id)');
        $this->addSql('CREATE INDEX supplier_idx_name ON suppliers (name)');
        $this->addSql('CREATE INDEX supplier_idx_parent_name ON suppliers (parent_id, name)');
        $this->addSql('CREATE INDEX IDX_AC28B95CEA7100A1 ON suppliers (id_preview_attachment)');
    }

    public function sqLiteDown(Schema $schema): void
    {
        $this->addSql('CREATE TEMPORARY TABLE __temp__attachments AS SELECT id, name, last_modified, datetime_added, original_filename, internal_path, external_path, show_in_table, type_id, class_name, element_id FROM "attachments"');
        $this->addSql('DROP TABLE "attachments"');
        $this->addSql('CREATE TABLE "attachments" (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(255) NOT NULL, last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, original_filename VARCHAR(255) DEFAULT NULL, internal_path VARCHAR(255) DEFAULT NULL, external_path VARCHAR(255) DEFAULT NULL, show_in_table BOOLEAN NOT NULL, type_id INTEGER NOT NULL, class_name VARCHAR(255) NOT NULL, element_id INTEGER NOT NULL, CONSTRAINT FK_47C4FAD6C54C8C93 FOREIGN KEY (type_id) REFERENCES "attachment_types" (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO "attachments" (id, name, last_modified, datetime_added, original_filename, internal_path, external_path, show_in_table, type_id, class_name, element_id) SELECT id, name, last_modified, datetime_added, original_filename, internal_path, external_path, show_in_table, type_id, class_name, element_id FROM __temp__attachments');
        $this->addSql('DROP TABLE __temp__attachments');
        $this->addSql('CREATE INDEX IDX_47C4FAD6C54C8C93 ON "attachments" (type_id)');
        $this->addSql('CREATE INDEX IDX_47C4FAD61F1F2A24 ON "attachments" (element_id)');
        $this->addSql('CREATE INDEX attachments_idx_id_element_id_class_name ON "attachments" (id, element_id, class_name)');
        $this->addSql('CREATE INDEX attachments_idx_class_name_id ON "attachments" (class_name, id)');
        $this->addSql('CREATE INDEX attachment_name_idx ON "attachments" (name)');
        $this->addSql('CREATE INDEX attachment_element_idx ON "attachments" (class_name, element_id)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__manufacturers AS SELECT id, name, last_modified, datetime_added, comment, not_selectable, alternative_names, address, phone_number, fax_number, email_address, website, auto_product_url, parent_id, id_preview_attachment FROM "manufacturers"');
        $this->addSql('DROP TABLE "manufacturers"');
        $this->addSql('CREATE TABLE "manufacturers" (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(255) NOT NULL, last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, comment CLOB NOT NULL, not_selectable BOOLEAN NOT NULL, alternative_names CLOB DEFAULT NULL, address VARCHAR(255) NOT NULL, phone_number VARCHAR(255) NOT NULL, fax_number VARCHAR(255) NOT NULL, email_address VARCHAR(255) NOT NULL, website VARCHAR(255) NOT NULL, auto_product_url VARCHAR(255) NOT NULL, parent_id INTEGER DEFAULT NULL, id_preview_attachment INTEGER DEFAULT NULL, CONSTRAINT FK_94565B12727ACA70 FOREIGN KEY (parent_id) REFERENCES "manufacturers" (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_94565B12EA7100A1 FOREIGN KEY (id_preview_attachment) REFERENCES "attachments" (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO "manufacturers" (id, name, last_modified, datetime_added, comment, not_selectable, alternative_names, address, phone_number, fax_number, email_address, website, auto_product_url, parent_id, id_preview_attachment) SELECT id, name, last_modified, datetime_added, comment, not_selectable, alternative_names, address, phone_number, fax_number, email_address, website, auto_product_url, parent_id, id_preview_attachment FROM __temp__manufacturers');
        $this->addSql('DROP TABLE __temp__manufacturers');
        $this->addSql('CREATE INDEX IDX_94565B12727ACA70 ON "manufacturers" (parent_id)');
        $this->addSql('CREATE INDEX IDX_94565B12EA7100A1 ON "manufacturers" (id_preview_attachment)');
        $this->addSql('CREATE INDEX manufacturer_name ON "manufacturers" (name)');
        $this->addSql('CREATE INDEX manufacturer_idx_parent_name ON "manufacturers" (parent_id, name)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__parts AS SELECT id, name, last_modified, datetime_added, needs_review, tags, mass, ipn, description, comment, visible, favorite, minamount, manufacturer_product_url, manufacturer_product_number, manufacturing_status, order_quantity, manual_order, provider_reference_provider_key, provider_reference_provider_id, provider_reference_provider_url, provider_reference_last_updated, eda_info_reference_prefix, eda_info_value, eda_info_invisible, eda_info_exclude_from_bom, eda_info_exclude_from_board, eda_info_exclude_from_sim, eda_info_kicad_symbol, eda_info_kicad_footprint, id_preview_attachment, id_part_custom_state, id_category, id_footprint, id_part_unit, id_manufacturer, order_orderdetails_id, built_project_id FROM "parts"');
        $this->addSql('DROP TABLE "parts"');
        $this->addSql('CREATE TABLE "parts" (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(255) NOT NULL, last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, needs_review BOOLEAN NOT NULL, tags CLOB NOT NULL, mass DOUBLE PRECISION DEFAULT NULL, ipn VARCHAR(100) DEFAULT NULL, description CLOB NOT NULL, comment CLOB NOT NULL, visible BOOLEAN NOT NULL, favorite BOOLEAN NOT NULL, minamount DOUBLE PRECISION NOT NULL, manufacturer_product_url CLOB NOT NULL, manufacturer_product_number VARCHAR(255) NOT NULL, manufacturing_status VARCHAR(255) DEFAULT NULL, order_quantity INTEGER NOT NULL, manual_order BOOLEAN NOT NULL, provider_reference_provider_key VARCHAR(255) DEFAULT NULL, provider_reference_provider_id VARCHAR(255) DEFAULT NULL, provider_reference_provider_url VARCHAR(255) DEFAULT NULL, provider_reference_last_updated DATETIME DEFAULT NULL, eda_info_reference_prefix VARCHAR(255) DEFAULT NULL, eda_info_value VARCHAR(255) DEFAULT NULL, eda_info_invisible BOOLEAN DEFAULT NULL, eda_info_exclude_from_bom BOOLEAN DEFAULT NULL, eda_info_exclude_from_board BOOLEAN DEFAULT NULL, eda_info_exclude_from_sim BOOLEAN DEFAULT NULL, eda_info_kicad_symbol VARCHAR(255) DEFAULT NULL, eda_info_kicad_footprint VARCHAR(255) DEFAULT NULL, id_preview_attachment INTEGER DEFAULT NULL, id_part_custom_state INTEGER DEFAULT NULL, id_category INTEGER NOT NULL, id_footprint INTEGER DEFAULT NULL, id_part_unit INTEGER DEFAULT NULL, id_manufacturer INTEGER DEFAULT NULL, order_orderdetails_id INTEGER DEFAULT NULL, built_project_id INTEGER DEFAULT NULL, CONSTRAINT FK_6940A7FEEA7100A1 FOREIGN KEY (id_preview_attachment) REFERENCES "attachments" (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_6940A7FEA3ED1215 FOREIGN KEY (id_part_custom_state) REFERENCES "part_custom_states" (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_6940A7FE5697F554 FOREIGN KEY (id_category) REFERENCES "categories" (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_6940A7FE7E371A10 FOREIGN KEY (id_footprint) REFERENCES "footprints" (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_6940A7FE2626CEF9 FOREIGN KEY (id_part_unit) REFERENCES "measurement_units" (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_6940A7FE1ECB93AE FOREIGN KEY (id_manufacturer) REFERENCES "manufacturers" (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_6940A7FE81081E9B FOREIGN KEY (order_orderdetails_id) REFERENCES "orderdetails" (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_6940A7FEE8AE70D9 FOREIGN KEY (built_project_id) REFERENCES projects (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO "parts" (id, name, last_modified, datetime_added, needs_review, tags, mass, ipn, description, comment, visible, favorite, minamount, manufacturer_product_url, manufacturer_product_number, manufacturing_status, order_quantity, manual_order, provider_reference_provider_key, provider_reference_provider_id, provider_reference_provider_url, provider_reference_last_updated, eda_info_reference_prefix, eda_info_value, eda_info_invisible, eda_info_exclude_from_bom, eda_info_exclude_from_board, eda_info_exclude_from_sim, eda_info_kicad_symbol, eda_info_kicad_footprint, id_preview_attachment, id_part_custom_state, id_category, id_footprint, id_part_unit, id_manufacturer, order_orderdetails_id, built_project_id) SELECT id, name, last_modified, datetime_added, needs_review, tags, mass, ipn, description, comment, visible, favorite, minamount, manufacturer_product_url, manufacturer_product_number, manufacturing_status, order_quantity, manual_order, provider_reference_provider_key, provider_reference_provider_id, provider_reference_provider_url, provider_reference_last_updated, eda_info_reference_prefix, eda_info_value, eda_info_invisible, eda_info_exclude_from_bom, eda_info_exclude_from_board, eda_info_exclude_from_sim, eda_info_kicad_symbol, eda_info_kicad_footprint, id_preview_attachment, id_part_custom_state, id_category, id_footprint, id_part_unit, id_manufacturer, order_orderdetails_id, built_project_id FROM __temp__parts');
        $this->addSql('DROP TABLE __temp__parts');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_6940A7FE3D721C14 ON "parts" (ipn)');
        $this->addSql('CREATE INDEX IDX_6940A7FEEA7100A1 ON "parts" (id_preview_attachment)');
        $this->addSql('CREATE INDEX IDX_6940A7FEA3ED1215 ON "parts" (id_part_custom_state)');
        $this->addSql('CREATE INDEX IDX_6940A7FE5697F554 ON "parts" (id_category)');
        $this->addSql('CREATE INDEX IDX_6940A7FE7E371A10 ON "parts" (id_footprint)');
        $this->addSql('CREATE INDEX IDX_6940A7FE2626CEF9 ON "parts" (id_part_unit)');
        $this->addSql('CREATE INDEX IDX_6940A7FE1ECB93AE ON "parts" (id_manufacturer)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_6940A7FE81081E9B ON "parts" (order_orderdetails_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_6940A7FEE8AE70D9 ON "parts" (built_project_id)');
        $this->addSql('CREATE INDEX parts_idx_datet_name_last_id_needs ON "parts" (datetime_added, name, last_modified, id, needs_review)');
        $this->addSql('CREATE INDEX parts_idx_name ON "parts" (name)');
        $this->addSql('CREATE INDEX parts_idx_ipn ON "parts" (ipn)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__suppliers AS SELECT id, name, last_modified, datetime_added, comment, not_selectable, alternative_names, address, phone_number, fax_number, email_address, website, auto_product_url, shipping_costs, parent_id, default_currency_id, id_preview_attachment FROM "suppliers"');
        $this->addSql('DROP TABLE "suppliers"');
        $this->addSql('CREATE TABLE "suppliers" (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(255) NOT NULL, last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, comment CLOB NOT NULL, not_selectable BOOLEAN NOT NULL, alternative_names CLOB DEFAULT NULL, address VARCHAR(255) NOT NULL, phone_number VARCHAR(255) NOT NULL, fax_number VARCHAR(255) NOT NULL, email_address VARCHAR(255) NOT NULL, website VARCHAR(255) NOT NULL, auto_product_url VARCHAR(255) NOT NULL, shipping_costs NUMERIC(11, 5) DEFAULT NULL, parent_id INTEGER DEFAULT NULL, default_currency_id INTEGER DEFAULT NULL, id_preview_attachment INTEGER DEFAULT NULL, CONSTRAINT FK_AC28B95C727ACA70 FOREIGN KEY (parent_id) REFERENCES "suppliers" (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_AC28B95CECD792C0 FOREIGN KEY (default_currency_id) REFERENCES currencies (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_AC28B95CEA7100A1 FOREIGN KEY (id_preview_attachment) REFERENCES "attachments" (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO "suppliers" (id, name, last_modified, datetime_added, comment, not_selectable, alternative_names, address, phone_number, fax_number, email_address, website, auto_product_url, shipping_costs, parent_id, default_currency_id, id_preview_attachment) SELECT id, name, last_modified, datetime_added, comment, not_selectable, alternative_names, address, phone_number, fax_number, email_address, website, auto_product_url, shipping_costs, parent_id, default_currency_id, id_preview_attachment FROM __temp__suppliers');
        $this->addSql('DROP TABLE __temp__suppliers');
        $this->addSql('CREATE INDEX IDX_AC28B95C727ACA70 ON "suppliers" (parent_id)');
        $this->addSql('CREATE INDEX IDX_AC28B95CECD792C0 ON "suppliers" (default_currency_id)');
        $this->addSql('CREATE INDEX IDX_AC28B95CEA7100A1 ON "suppliers" (id_preview_attachment)');
        $this->addSql('CREATE INDEX supplier_idx_name ON "suppliers" (name)');
        $this->addSql('CREATE INDEX supplier_idx_parent_name ON "suppliers" (parent_id, name)');
    }

    public function postgreSQLUp(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE attachments ALTER external_path TYPE VARCHAR(2048)');
        $this->addSql('ALTER TABLE manufacturers ALTER website TYPE VARCHAR(2048)');
        $this->addSql('ALTER TABLE manufacturers ALTER auto_product_url TYPE VARCHAR(2048)');
        $this->addSql('ALTER TABLE parts ALTER provider_reference_provider_url TYPE VARCHAR(2048)');
        $this->addSql('ALTER TABLE suppliers ALTER website TYPE VARCHAR(2048)');
        $this->addSql('ALTER TABLE suppliers ALTER auto_product_url TYPE VARCHAR(2048)');
    }

    public function postgreSQLDown(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "attachments" ALTER external_path TYPE VARCHAR(255)');
        $this->addSql('ALTER TABLE "manufacturers" ALTER website TYPE VARCHAR(255)');
        $this->addSql('ALTER TABLE "manufacturers" ALTER auto_product_url TYPE VARCHAR(255)');
        $this->addSql('ALTER TABLE "parts" ALTER provider_reference_provider_url TYPE VARCHAR(255)');
        $this->addSql('ALTER TABLE "suppliers" ALTER website TYPE VARCHAR(255)');
        $this->addSql('ALTER TABLE "suppliers" ALTER auto_product_url TYPE VARCHAR(255)');
    }
}
