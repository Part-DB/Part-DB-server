<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250310160354 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE assembly_bom_entries RENAME INDEX idx_8c74887e2f180363 TO IDX_8C74887E4AD2039E');
        $this->addSql('ALTER TABLE project_bom_entries ADD id_assembly INT DEFAULT NULL AFTER id_part');
        $this->addSql('ALTER TABLE project_bom_entries ADD CONSTRAINT FK_1AA2DD314AD2039E FOREIGN KEY (id_assembly) REFERENCES assemblies (id)');
        $this->addSql('CREATE INDEX IDX_1AA2DD314AD2039E ON project_bom_entries (id_assembly)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE assembly_bom_entries RENAME INDEX idx_8c74887e4ad2039e TO IDX_8C74887E2F180363');
        $this->addSql('ALTER TABLE project_bom_entries DROP FOREIGN KEY FK_1AA2DD314AD2039E');
        $this->addSql('DROP INDEX IDX_1AA2DD314AD2039E ON project_bom_entries');
        $this->addSql('ALTER TABLE project_bom_entries DROP id_assembly');
    }
}
