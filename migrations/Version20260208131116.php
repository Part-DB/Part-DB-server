<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Migration\AbstractMultiPlatformMigration;
use Doctrine\DBAL\Schema\Schema;


final class Version20260208131116 extends AbstractMultiPlatformMigration
{
    public function getDescription(): string
    {
        return 'Add GTIN fields, allowed targets for attachment types and last stocktake date for part lots and add include_vat field for price details.';
    }

    public function mySQLUp(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE attachment_types ADD allowed_targets LONGTEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE part_lots ADD last_stocktake_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE parts ADD gtin VARCHAR(255) DEFAULT NULL');
        $this->addSql('CREATE INDEX parts_idx_gtin ON parts (gtin)');
        $this->addSql('ALTER TABLE orderdetails ADD prices_includes_vat TINYINT DEFAULT NULL');
    }

    public function mySQLDown(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE `attachment_types` DROP allowed_targets');
        $this->addSql('DROP INDEX parts_idx_gtin ON `parts`');
        $this->addSql('ALTER TABLE `parts` DROP gtin');
        $this->addSql('ALTER TABLE part_lots DROP last_stocktake_at');
        $this->addSql('ALTER TABLE `orderdetails` DROP prices_includes_vat');
    }

    public function sqLiteUp(Schema $schema): void
    {
        $this->addSql('ALTER TABLE attachment_types ADD COLUMN allowed_targets CLOB DEFAULT NULL');
        $this->addSql('ALTER TABLE part_lots ADD COLUMN last_stocktake_at DATETIME DEFAULT NULL');
        $this->addSql('CREATE TEMPORARY TABLE __temp__parts AS SELECT id, id_preview_attachment, id_category, id_footprint, id_part_unit, id_manufacturer, id_part_custom_state, order_orderdetails_id, built_project_id, datetime_added, name, last_modified, needs_review, tags, mass, description, comment, visible, favorite, minamount, manufacturer_product_url, manufacturer_product_number, manufacturing_status, order_quantity, manual_order, ipn, provider_reference_provider_key, provider_reference_provider_id, provider_reference_provider_url, provider_reference_last_updated, eda_info_reference_prefix, eda_info_value, eda_info_invisible, eda_info_exclude_from_bom, eda_info_exclude_from_board, eda_info_exclude_from_sim, eda_info_kicad_symbol, eda_info_kicad_footprint FROM parts');
        $this->addSql('DROP TABLE parts');
        $this->addSql('CREATE TABLE parts (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, id_preview_attachment INTEGER DEFAULT NULL, id_category INTEGER NOT NULL, id_footprint INTEGER DEFAULT NULL, id_part_unit INTEGER DEFAULT NULL, id_manufacturer INTEGER DEFAULT NULL, id_part_custom_state INTEGER DEFAULT NULL, order_orderdetails_id INTEGER DEFAULT NULL, built_project_id INTEGER DEFAULT NULL, datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, name VARCHAR(255) NOT NULL, last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, needs_review BOOLEAN NOT NULL, tags CLOB NOT NULL, mass DOUBLE PRECISION DEFAULT NULL, description CLOB NOT NULL, comment CLOB NOT NULL, visible BOOLEAN NOT NULL, favorite BOOLEAN NOT NULL, minamount DOUBLE PRECISION NOT NULL, manufacturer_product_url CLOB NOT NULL, manufacturer_product_number VARCHAR(255) NOT NULL, manufacturing_status VARCHAR(255) DEFAULT NULL, order_quantity INTEGER NOT NULL, manual_order BOOLEAN NOT NULL, ipn VARCHAR(100) DEFAULT NULL, provider_reference_provider_key VARCHAR(255) DEFAULT NULL, provider_reference_provider_id VARCHAR(255) DEFAULT NULL, provider_reference_provider_url VARCHAR(2048) DEFAULT NULL, provider_reference_last_updated DATETIME DEFAULT NULL, eda_info_reference_prefix VARCHAR(255) DEFAULT NULL, eda_info_value VARCHAR(255) DEFAULT NULL, eda_info_invisible BOOLEAN DEFAULT NULL, eda_info_exclude_from_bom BOOLEAN DEFAULT NULL, eda_info_exclude_from_board BOOLEAN DEFAULT NULL, eda_info_exclude_from_sim BOOLEAN DEFAULT NULL, eda_info_kicad_symbol VARCHAR(255) DEFAULT NULL, eda_info_kicad_footprint VARCHAR(255) DEFAULT NULL, gtin VARCHAR(255) DEFAULT NULL, CONSTRAINT FK_6940A7FEEA7100A1 FOREIGN KEY (id_preview_attachment) REFERENCES attachments (id) ON UPDATE NO ACTION ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_6940A7FE5697F554 FOREIGN KEY (id_category) REFERENCES categories (id) ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_6940A7FE7E371A10 FOREIGN KEY (id_footprint) REFERENCES footprints (id) ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_6940A7FE2626CEF9 FOREIGN KEY (id_part_unit) REFERENCES measurement_units (id) ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_6940A7FE1ECB93AE FOREIGN KEY (id_manufacturer) REFERENCES manufacturers (id) ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_6940A7FEA3ED1215 FOREIGN KEY (id_part_custom_state) REFERENCES part_custom_states (id) ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_6940A7FE81081E9B FOREIGN KEY (order_orderdetails_id) REFERENCES orderdetails (id) ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_6940A7FEE8AE70D9 FOREIGN KEY (built_project_id) REFERENCES projects (id) ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO parts (id, id_preview_attachment, id_category, id_footprint, id_part_unit, id_manufacturer, id_part_custom_state, order_orderdetails_id, built_project_id, datetime_added, name, last_modified, needs_review, tags, mass, description, comment, visible, favorite, minamount, manufacturer_product_url, manufacturer_product_number, manufacturing_status, order_quantity, manual_order, ipn, provider_reference_provider_key, provider_reference_provider_id, provider_reference_provider_url, provider_reference_last_updated, eda_info_reference_prefix, eda_info_value, eda_info_invisible, eda_info_exclude_from_bom, eda_info_exclude_from_board, eda_info_exclude_from_sim, eda_info_kicad_symbol, eda_info_kicad_footprint) SELECT id, id_preview_attachment, id_category, id_footprint, id_part_unit, id_manufacturer, id_part_custom_state, order_orderdetails_id, built_project_id, datetime_added, name, last_modified, needs_review, tags, mass, description, comment, visible, favorite, minamount, manufacturer_product_url, manufacturer_product_number, manufacturing_status, order_quantity, manual_order, ipn, provider_reference_provider_key, provider_reference_provider_id, provider_reference_provider_url, provider_reference_last_updated, eda_info_reference_prefix, eda_info_value, eda_info_invisible, eda_info_exclude_from_bom, eda_info_exclude_from_board, eda_info_exclude_from_sim, eda_info_kicad_symbol, eda_info_kicad_footprint FROM __temp__parts');
        $this->addSql('DROP TABLE __temp__parts');
        $this->addSql('CREATE INDEX parts_idx_name ON parts (name)');
        $this->addSql('CREATE INDEX parts_idx_ipn ON parts (ipn)');
        $this->addSql('CREATE INDEX parts_idx_datet_name_last_id_needs ON parts (datetime_added, name, last_modified, id, needs_review)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_6940A7FEE8AE70D9 ON parts (built_project_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_6940A7FE81081E9B ON parts (order_orderdetails_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_6940A7FE3D721C14 ON parts (ipn)');
        $this->addSql('CREATE INDEX IDX_6940A7FEEA7100A1 ON parts (id_preview_attachment)');
        $this->addSql('CREATE INDEX IDX_6940A7FE7E371A10 ON parts (id_footprint)');
        $this->addSql('CREATE INDEX IDX_6940A7FE5697F554 ON parts (id_category)');
        $this->addSql('CREATE INDEX IDX_6940A7FE2626CEF9 ON parts (id_part_unit)');
        $this->addSql('CREATE INDEX IDX_6940A7FE1ECB93AE ON parts (id_manufacturer)');
        $this->addSql('CREATE INDEX IDX_6940A7FEA3ED1215 ON parts (id_part_custom_state)');
        $this->addSql('CREATE INDEX parts_idx_gtin ON parts (gtin)');
        $this->addSql('ALTER TABLE orderdetails ADD COLUMN prices_includes_vat BOOLEAN DEFAULT NULL');
    }

