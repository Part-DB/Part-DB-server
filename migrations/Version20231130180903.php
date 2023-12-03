<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Migration\AbstractMultiPlatformMigration;
use Doctrine\DBAL\Schema\Schema;

final class Version20231130180903 extends AbstractMultiPlatformMigration
{
    public function getDescription(): string
    {
        return 'Added EDA fields';
    }

    public function mySQLUp(Schema $schema): void
    {
        $this->addSql('ALTER TABLE categories ADD eda_info_reference_prefix VARCHAR(255) DEFAULT NULL, ADD eda_info_invisible TINYINT(1) DEFAULT NULL, ADD eda_info_exclude_from_bom TINYINT(1) DEFAULT NULL, ADD eda_info_exclude_from_board TINYINT(1) DEFAULT NULL, ADD eda_info_exclude_from_sim TINYINT(1) DEFAULT NULL, ADD eda_info_kicad_symbol VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE footprints ADD eda_info_kicad_footprint VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE parts ADD eda_info_reference_prefix VARCHAR(255) DEFAULT NULL, ADD eda_info_value VARCHAR(255) DEFAULT NULL, ADD eda_info_invisible TINYINT(1) DEFAULT NULL, ADD eda_info_exclude_from_bom TINYINT(1) DEFAULT NULL, ADD eda_info_exclude_from_board TINYINT(1) DEFAULT NULL, ADD eda_info_exclude_from_sim TINYINT(1) DEFAULT NULL, ADD eda_info_kicad_symbol VARCHAR(255) DEFAULT NULL, ADD eda_info_kicad_footprint VARCHAR(255) DEFAULT NULL');
    }

    public function mySQLDown(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `categories` DROP eda_info_reference_prefix, DROP eda_info_invisible, DROP eda_info_exclude_from_bom, DROP eda_info_exclude_from_board, DROP eda_info_exclude_from_sim, DROP eda_info_kicad_symbol');
        $this->addSql('ALTER TABLE `footprints` DROP eda_info_kicad_footprint');
        $this->addSql('ALTER TABLE `parts` DROP eda_info_reference_prefix, DROP eda_info_value, DROP eda_info_invisible, DROP eda_info_exclude_from_bom, DROP eda_info_exclude_from_board, DROP eda_info_exclude_from_sim, DROP eda_info_kicad_symbol, DROP eda_info_kicad_footprint');
    }

    public function sqLiteUp(Schema $schema): void
    {
        $this->addSql('ALTER TABLE categories ADD COLUMN eda_info_reference_prefix VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE categories ADD COLUMN eda_info_invisible BOOLEAN DEFAULT NULL');
        $this->addSql('ALTER TABLE categories ADD COLUMN eda_info_exclude_from_bom BOOLEAN DEFAULT NULL');
        $this->addSql('ALTER TABLE categories ADD COLUMN eda_info_exclude_from_board BOOLEAN DEFAULT NULL');
        $this->addSql('ALTER TABLE categories ADD COLUMN eda_info_exclude_from_sim BOOLEAN DEFAULT NULL');
        $this->addSql('ALTER TABLE categories ADD COLUMN eda_info_kicad_symbol VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE footprints ADD COLUMN eda_info_kicad_footprint VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE parts ADD COLUMN eda_info_reference_prefix VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE parts ADD COLUMN eda_info_value VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE parts ADD COLUMN eda_info_invisible BOOLEAN DEFAULT NULL');
        $this->addSql('ALTER TABLE parts ADD COLUMN eda_info_exclude_from_bom BOOLEAN DEFAULT NULL');
        $this->addSql('ALTER TABLE parts ADD COLUMN eda_info_exclude_from_board BOOLEAN DEFAULT NULL');
        $this->addSql('ALTER TABLE parts ADD COLUMN eda_info_exclude_from_sim BOOLEAN DEFAULT NULL');
        $this->addSql('ALTER TABLE parts ADD COLUMN eda_info_kicad_symbol VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE parts ADD COLUMN eda_info_kicad_footprint VARCHAR(255) DEFAULT NULL');
    }

