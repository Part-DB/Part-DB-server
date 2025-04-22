<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Migration\AbstractMultiPlatformMigration;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250304081039 extends AbstractMultiPlatformMigration
{
    public function getDescription(): string
    {
        return 'Add assemblies and assembly BOM entries';
    }

    public function mySQLUp(Schema $schema): void
    {
        $this->addSql('CREATE TABLE assemblies (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, comment LONGTEXT NOT NULL, not_selectable TINYINT(1) NOT NULL, alternative_names LONGTEXT DEFAULT NULL, order_quantity INT NOT NULL, status VARCHAR(64) DEFAULT NULL, order_only_missing_parts TINYINT(1) NOT NULL, description LONGTEXT NOT NULL, parent_id INT DEFAULT NULL, id_preview_attachment INT DEFAULT NULL, INDEX IDX_5F3832C0727ACA70 (parent_id), INDEX IDX_5F3832C0EA7100A1 (id_preview_attachment), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE assembly_bom_entries (id INT AUTO_INCREMENT NOT NULL, quantity DOUBLE PRECISION NOT NULL, mountnames LONGTEXT NOT NULL, name VARCHAR(255) DEFAULT NULL, comment LONGTEXT NOT NULL, price NUMERIC(11, 5) DEFAULT NULL, last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, id_assembly INT DEFAULT NULL, id_part INT DEFAULT NULL, price_currency_id INT DEFAULT NULL, INDEX IDX_8C74887E2F180363 (id_assembly), INDEX IDX_8C74887EC22F6CC4 (id_part), INDEX IDX_8C74887E3FFDCD60 (price_currency_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE assemblies ADD CONSTRAINT FK_5F3832C0727ACA70 FOREIGN KEY (parent_id) REFERENCES assemblies (id)');
        $this->addSql('ALTER TABLE assemblies ADD CONSTRAINT FK_5F3832C0EA7100A1 FOREIGN KEY (id_preview_attachment) REFERENCES `attachments` (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE assembly_bom_entries ADD CONSTRAINT FK_8C74887E2F180363 FOREIGN KEY (id_assembly) REFERENCES assemblies (id)');
        $this->addSql('ALTER TABLE assembly_bom_entries ADD CONSTRAINT FK_8C74887EC22F6CC4 FOREIGN KEY (id_part) REFERENCES `parts` (id)');
        $this->addSql('ALTER TABLE assembly_bom_entries ADD CONSTRAINT FK_8C74887E3FFDCD60 FOREIGN KEY (price_currency_id) REFERENCES currencies (id)');
    }

    public function mySQLDown(Schema $schema): void
    {
        $this->addSql('ALTER TABLE assemblies DROP FOREIGN KEY FK_5F3832C0727ACA70');
        $this->addSql('ALTER TABLE assemblies DROP FOREIGN KEY FK_5F3832C0EA7100A1');
        $this->addSql('ALTER TABLE assembly_bom_entries DROP FOREIGN KEY FK_8C74887E2F180363');
        $this->addSql('ALTER TABLE assembly_bom_entries DROP FOREIGN KEY FK_8C74887EC22F6CC4');
        $this->addSql('ALTER TABLE assembly_bom_entries DROP FOREIGN KEY FK_8C74887E3FFDCD60');
        $this->addSql('DROP TABLE assemblies');
        $this->addSql('DROP TABLE assembly_bom_entries');
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
        CREATE TABLE assembly_bom_entries (
            id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
            id_assembly INTEGER DEFAULT NULL,
            id_part INTEGER DEFAULT NULL,
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
        //Not needed
    }

    public function postgreSQLDown(Schema $schema): void
    {
        //Not needed
    }
}
