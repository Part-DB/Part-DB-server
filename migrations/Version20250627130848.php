<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Migration\AbstractMultiPlatformMigration;
use Doctrine\DBAL\Schema\Schema;

final class Version20250627130848 extends AbstractMultiPlatformMigration
{
    public function getDescription(): string
    {
        return 'Add id_referenced_assembly in assembly_bom_entries';
    }

    public function mySQLUp(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE assembly_bom_entries ADD id_referenced_assembly INT DEFAULT NULL AFTER id_project
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE assembly_bom_entries ADD CONSTRAINT FK_8C74887E22522999 FOREIGN KEY (id_referenced_assembly) REFERENCES assemblies (id) ON DELETE SET NULL
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_8C74887E22522999 ON assembly_bom_entries (id_referenced_assembly)
        SQL);
    }

    public function mySQLDown(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE assembly_bom_entries DROP FOREIGN KEY FK_8C74887E22522999
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX IDX_8C74887E22522999 ON assembly_bom_entries
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE assembly_bom_entries DROP id_referenced_assembly
        SQL);
    }

    public function sqLiteUp(Schema $schema): void
    {
        //nothing to do. Done via Version20250304081039
    }

    public function sqLiteDown(Schema $schema): void
    {
        //nothing to do. Done via Version20250304081039
    }

    public function postgreSQLUp(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE assembly_bom_entries ADD id_referenced_assembly INT DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE assembly_bom_entries ADD CONSTRAINT FK_8C74887E22522999 FOREIGN KEY (id_referenced_assembly) REFERENCES assemblies (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_8C74887E22522999 ON assembly_bom_entries (id_referenced_assembly)
        SQL);
    }

    public function postgreSQLDown(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE assembly_bom_entries DROP CONSTRAINT FK_8C74887E22522999
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX IDX_8C74887E22522999
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE assembly_bom_entries DROP id_referenced_assembly
        SQL);
    }
}