    public function sqLiteDown(Schema $schema): void
    {
        $this->addSql('CREATE TEMPORARY TABLE __temp__categories AS SELECT id, parent_id, id_preview_attachment, name, last_modified, datetime_added, comment, not_selectable, alternative_names, partname_hint, partname_regex, disable_footprints, disable_manufacturers, disable_autodatasheets, disable_properties, default_description, default_comment FROM "categories"');
        $this->addSql('DROP TABLE "categories"');
        $this->addSql('CREATE TABLE "categories" (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, parent_id INTEGER DEFAULT NULL, id_preview_attachment INTEGER DEFAULT NULL, name VARCHAR(255) NOT NULL, last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, comment CLOB NOT NULL, not_selectable BOOLEAN NOT NULL, alternative_names CLOB DEFAULT NULL, partname_hint CLOB NOT NULL, partname_regex CLOB NOT NULL, disable_footprints BOOLEAN NOT NULL, disable_manufacturers BOOLEAN NOT NULL, disable_autodatasheets BOOLEAN NOT NULL, disable_properties BOOLEAN NOT NULL, default_description CLOB NOT NULL, default_comment CLOB NOT NULL, CONSTRAINT FK_3AF34668727ACA70 FOREIGN KEY (parent_id) REFERENCES "categories" (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_3AF34668EA7100A1 FOREIGN KEY (id_preview_attachment) REFERENCES "attachments" (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO "categories" (id, parent_id, id_preview_attachment, name, last_modified, datetime_added, comment, not_selectable, alternative_names, partname_hint, partname_regex, disable_footprints, disable_manufacturers, disable_autodatasheets, disable_properties, default_description, default_comment) SELECT id, parent_id, id_preview_attachment, name, last_modified, datetime_added, comment, not_selectable, alternative_names, partname_hint, partname_regex, disable_footprints, disable_manufacturers, disable_autodatasheets, disable_properties, default_description, default_comment FROM __temp__categories');
        $this->addSql('DROP TABLE __temp__categories');
        $this->addSql('CREATE INDEX IDX_3AF34668727ACA70 ON "categories" (parent_id)');
        $this->addSql('CREATE INDEX IDX_3AF34668EA7100A1 ON "categories" (id_preview_attachment)');
        $this->addSql('CREATE INDEX category_idx_name ON "categories" (name)');
        $this->addSql('CREATE INDEX category_idx_parent_name ON "categories" (parent_id, name)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__footprints AS SELECT id, parent_id, id_preview_attachment, id_footprint_3d, name, last_modified, datetime_added, comment, not_selectable, alternative_names FROM "footprints"');
        $this->addSql('DROP TABLE "footprints"');
        $this->addSql('CREATE TABLE "footprints" (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, parent_id INTEGER DEFAULT NULL, id_preview_attachment INTEGER DEFAULT NULL, id_footprint_3d INTEGER DEFAULT NULL, name VARCHAR(255) NOT NULL, last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, comment CLOB NOT NULL, not_selectable BOOLEAN NOT NULL, alternative_names CLOB DEFAULT NULL, CONSTRAINT FK_A34D68A2727ACA70 FOREIGN KEY (parent_id) REFERENCES "footprints" (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_A34D68A2EA7100A1 FOREIGN KEY (id_preview_attachment) REFERENCES "attachments" (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_A34D68A232A38C34 FOREIGN KEY (id_footprint_3d) REFERENCES "attachments" (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO "footprints" (id, parent_id, id_preview_attachment, id_footprint_3d, name, last_modified, datetime_added, comment, not_selectable, alternative_names) SELECT id, parent_id, id_preview_attachment, id_footprint_3d, name, last_modified, datetime_added, comment, not_selectable, alternative_names FROM __temp__footprints');
        $this->addSql('DROP TABLE __temp__footprints');
        $this->addSql('CREATE INDEX IDX_A34D68A2727ACA70 ON "footprints" (parent_id)');
        $this->addSql('CREATE INDEX IDX_A34D68A2EA7100A1 ON "footprints" (id_preview_attachment)');
        $this->addSql('CREATE INDEX IDX_A34D68A232A38C34 ON "footprints" (id_footprint_3d)');
        $this->addSql('CREATE INDEX footprint_idx_name ON "footprints" (name)');
        $this->addSql('CREATE INDEX footprint_idx_parent_name ON "footprints" (parent_id, name)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__parts AS SELECT id, id_preview_attachment, id_category, id_footprint, id_part_unit, id_manufacturer, order_orderdetails_id, built_project_id, name, last_modified, datetime_added, needs_review, tags, mass, ipn, description, comment, visible, favorite, minamount, manufacturer_product_url, manufacturer_product_number, manufacturing_status, order_quantity, manual_order, provider_reference_provider_key, provider_reference_provider_id, provider_reference_provider_url, provider_reference_last_updated FROM "parts"');
        $this->addSql('DROP TABLE "parts"');
        $this->addSql('CREATE TABLE "parts" (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, id_preview_attachment INTEGER DEFAULT NULL, id_category INTEGER NOT NULL, id_footprint INTEGER DEFAULT NULL, id_part_unit INTEGER DEFAULT NULL, id_manufacturer INTEGER DEFAULT NULL, order_orderdetails_id INTEGER DEFAULT NULL, built_project_id INTEGER DEFAULT NULL, name VARCHAR(255) NOT NULL, last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, needs_review BOOLEAN NOT NULL, tags CLOB NOT NULL, mass DOUBLE PRECISION DEFAULT NULL, ipn VARCHAR(100) DEFAULT NULL, description CLOB NOT NULL, comment CLOB NOT NULL, visible BOOLEAN NOT NULL, favorite BOOLEAN NOT NULL, minamount DOUBLE PRECISION NOT NULL, manufacturer_product_url CLOB NOT NULL, manufacturer_product_number VARCHAR(255) NOT NULL, manufacturing_status VARCHAR(255) DEFAULT NULL, order_quantity INTEGER NOT NULL, manual_order BOOLEAN NOT NULL, provider_reference_provider_key VARCHAR(255) DEFAULT NULL, provider_reference_provider_id VARCHAR(255) DEFAULT NULL, provider_reference_provider_url VARCHAR(255) DEFAULT NULL, provider_reference_last_updated DATETIME DEFAULT NULL, CONSTRAINT FK_6940A7FEEA7100A1 FOREIGN KEY (id_preview_attachment) REFERENCES "attachments" (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_6940A7FE5697F554 FOREIGN KEY (id_category) REFERENCES "categories" (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_6940A7FE7E371A10 FOREIGN KEY (id_footprint) REFERENCES "footprints" (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_6940A7FE2626CEF9 FOREIGN KEY (id_part_unit) REFERENCES "measurement_units" (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_6940A7FE1ECB93AE FOREIGN KEY (id_manufacturer) REFERENCES "manufacturers" (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_6940A7FE81081E9B FOREIGN KEY (order_orderdetails_id) REFERENCES "orderdetails" (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_6940A7FEE8AE70D9 FOREIGN KEY (built_project_id) REFERENCES projects (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO "parts" (id, id_preview_attachment, id_category, id_footprint, id_part_unit, id_manufacturer, order_orderdetails_id, built_project_id, name, last_modified, datetime_added, needs_review, tags, mass, ipn, description, comment, visible, favorite, minamount, manufacturer_product_url, manufacturer_product_number, manufacturing_status, order_quantity, manual_order, provider_reference_provider_key, provider_reference_provider_id, provider_reference_provider_url, provider_reference_last_updated) SELECT id, id_preview_attachment, id_category, id_footprint, id_part_unit, id_manufacturer, order_orderdetails_id, built_project_id, name, last_modified, datetime_added, needs_review, tags, mass, ipn, description, comment, visible, favorite, minamount, manufacturer_product_url, manufacturer_product_number, manufacturing_status, order_quantity, manual_order, provider_reference_provider_key, provider_reference_provider_id, provider_reference_provider_url, provider_reference_last_updated FROM __temp__parts');
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
    }
}
