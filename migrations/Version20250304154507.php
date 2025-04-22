<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Migration\AbstractMultiPlatformMigration;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250304154507 extends AbstractMultiPlatformMigration
{
    public function getDescription(): string
    {
        return 'Add built_assembly_id to parts table';
    }

    public function mySQLUp(Schema $schema): void
    {
        $this->addSql('ALTER TABLE parts ADD built_assembly_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE parts ADD CONSTRAINT FK_6940A7FECC660B3C FOREIGN KEY (built_assembly_id) REFERENCES assemblies (id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_6940A7FECC660B3C ON parts (built_assembly_id)');
    }

    public function mySQLDown(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `parts` DROP FOREIGN KEY FK_6940A7FECC660B3C');
        $this->addSql('DROP INDEX UNIQ_6940A7FECC660B3C ON `parts`');
        $this->addSql('ALTER TABLE `parts` DROP built_assembly_id');
    }

    public function sqLiteUp(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
        CREATE TEMPORARY TABLE __temp__parts AS 
            SELECT 
                id,
                id_preview_attachment,
                id_category,
                id_footprint,
                id_part_unit,
                id_manufacturer,
                order_orderdetails_id,
                built_project_id,
                datetime_added,
                name,
                last_modified,
                needs_review,
                tags,
                mass,
                description,
                comment,
                visible,
                favorite,
                minamount,
                manufacturer_product_url,
                manufacturer_product_number,
                manufacturing_status,
                order_quantity,
                manual_order,
                ipn,
                provider_reference_provider_key,
                provider_reference_provider_id,
                provider_reference_provider_url,
                provider_reference_last_updated,
                eda_info_reference_prefix,
                eda_info_value,
                eda_info_invisible,
                eda_info_exclude_from_bom,
                eda_info_exclude_from_board,
                eda_info_exclude_from_sim,
                eda_info_kicad_symbol,
                eda_info_kicad_footprint 
            FROM parts
        SQL);
        $this->addSql('DROP TABLE parts');

        $this->addSql(<<<'SQL'
        CREATE TABLE "parts"
        (
            id                              INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
            id_preview_attachment           INTEGER          DEFAULT NULL,
            id_category                     INTEGER          NOT NULL,
            id_footprint                    INTEGER          DEFAULT NULL,
            id_part_unit                    INTEGER          DEFAULT NULL,
            id_manufacturer                 INTEGER          DEFAULT NULL,
            order_orderdetails_id           INTEGER          DEFAULT NULL,
            built_project_id                INTEGER          DEFAULT NULL,
            built_assembly_id               INTEGER          DEFAULT NULL,
            datetime_added                  DATETIME         DEFAULT CURRENT_TIMESTAMP NOT NULL,
            name                            VARCHAR(255)                               NOT NULL,
            last_modified                   DATETIME         DEFAULT CURRENT_TIMESTAMP NOT NULL,
            needs_review                    BOOLEAN                                    NOT NULL,
            tags                            CLOB                                       NOT NULL,
            mass                            DOUBLE PRECISION DEFAULT NULL,
            description                     CLOB                                       NOT NULL,
            comment                         CLOB                                       NOT NULL,
            visible                         BOOLEAN                                    NOT NULL,
            favorite                        BOOLEAN                                    NOT NULL,
            minamount                       DOUBLE PRECISION                           NOT NULL,
            manufacturer_product_url        CLOB                                       NOT NULL,
            manufacturer_product_number     VARCHAR(255)                               NOT NULL,
            manufacturing_status            VARCHAR(255)     DEFAULT NULL,
            order_quantity                  INTEGER                                    NOT NULL,
            manual_order                    BOOLEAN                                    NOT NULL,
            ipn                             VARCHAR(100)     DEFAULT NULL,
            provider_reference_provider_key VARCHAR(255)     DEFAULT NULL,
            provider_reference_provider_id  VARCHAR(255)     DEFAULT NULL,
            provider_reference_provider_url VARCHAR(255)     DEFAULT NULL,
            provider_reference_last_updated DATETIME         DEFAULT NULL,
            eda_info_reference_prefix       VARCHAR(255)     DEFAULT NULL,
            eda_info_value                  VARCHAR(255)     DEFAULT NULL,
            eda_info_invisible              BOOLEAN          DEFAULT NULL,
            eda_info_exclude_from_bom       BOOLEAN          DEFAULT NULL,
            eda_info_exclude_from_board     BOOLEAN          DEFAULT NULL,
            eda_info_exclude_from_sim       BOOLEAN          DEFAULT NULL,
            eda_info_kicad_symbol           VARCHAR(255)     DEFAULT NULL,
            eda_info_kicad_footprint        VARCHAR(255)     DEFAULT NULL,
            CONSTRAINT FK_6940A7FEEA7100A1 FOREIGN KEY (id_preview_attachment) REFERENCES "attachments" (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE,
            CONSTRAINT FK_6940A7FE5697F554 FOREIGN KEY (id_category) REFERENCES "categories" (id) NOT DEFERRABLE INITIALLY IMMEDIATE,
            CONSTRAINT FK_6940A7FE7E371A10 FOREIGN KEY (id_footprint) REFERENCES "footprints" (id) NOT DEFERRABLE INITIALLY IMMEDIATE,
            CONSTRAINT FK_6940A7FE2626CEF9 FOREIGN KEY (id_part_unit) REFERENCES "measurement_units" (id) NOT DEFERRABLE INITIALLY IMMEDIATE,
            CONSTRAINT FK_6940A7FE1ECB93AE FOREIGN KEY (id_manufacturer) REFERENCES "manufacturers" (id) NOT DEFERRABLE INITIALLY IMMEDIATE,
            CONSTRAINT FK_6940A7FE81081E9B FOREIGN KEY (order_orderdetails_id) REFERENCES "orderdetails" (id) NOT DEFERRABLE INITIALLY IMMEDIATE,
            CONSTRAINT FK_6940A7FEE8AE70D9 FOREIGN KEY (built_project_id) REFERENCES projects (id) NOT DEFERRABLE INITIALLY IMMEDIATE,
            CONSTRAINT FK_6940A7FECC660B3C FOREIGN KEY (built_assembly_id) REFERENCES assemblies (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        )
        SQL);

        $this->addSql(<<<'SQL'
        INSERT INTO parts (
            id,
            id_preview_attachment,
            id_category,
            id_footprint,
            id_part_unit,
            id_manufacturer,
            order_orderdetails_id,
            built_project_id,     
            datetime_added,               
            name,
            last_modified,
            needs_review,
            tags,
            mass,
            description,
            comment,
            visible,
            favorite,
            minamount,
            manufacturer_product_url,
            manufacturer_product_number,
            manufacturing_status,
            order_quantity,
            manual_order,       
            ipn,
            provider_reference_provider_key,
            provider_reference_provider_id,
            provider_reference_provider_url,
            provider_reference_last_updated,               
            eda_info_reference_prefix,
            eda_info_value,
            eda_info_invisible,
            eda_info_exclude_from_bom,
            eda_info_exclude_from_board,
            eda_info_exclude_from_sim,
            eda_info_kicad_symbol,
            eda_info_kicad_footprint
            ) SELECT * FROM __temp__parts
        SQL);
        $this->addSql('DROP TABLE __temp__parts');

        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_6940A7FE1ECB93AE ON "parts" (id_manufacturer)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_6940A7FE2626CEF9 ON "parts" (id_part_unit)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_6940A7FE5697F554 ON "parts" (id_category)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_6940A7FE7E371A10 ON "parts" (id_footprint)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_6940A7FEEA7100A1 ON "parts" (id_preview_attachment)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX UNIQ_6940A7FE3D721C14 ON "parts" (ipn)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX UNIQ_6940A7FE81081E9B ON "parts" (order_orderdetails_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX UNIQ_6940A7FEE8AE70D9 ON "parts" (built_project_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX UNIQ_6940A7FECC660B3C ON "parts" (built_assembly_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX parts_idx_datet_name_last_id_needs ON "parts" (datetime_added, name, last_modified, id, needs_review)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX parts_idx_ipn ON "parts" (ipn)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX parts_idx_name ON "parts" (name)
        SQL);
    }

    public function sqLiteDown(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
        CREATE TEMPORARY TABLE __temp__parts AS 
            SELECT 
                id,
                id_preview_attachment,
                id_category,
                id_footprint,
                id_part_unit,
                id_manufacturer,
                order_orderdetails_id,
                built_project_id,
                datetime_added,
                name,
                last_modified,
                needs_review,
                tags,
                mass,
                description,
                comment,
                visible,
                favorite,
                minamount,
                manufacturer_product_url,
                manufacturer_product_number,
                manufacturing_status,
                order_quantity,
                manual_order,
                ipn,
                provider_reference_provider_key,
                provider_reference_provider_id,
                provider_reference_provider_url,
                provider_reference_last_updated,
                eda_info_reference_prefix,
                eda_info_value,
                eda_info_invisible,
                eda_info_exclude_from_bom,
                eda_info_exclude_from_board,
                eda_info_exclude_from_sim,
                eda_info_kicad_symbol,
                eda_info_kicad_footprint
            FROM parts
        SQL);

        $this->addSql('DROP TABLE parts');

        $this->addSql(<<<'SQL'
        CREATE TABLE "parts"
        (
            id                              INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
            id_preview_attachment           INTEGER          DEFAULT NULL,
            id_category                     INTEGER          NOT NULL,
            id_footprint                    INTEGER          DEFAULT NULL,
            id_part_unit                    INTEGER          DEFAULT NULL,
            id_manufacturer                 INTEGER          DEFAULT NULL,
            order_orderdetails_id           INTEGER          DEFAULT NULL,
            built_project_id                INTEGER          DEFAULT NULL,
            datetime_added                  DATETIME         DEFAULT CURRENT_TIMESTAMP NOT NULL,
            name                            VARCHAR(255)                               NOT NULL,
            last_modified                   DATETIME         DEFAULT CURRENT_TIMESTAMP NOT NULL,
            needs_review                    BOOLEAN                                    NOT NULL,
            tags                            CLOB                                       NOT NULL,
            mass                            DOUBLE PRECISION DEFAULT NULL,
            description                     CLOB                                       NOT NULL,
            comment                         CLOB                                       NOT NULL,
            visible                         BOOLEAN                                    NOT NULL,
            favorite                        BOOLEAN                                    NOT NULL,
            minamount                       DOUBLE PRECISION                           NOT NULL,
            manufacturer_product_url        CLOB                                       NOT NULL,
            manufacturer_product_number     VARCHAR(255)                               NOT NULL,
            manufacturing_status            VARCHAR(255)     DEFAULT NULL,
            order_quantity                  INTEGER                                    NOT NULL,
            manual_order                    BOOLEAN                                    NOT NULL,
            ipn                             VARCHAR(100)     DEFAULT NULL,
            provider_reference_provider_key VARCHAR(255)     DEFAULT NULL,
            provider_reference_provider_id  VARCHAR(255)     DEFAULT NULL,
            provider_reference_provider_url VARCHAR(255)     DEFAULT NULL,
            provider_reference_last_updated DATETIME         DEFAULT NULL,
            eda_info_reference_prefix       VARCHAR(255)     DEFAULT NULL,
            eda_info_value                  VARCHAR(255)     DEFAULT NULL,
            eda_info_invisible              BOOLEAN          DEFAULT NULL,
            eda_info_exclude_from_bom       BOOLEAN          DEFAULT NULL,
            eda_info_exclude_from_board     BOOLEAN          DEFAULT NULL,
            eda_info_exclude_from_sim       BOOLEAN          DEFAULT NULL,
            eda_info_kicad_symbol           VARCHAR(255)     DEFAULT NULL,
            eda_info_kicad_footprint        VARCHAR(255)     DEFAULT NULL,
            CONSTRAINT FK_6940A7FEEA7100A1 FOREIGN KEY (id_preview_attachment) REFERENCES "attachments" (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE,
            CONSTRAINT FK_6940A7FE5697F554 FOREIGN KEY (id_category) REFERENCES "categories" (id) NOT DEFERRABLE INITIALLY IMMEDIATE,
            CONSTRAINT FK_6940A7FE7E371A10 FOREIGN KEY (id_footprint) REFERENCES "footprints" (id) NOT DEFERRABLE INITIALLY IMMEDIATE,
            CONSTRAINT FK_6940A7FE2626CEF9 FOREIGN KEY (id_part_unit) REFERENCES "measurement_units" (id) NOT DEFERRABLE INITIALLY IMMEDIATE,
            CONSTRAINT FK_6940A7FE1ECB93AE FOREIGN KEY (id_manufacturer) REFERENCES "manufacturers" (id) NOT DEFERRABLE INITIALLY IMMEDIATE,
            CONSTRAINT FK_6940A7FE81081E9B FOREIGN KEY (order_orderdetails_id) REFERENCES "orderdetails" (id) NOT DEFERRABLE INITIALLY IMMEDIATE,
            CONSTRAINT FK_6940A7FEE8AE70D9 FOREIGN KEY (built_project_id) REFERENCES projects (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        )
        SQL);

        $this->addSql(<<<'SQL'
        INSERT INTO parts (
            id,
            id_preview_attachment,
            id_category,
            id_footprint,
            id_part_unit,
            id_manufacturer,
            order_orderdetails_id,
            built_project_id,     
            datetime_added,               
            name,
            last_modified,
            needs_review,
            tags,
            mass,
            description,
            comment,
            visible,
            favorite,
            minamount,
            manufacturer_product_url,
            manufacturer_product_number,
            manufacturing_status,
            order_quantity,
            manual_order,       
            ipn,
            provider_reference_provider_key,
            provider_reference_provider_id,
            provider_reference_provider_url,
            provider_reference_last_updated,               
            eda_info_reference_prefix,
            eda_info_value,
            eda_info_invisible,
            eda_info_exclude_from_bom,
            eda_info_exclude_from_board,
            eda_info_exclude_from_sim,
            eda_info_kicad_symbol,
            eda_info_kicad_footprint
            ) SELECT * FROM __temp__parts
        SQL);

        $this->addSql('DROP TABLE __temp__parts');

        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_6940A7FE1ECB93AE ON "parts" (id_manufacturer)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_6940A7FE2626CEF9 ON "parts" (id_part_unit)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_6940A7FE5697F554 ON "parts" (id_category)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_6940A7FE7E371A10 ON "parts" (id_footprint)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_6940A7FEEA7100A1 ON "parts" (id_preview_attachment)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX UNIQ_6940A7FE3D721C14 ON "parts" (ipn)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX UNIQ_6940A7FE81081E9B ON "parts" (order_orderdetails_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX UNIQ_6940A7FEE8AE70D9 ON "parts" (built_project_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX parts_idx_datet_name_last_id_needs ON "parts" (datetime_added, name, last_modified, id, needs_review)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX parts_idx_ipn ON "parts" (ipn)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX parts_idx_name ON "parts" (name)
        SQL);
    }

    public function postgreSQLUp(Schema $schema): void
    {
        //Not needed
    }

    public function postgreSQLDown(Schema $schema): void
    {
        //Not needed
    }
}
