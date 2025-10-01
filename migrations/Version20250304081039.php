<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Migration\AbstractMultiPlatformMigration;
use Doctrine\DBAL\Schema\Schema;

final class Version20250304081039 extends AbstractMultiPlatformMigration
{
    public function getDescription(): string
    {
        return 'Add assemblies and assembly BOM entries';
    }

    public function mySQLUp(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE assemblies (
                id INT AUTO_INCREMENT NOT NULL,
                parent_id INT DEFAULT NULL,
                id_preview_attachment INT DEFAULT NULL,
                name VARCHAR(255) NOT NULL,
                comment LONGTEXT NOT NULL,
                not_selectable TINYINT(1) NOT NULL,
                alternative_names LONGTEXT DEFAULT NULL,
                order_quantity INT NOT NULL,
                status VARCHAR(64) DEFAULT NULL,
                order_only_missing_parts TINYINT(1) NOT NULL,
                description LONGTEXT NOT NULL,
                last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
                datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
                INDEX IDX_5F3832C0727ACA70 (parent_id),
                INDEX IDX_5F3832C0EA7100A1 (id_preview_attachment),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE assembly_bom_entries (
                id INT AUTO_INCREMENT NOT NULL,
                id_assembly INT DEFAULT NULL,
                id_part INT DEFAULT NULL,
                id_project INT DEFAULT NULL,
                quantity DOUBLE PRECISION NOT NULL,
                mountnames LONGTEXT NOT NULL,
                name VARCHAR(255) DEFAULT NULL,
                comment LONGTEXT NOT NULL,
                price NUMERIC(11, 5) DEFAULT NULL,
                price_currency_id INT DEFAULT NULL,
                last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
                datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
                INDEX IDX_8C74887E2F180363 (id_assembly),
                INDEX IDX_8C74887EC22F6CC4 (id_part),
                INDEX IDX_8C74887EF12E799E (id_project),
                INDEX IDX_8C74887E3FFDCD60 (price_currency_id),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE assemblies ADD CONSTRAINT FK_5F3832C0727ACA70 FOREIGN KEY (parent_id) REFERENCES assemblies (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE assemblies ADD CONSTRAINT FK_5F3832C0EA7100A1 FOREIGN KEY (id_preview_attachment) REFERENCES `attachments` (id) ON DELETE SET NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE assembly_bom_entries ADD CONSTRAINT FK_8C74887E2F180363 FOREIGN KEY (id_assembly) REFERENCES assemblies (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE assembly_bom_entries ADD CONSTRAINT FK_8C74887EC22F6CC4 FOREIGN KEY (id_part) REFERENCES `parts` (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE assembly_bom_entries ADD CONSTRAINT FK_8C74887EF12E799E FOREIGN KEY (id_project) REFERENCES `projects` (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE assembly_bom_entries ADD CONSTRAINT FK_8C74887E3FFDCD60 FOREIGN KEY (price_currency_id) REFERENCES currencies (id)
        SQL);
    }

    public function mySQLDown(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE assemblies DROP FOREIGN KEY FK_5F3832C0727ACA70
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE assemblies DROP FOREIGN KEY FK_5F3832C0EA7100A1
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE assembly_bom_entries DROP FOREIGN KEY FK_8C74887E2F180363
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE assembly_bom_entries DROP FOREIGN KEY FK_8C74887EC22F6CC4
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE assembly_bom_entries DROP FOREIGN KEY FK_8C74887EF12E799E
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE assembly_bom_entries DROP FOREIGN KEY FK_8C74887E3FFDCD60
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE assemblies
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE assembly_bom_entries
        SQL);
    }

    public function sqLiteUp(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
        CREATE TABLE assemblies (
            id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
            parent_id INTEGER DEFAULT NULL,
            id_preview_attachment INTEGER DEFAULT NULL,
            order_quantity INTEGER NOT NULL,
            order_only_missing_parts BOOLEAN NOT NULL,
            comment CLOB NOT NULL,
            not_selectable BOOLEAN NOT NULL,
            name VARCHAR(255) NOT NULL,
            last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
            datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
            status VARCHAR(64) DEFAULT NULL,
            ipn VARCHAR(100) DEFAULT NULL,
            description CLOB NOT NULL,
            alternative_names CLOB DEFAULT NULL,
            CONSTRAINT FK_5F3832C0727ACA70 FOREIGN KEY (parent_id) REFERENCES assemblies (id) NOT DEFERRABLE INITIALLY IMMEDIATE,
            CONSTRAINT FK_5F3832C0EA7100A1 FOREIGN KEY (id_preview_attachment) REFERENCES "attachments" (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE
        )
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_5F3832C0727ACA70 ON assemblies (parent_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_5F3832C0EA7100A1 ON assemblies (id_preview_attachment)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX UNIQ_5F3832C03D721C14 ON assemblies (ipn)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX assembly_idx_ipn ON assemblies (ipn)
        SQL);

        $this->addSql(<<<'SQL'
        CREATE TABLE assembly_bom_entries (
            id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
            id_assembly INTEGER DEFAULT NULL,
            id_part INTEGER DEFAULT NULL,
            id_referenced_assembly INTEGER DEFAULT NULL,
            price_currency_id INTEGER DEFAULT NULL,
            quantity DOUBLE PRECISION NOT NULL,
            mountnames CLOB NOT NULL,
            name VARCHAR(255) DEFAULT NULL,
            comment CLOB NOT NULL,
            price NUMERIC(11, 5) DEFAULT NULL,
            last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
            datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
            CONSTRAINT FK_8C74887E4AD2039E FOREIGN KEY (id_assembly) REFERENCES assemblies (id) NOT DEFERRABLE INITIALLY IMMEDIATE,
            CONSTRAINT FK_8C74887EC22F6CC4 FOREIGN KEY (id_part) REFERENCES "parts" (id) NOT DEFERRABLE INITIALLY IMMEDIATE,
            CONSTRAINT FK_8C74887E22522999 FOREIGN KEY (id_referenced_assembly) REFERENCES assemblies (id) ON UPDATE NO ACTION ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE,
            CONSTRAINT FK_8C74887E3FFDCD60 FOREIGN KEY (price_currency_id) REFERENCES currencies (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        )
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_8C74887E4AD2039E ON assembly_bom_entries (id_assembly)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_8C74887EC22F6CC4 ON assembly_bom_entries (id_part)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_8C74887E22522999 ON assembly_bom_entries (id_referenced_assembly)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_8C74887E3FFDCD60 ON assembly_bom_entries (price_currency_id)
        SQL);
    }

    public function sqLiteDown(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            DROP TABLE assembly_bom_entries
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE assemblies
        SQL);
    }

    public function postgreSQLUp(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE assemblies (
                id INT GENERATED BY DEFAULT AS IDENTITY NOT NULL,
                name VARCHAR(255) NOT NULL,
                last_modified TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL,
                datetime_added TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL,
                comment TEXT NOT NULL,
                not_selectable BOOLEAN NOT NULL,
                alternative_names TEXT DEFAULT NULL,
                order_quantity INT NOT NULL,
                status VARCHAR(64) DEFAULT NULL,
                order_only_missing_parts BOOLEAN NOT NULL,
                description TEXT NOT NULL,
                parent_id INT DEFAULT NULL,
                id_preview_attachment INT DEFAULT NULL,
                PRIMARY KEY(id)
            )
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_5F3832C0727ACA70 ON assemblies (parent_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_5F3832C0EA7100A1 ON assemblies (id_preview_attachment)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE assembly_bom_entries (
                id INT GENERATED BY DEFAULT AS IDENTITY NOT NULL,
                id_assembly INT DEFAULT NULL,
                id_part INT DEFAULT NULL,
                quantity DOUBLE PRECISION NOT NULL,
                mountnames TEXT NOT NULL,
                name VARCHAR(255) DEFAULT NULL,
                comment TEXT NOT NULL,
                price NUMERIC(11, 5) DEFAULT NULL,
                price_currency_id INT DEFAULT NULL,
                last_modified TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL,
                datetime_added TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL,
                PRIMARY KEY(id)
            )
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_8C74887E4AD2039E ON assembly_bom_entries (id_assembly)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_8C74887EC22F6CC4 ON assembly_bom_entries (id_part)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_8C74887E3FFDCD60 ON assembly_bom_entries (price_currency_id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE assemblies ADD CONSTRAINT FK_5F3832C0727ACA70 FOREIGN KEY (parent_id) REFERENCES assemblies (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE assemblies ADD CONSTRAINT FK_5F3832C0EA7100A1 FOREIGN KEY (id_preview_attachment) REFERENCES "attachments" (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE assembly_bom_entries ADD CONSTRAINT FK_8C74887E4AD2039E FOREIGN KEY (id_assembly) REFERENCES assemblies (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE assembly_bom_entries ADD CONSTRAINT FK_8C74887EC22F6CC4 FOREIGN KEY (id_part) REFERENCES "parts" (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE assembly_bom_entries ADD CONSTRAINT FK_8C74887E3FFDCD60 FOREIGN KEY (price_currency_id) REFERENCES currencies (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
    }

    public function postgreSQLDown(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE assemblies DROP CONSTRAINT FK_5F3832C0727ACA70
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE assemblies DROP CONSTRAINT FK_5F3832C0EA7100A1
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE assembly_bom_entries DROP CONSTRAINT FK_8C74887E4AD2039E
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE assembly_bom_entries DROP CONSTRAINT FK_8C74887EC22F6CC4
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE assembly_bom_entries DROP CONSTRAINT FK_8C74887EF12E799E
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE assembly_bom_entries DROP CONSTRAINT FK_8C74887E3FFDCD60
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE assemblies
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE assembly_bom_entries
        SQL);
    }
}
