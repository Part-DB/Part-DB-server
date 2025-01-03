<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Migration\AbstractMultiPlatformMigration;
use Doctrine\DBAL\Schema\Schema;

final class Version20230730131708 extends AbstractMultiPlatformMigration
{
    public function getDescription(): string
    {
        return 'Allow longer fields for manufacturer and supplier product urls, allow client credentials grant to have null refresh token';
    }

    public function mySQLUp(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE oauth_tokens CHANGE token token LONGTEXT DEFAULT NULL, CHANGE refresh_token refresh_token LONGTEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE orderdetails CHANGE supplier_product_url supplier_product_url LONGTEXT NOT NULL');
        $this->addSql('ALTER TABLE parts CHANGE manufacturer_product_url manufacturer_product_url LONGTEXT NOT NULL');
    }

    public function mySQLDown(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE oauth_tokens CHANGE token token VARCHAR(255) DEFAULT NULL, CHANGE refresh_token refresh_token VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE `orderdetails` CHANGE supplier_product_url supplier_product_url VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE `parts` CHANGE manufacturer_product_url manufacturer_product_url VARCHAR(255) NOT NULL');
    }

    public function sqLiteUp(Schema $schema): void
    {
        $this->addSql('CREATE TEMPORARY TABLE __temp__oauth_tokens AS SELECT id, token, expires_at, refresh_token, name, last_modified, datetime_added FROM oauth_tokens');
        $this->addSql('DROP TABLE oauth_tokens');
        $this->addSql('CREATE TABLE oauth_tokens (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, token CLOB DEFAULT NULL, expires_at DATETIME DEFAULT NULL --(DC2Type:datetime_immutable)
        , refresh_token CLOB DEFAULT NULL, name VARCHAR(255) NOT NULL, last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL)');
        $this->addSql('INSERT INTO oauth_tokens (id, token, expires_at, refresh_token, name, last_modified, datetime_added) SELECT id, token, expires_at, refresh_token, name, last_modified, datetime_added FROM __temp__oauth_tokens');
        $this->addSql('DROP TABLE __temp__oauth_tokens');
        $this->addSql('CREATE UNIQUE INDEX oauth_tokens_unique_name ON oauth_tokens (name)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__orderdetails AS SELECT id, part_id, id_supplier, supplierpartnr, obsolete, supplier_product_url, last_modified, datetime_added FROM orderdetails');
        $this->addSql('DROP TABLE orderdetails');
        $this->addSql('CREATE TABLE orderdetails (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, part_id INTEGER NOT NULL, id_supplier INTEGER DEFAULT NULL, supplierpartnr VARCHAR(255) NOT NULL, obsolete BOOLEAN NOT NULL, supplier_product_url CLOB NOT NULL, last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, CONSTRAINT FK_489AFCDC4CE34BEC FOREIGN KEY (part_id) REFERENCES parts (id) ON UPDATE NO ACTION ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_489AFCDCCBF180EB FOREIGN KEY (id_supplier) REFERENCES suppliers (id) ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO orderdetails (id, part_id, id_supplier, supplierpartnr, obsolete, supplier_product_url, last_modified, datetime_added) SELECT id, part_id, id_supplier, supplierpartnr, obsolete, supplier_product_url, last_modified, datetime_added FROM __temp__orderdetails');
        $this->addSql('DROP TABLE __temp__orderdetails');
        $this->addSql('CREATE INDEX orderdetails_supplier_part_nr ON orderdetails (supplierpartnr)');
        $this->addSql('CREATE INDEX IDX_489AFCDC4CE34BEC ON orderdetails (part_id)');
        $this->addSql('CREATE INDEX IDX_489AFCDCCBF180EB ON orderdetails (id_supplier)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__parts AS SELECT id, id_preview_attachment, id_category, id_footprint, id_part_unit, id_manufacturer, order_orderdetails_id, built_project_id, datetime_added, name, last_modified, needs_review, tags, mass, description, comment, visible, favorite, minamount, manufacturer_product_url, manufacturer_product_number, manufacturing_status, order_quantity, manual_order, ipn, provider_reference_provider_key, provider_reference_provider_id, provider_reference_provider_url, provider_reference_last_updated FROM parts');
        $this->addSql('DROP TABLE parts');
        $this->addSql('CREATE TABLE parts (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, id_preview_attachment INTEGER DEFAULT NULL, id_category INTEGER NOT NULL, id_footprint INTEGER DEFAULT NULL, id_part_unit INTEGER DEFAULT NULL, id_manufacturer INTEGER DEFAULT NULL, order_orderdetails_id INTEGER DEFAULT NULL, built_project_id INTEGER DEFAULT NULL, datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, name VARCHAR(255) NOT NULL, last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, needs_review BOOLEAN NOT NULL, tags CLOB NOT NULL, mass DOUBLE PRECISION DEFAULT NULL, description CLOB NOT NULL, comment CLOB NOT NULL, visible BOOLEAN NOT NULL, favorite BOOLEAN NOT NULL, minamount DOUBLE PRECISION NOT NULL, manufacturer_product_url CLOB NOT NULL, manufacturer_product_number VARCHAR(255) NOT NULL, manufacturing_status VARCHAR(255) DEFAULT NULL, order_quantity INTEGER NOT NULL, manual_order BOOLEAN NOT NULL, ipn VARCHAR(100) DEFAULT NULL, provider_reference_provider_key VARCHAR(255) DEFAULT NULL, provider_reference_provider_id VARCHAR(255) DEFAULT NULL, provider_reference_provider_url VARCHAR(255) DEFAULT NULL, provider_reference_last_updated DATETIME DEFAULT NULL, CONSTRAINT FK_6940A7FE5697F554 FOREIGN KEY (id_category) REFERENCES categories (id) ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_6940A7FE7E371A10 FOREIGN KEY (id_footprint) REFERENCES footprints (id) ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_6940A7FE2626CEF9 FOREIGN KEY (id_part_unit) REFERENCES measurement_units (id) ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_6940A7FE1ECB93AE FOREIGN KEY (id_manufacturer) REFERENCES manufacturers (id) ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_6940A7FE81081E9B FOREIGN KEY (order_orderdetails_id) REFERENCES orderdetails (id) ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_6940A7FEE8AE70D9 FOREIGN KEY (built_project_id) REFERENCES projects (id) ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_6940A7FEEA7100A1 FOREIGN KEY (id_preview_attachment) REFERENCES attachments (id) ON UPDATE NO ACTION ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO parts (id, id_preview_attachment, id_category, id_footprint, id_part_unit, id_manufacturer, order_orderdetails_id, built_project_id, datetime_added, name, last_modified, needs_review, tags, mass, description, comment, visible, favorite, minamount, manufacturer_product_url, manufacturer_product_number, manufacturing_status, order_quantity, manual_order, ipn, provider_reference_provider_key, provider_reference_provider_id, provider_reference_provider_url, provider_reference_last_updated) SELECT id, id_preview_attachment, id_category, id_footprint, id_part_unit, id_manufacturer, order_orderdetails_id, built_project_id, datetime_added, name, last_modified, needs_review, tags, mass, description, comment, visible, favorite, minamount, manufacturer_product_url, manufacturer_product_number, manufacturing_status, order_quantity, manual_order, ipn, provider_reference_provider_key, provider_reference_provider_id, provider_reference_provider_url, provider_reference_last_updated FROM __temp__parts');
        $this->addSql('DROP TABLE __temp__parts');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_6940A7FEE8AE70D9 ON parts (built_project_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_6940A7FE81081E9B ON parts (order_orderdetails_id)');
        $this->addSql('CREATE INDEX IDX_6940A7FE1ECB93AE ON parts (id_manufacturer)');
        $this->addSql('CREATE INDEX IDX_6940A7FE2626CEF9 ON parts (id_part_unit)');
        $this->addSql('CREATE INDEX IDX_6940A7FE7E371A10 ON parts (id_footprint)');
        $this->addSql('CREATE INDEX IDX_6940A7FE5697F554 ON parts (id_category)');
        $this->addSql('CREATE INDEX parts_idx_datet_name_last_id_needs ON parts (datetime_added, name, last_modified, id, needs_review)');
        $this->addSql('CREATE INDEX parts_idx_name ON parts (name)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_6940A7FE3D721C14 ON parts (ipn)');
        $this->addSql('CREATE INDEX parts_idx_ipn ON parts (ipn)');
        $this->addSql('CREATE INDEX IDX_6940A7FEEA7100A1 ON parts (id_preview_attachment)');
    }

    public function sqLiteDown(Schema $schema): void
    {
        $this->addSql('CREATE TEMPORARY TABLE __temp__oauth_tokens AS SELECT id, token, expires_at, refresh_token, name, last_modified, datetime_added FROM oauth_tokens');
        $this->addSql('DROP TABLE oauth_tokens');
        $this->addSql('CREATE TABLE oauth_tokens (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, token VARCHAR(255) DEFAULT NULL, expires_at DATETIME DEFAULT NULL --(DC2Type:datetime_immutable)
        , refresh_token VARCHAR(255) NOT NULL, name VARCHAR(255) NOT NULL, last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL)');
        $this->addSql('INSERT INTO oauth_tokens (id, token, expires_at, refresh_token, name, last_modified, datetime_added) SELECT id, token, expires_at, refresh_token, name, last_modified, datetime_added FROM __temp__oauth_tokens');
        $this->addSql('DROP TABLE __temp__oauth_tokens');
        $this->addSql('CREATE UNIQUE INDEX oauth_tokens_unique_name ON oauth_tokens (name)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__orderdetails AS SELECT id, part_id, id_supplier, supplierpartnr, obsolete, supplier_product_url, last_modified, datetime_added FROM "orderdetails"');
        $this->addSql('DROP TABLE "orderdetails"');
        $this->addSql('CREATE TABLE "orderdetails" (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, part_id INTEGER NOT NULL, id_supplier INTEGER DEFAULT NULL, supplierpartnr VARCHAR(255) NOT NULL, obsolete BOOLEAN NOT NULL, supplier_product_url VARCHAR(255) NOT NULL, last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, CONSTRAINT FK_489AFCDC4CE34BEC FOREIGN KEY (part_id) REFERENCES "parts" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_489AFCDCCBF180EB FOREIGN KEY (id_supplier) REFERENCES "suppliers" (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO "orderdetails" (id, part_id, id_supplier, supplierpartnr, obsolete, supplier_product_url, last_modified, datetime_added) SELECT id, part_id, id_supplier, supplierpartnr, obsolete, supplier_product_url, last_modified, datetime_added FROM __temp__orderdetails');
        $this->addSql('DROP TABLE __temp__orderdetails');
        $this->addSql('CREATE INDEX IDX_489AFCDC4CE34BEC ON "orderdetails" (part_id)');
        $this->addSql('CREATE INDEX IDX_489AFCDCCBF180EB ON "orderdetails" (id_supplier)');
        $this->addSql('CREATE INDEX orderdetails_supplier_part_nr ON "orderdetails" (supplierpartnr)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__parts AS SELECT id, id_preview_attachment, id_category, id_footprint, id_part_unit, id_manufacturer, order_orderdetails_id, built_project_id, name, last_modified, datetime_added, needs_review, tags, mass, ipn, description, comment, visible, favorite, minamount, manufacturer_product_url, manufacturer_product_number, manufacturing_status, order_quantity, manual_order, provider_reference_provider_key, provider_reference_provider_id, provider_reference_provider_url, provider_reference_last_updated FROM "parts"');
        $this->addSql('DROP TABLE "parts"');
        $this->addSql('CREATE TABLE "parts" (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, id_preview_attachment INTEGER DEFAULT NULL, id_category INTEGER NOT NULL, id_footprint INTEGER DEFAULT NULL, id_part_unit INTEGER DEFAULT NULL, id_manufacturer INTEGER DEFAULT NULL, order_orderdetails_id INTEGER DEFAULT NULL, built_project_id INTEGER DEFAULT NULL, name VARCHAR(255) NOT NULL, last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, needs_review BOOLEAN NOT NULL, tags CLOB NOT NULL, mass DOUBLE PRECISION DEFAULT NULL, ipn VARCHAR(100) DEFAULT NULL, description CLOB NOT NULL, comment CLOB NOT NULL, visible BOOLEAN NOT NULL, favorite BOOLEAN NOT NULL, minamount DOUBLE PRECISION NOT NULL, manufacturer_product_url VARCHAR(255) NOT NULL, manufacturer_product_number VARCHAR(255) NOT NULL, manufacturing_status VARCHAR(255) DEFAULT NULL, order_quantity INTEGER NOT NULL, manual_order BOOLEAN NOT NULL, provider_reference_provider_key VARCHAR(255) DEFAULT NULL, provider_reference_provider_id VARCHAR(255) DEFAULT NULL, provider_reference_provider_url VARCHAR(255) DEFAULT NULL, provider_reference_last_updated DATETIME DEFAULT NULL, CONSTRAINT FK_6940A7FEEA7100A1 FOREIGN KEY (id_preview_attachment) REFERENCES "attachments" (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_6940A7FE5697F554 FOREIGN KEY (id_category) REFERENCES "categories" (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_6940A7FE7E371A10 FOREIGN KEY (id_footprint) REFERENCES "footprints" (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_6940A7FE2626CEF9 FOREIGN KEY (id_part_unit) REFERENCES "measurement_units" (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_6940A7FE1ECB93AE FOREIGN KEY (id_manufacturer) REFERENCES "manufacturers" (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_6940A7FE81081E9B FOREIGN KEY (order_orderdetails_id) REFERENCES "orderdetails" (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_6940A7FEE8AE70D9 FOREIGN KEY (built_project_id) REFERENCES projects (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
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

    public function postgreSQLUp(Schema $schema): void
    {
        $this->warnIf(true, "Migration not needed for Postgres. Skipping...");
    }

    public function postgreSQLDown(Schema $schema): void
    {
        $this->warnIf(true, "Migration not needed for Postgres. Skipping...");
    }
}