    public function sqLiteDown(Schema $schema): void
    {
        $this->addSql('CREATE TEMPORARY TABLE __temp__attachment_types AS SELECT id, name, last_modified, datetime_added, comment, not_selectable, alternative_names, filetype_filter, parent_id, id_preview_attachment FROM "attachment_types"');
        $this->addSql('DROP TABLE "attachment_types"');
        $this->addSql('CREATE TABLE "attachment_types" (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(255) NOT NULL, last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, comment CLOB NOT NULL, not_selectable BOOLEAN NOT NULL, alternative_names CLOB DEFAULT NULL, filetype_filter CLOB NOT NULL, parent_id INTEGER DEFAULT NULL, id_preview_attachment INTEGER DEFAULT NULL, CONSTRAINT FK_EFAED719727ACA70 FOREIGN KEY (parent_id) REFERENCES "attachment_types" (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_EFAED719EA7100A1 FOREIGN KEY (id_preview_attachment) REFERENCES "attachments" (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO "attachment_types" (id, name, last_modified, datetime_added, comment, not_selectable, alternative_names, filetype_filter, parent_id, id_preview_attachment) SELECT id, name, last_modified, datetime_added, comment, not_selectable, alternative_names, filetype_filter, parent_id, id_preview_attachment FROM __temp__attachment_types');
        $this->addSql('DROP TABLE __temp__attachment_types');
        $this->addSql('CREATE INDEX IDX_EFAED719727ACA70 ON "attachment_types" (parent_id)');
        $this->addSql('CREATE INDEX IDX_EFAED719EA7100A1 ON "attachment_types" (id_preview_attachment)');
        $this->addSql('CREATE INDEX attachment_types_idx_name ON "attachment_types" (name)');
        $this->addSql('CREATE INDEX attachment_types_idx_parent_name ON "attachment_types" (parent_id, name)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__part_lots AS SELECT id, description, comment, expiration_date, instock_unknown, amount, needs_refill, vendor_barcode, last_modified, datetime_added, id_store_location, id_part, id_owner FROM part_lots');
        $this->addSql('DROP TABLE part_lots');
        $this->addSql('CREATE TABLE part_lots (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, description CLOB NOT NULL, comment CLOB NOT NULL, expiration_date DATETIME DEFAULT NULL, instock_unknown BOOLEAN NOT NULL, amount DOUBLE PRECISION NOT NULL, needs_refill BOOLEAN NOT NULL, vendor_barcode VARCHAR(255) DEFAULT NULL, last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, id_store_location INTEGER DEFAULT NULL, id_part INTEGER NOT NULL, id_owner INTEGER DEFAULT NULL, CONSTRAINT FK_EBC8F9435D8F4B37 FOREIGN KEY (id_store_location) REFERENCES "storelocations" (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_EBC8F943C22F6CC4 FOREIGN KEY (id_part) REFERENCES "parts" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_EBC8F94321E5A74C FOREIGN KEY (id_owner) REFERENCES "users" (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO part_lots (id, description, comment, expiration_date, instock_unknown, amount, needs_refill, vendor_barcode, last_modified, datetime_added, id_store_location, id_part, id_owner) SELECT id, description, comment, expiration_date, instock_unknown, amount, needs_refill, vendor_barcode, last_modified, datetime_added, id_store_location, id_part, id_owner FROM __temp__part_lots');
        $this->addSql('DROP TABLE __temp__part_lots');
        $this->addSql('CREATE INDEX IDX_EBC8F9435D8F4B37 ON part_lots (id_store_location)');
        $this->addSql('CREATE INDEX IDX_EBC8F943C22F6CC4 ON part_lots (id_part)');
        $this->addSql('CREATE INDEX IDX_EBC8F94321E5A74C ON part_lots (id_owner)');
        $this->addSql('CREATE INDEX part_lots_idx_instock_un_expiration_id_part ON part_lots (instock_unknown, expiration_date, id_part)');
        $this->addSql('CREATE INDEX part_lots_idx_needs_refill ON part_lots (needs_refill)');
        $this->addSql('CREATE INDEX part_lots_idx_barcode ON part_lots (vendor_barcode)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__parts AS SELECT id, name, last_modified, datetime_added, needs_review, tags, mass, ipn, description, comment, visible, favorite, minamount, manufacturer_product_url, manufacturer_product_number, manufacturing_status, order_quantity, manual_order, provider_reference_provider_key, provider_reference_provider_id, provider_reference_provider_url, provider_reference_last_updated, eda_info_reference_prefix, eda_info_value, eda_info_invisible, eda_info_exclude_from_bom, eda_info_exclude_from_board, eda_info_exclude_from_sim, eda_info_kicad_symbol, eda_info_kicad_footprint, id_preview_attachment, id_part_custom_state, id_category, id_footprint, id_part_unit, id_manufacturer, order_orderdetails_id, built_project_id FROM "parts"');
        $this->addSql('DROP TABLE "parts"');
        $this->addSql('CREATE TABLE "parts" (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(255) NOT NULL, last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, needs_review BOOLEAN NOT NULL, tags CLOB NOT NULL, mass DOUBLE PRECISION DEFAULT NULL, ipn VARCHAR(100) DEFAULT NULL, description CLOB NOT NULL, comment CLOB NOT NULL, visible BOOLEAN NOT NULL, favorite BOOLEAN NOT NULL, minamount DOUBLE PRECISION NOT NULL, manufacturer_product_url CLOB NOT NULL, manufacturer_product_number VARCHAR(255) NOT NULL, manufacturing_status VARCHAR(255) DEFAULT NULL, order_quantity INTEGER NOT NULL, manual_order BOOLEAN NOT NULL, provider_reference_provider_key VARCHAR(255) DEFAULT NULL, provider_reference_provider_id VARCHAR(255) DEFAULT NULL, provider_reference_provider_url VARCHAR(2048) DEFAULT NULL, provider_reference_last_updated DATETIME DEFAULT NULL, eda_info_reference_prefix VARCHAR(255) DEFAULT NULL, eda_info_value VARCHAR(255) DEFAULT NULL, eda_info_invisible BOOLEAN DEFAULT NULL, eda_info_exclude_from_bom BOOLEAN DEFAULT NULL, eda_info_exclude_from_board BOOLEAN DEFAULT NULL, eda_info_exclude_from_sim BOOLEAN DEFAULT NULL, eda_info_kicad_symbol VARCHAR(255) DEFAULT NULL, eda_info_kicad_footprint VARCHAR(255) DEFAULT NULL, id_preview_attachment INTEGER DEFAULT NULL, id_part_custom_state INTEGER DEFAULT NULL, id_category INTEGER NOT NULL, id_footprint INTEGER DEFAULT NULL, id_part_unit INTEGER DEFAULT NULL, id_manufacturer INTEGER DEFAULT NULL, order_orderdetails_id INTEGER DEFAULT NULL, built_project_id INTEGER DEFAULT NULL, CONSTRAINT FK_6940A7FEEA7100A1 FOREIGN KEY (id_preview_attachment) REFERENCES "attachments" (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_6940A7FEA3ED1215 FOREIGN KEY (id_part_custom_state) REFERENCES "part_custom_states" (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_6940A7FE5697F554 FOREIGN KEY (id_category) REFERENCES "categories" (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_6940A7FE7E371A10 FOREIGN KEY (id_footprint) REFERENCES "footprints" (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_6940A7FE2626CEF9 FOREIGN KEY (id_part_unit) REFERENCES "measurement_units" (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_6940A7FE1ECB93AE FOREIGN KEY (id_manufacturer) REFERENCES "manufacturers" (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_6940A7FE81081E9B FOREIGN KEY (order_orderdetails_id) REFERENCES "orderdetails" (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_6940A7FEE8AE70D9 FOREIGN KEY (built_project_id) REFERENCES projects (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
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
        $this->addSql('CREATE TEMPORARY TABLE __temp__orderdetails AS SELECT id, supplierpartnr, obsolete, supplier_product_url, last_modified, datetime_added, part_id, id_supplier FROM "orderdetails"');
        $this->addSql('DROP TABLE "orderdetails"');
        $this->addSql('CREATE TABLE "orderdetails" (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, supplierpartnr VARCHAR(255) NOT NULL, obsolete BOOLEAN NOT NULL, supplier_product_url CLOB NOT NULL, last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, part_id INTEGER NOT NULL, id_supplier INTEGER DEFAULT NULL, CONSTRAINT FK_489AFCDC4CE34BEC FOREIGN KEY (part_id) REFERENCES "parts" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_489AFCDCCBF180EB FOREIGN KEY (id_supplier) REFERENCES "suppliers" (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO "orderdetails" (id, supplierpartnr, obsolete, supplier_product_url, last_modified, datetime_added, part_id, id_supplier) SELECT id, supplierpartnr, obsolete, supplier_product_url, last_modified, datetime_added, part_id, id_supplier FROM __temp__orderdetails');
        $this->addSql('DROP TABLE __temp__orderdetails');
        $this->addSql('CREATE INDEX IDX_489AFCDC4CE34BEC ON "orderdetails" (part_id)');
        $this->addSql('CREATE INDEX IDX_489AFCDCCBF180EB ON "orderdetails" (id_supplier)');
        $this->addSql('CREATE INDEX orderdetails_supplier_part_nr ON "orderdetails" (supplierpartnr)');
    }

    public function postgreSQLUp(Schema $schema): void
    {
        $this->addSql('ALTER TABLE attachment_types ADD allowed_targets TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE part_lots ADD last_stocktake_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE parts ADD gtin VARCHAR(255) DEFAULT NULL');
        $this->addSql('CREATE INDEX parts_idx_gtin ON parts (gtin)');
        $this->addSql('ALTER TABLE orderdetails ADD prices_includes_vat BOOLEAN DEFAULT NULL');
    }

    public function postgreSQLDown(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "attachment_types" DROP allowed_targets');
        $this->addSql('ALTER TABLE part_lots DROP last_stocktake_at');
        $this->addSql('DROP INDEX parts_idx_gtin');
        $this->addSql('ALTER TABLE "parts" DROP gtin');
        $this->addSql('ALTER TABLE "orderdetails" DROP prices_includes_vat');
    }
}
