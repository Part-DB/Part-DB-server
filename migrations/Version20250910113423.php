<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Migration\AbstractMultiPlatformMigration;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250910113423 extends AbstractMultiPlatformMigration
{
    public function getDescription(): string
    {
        return 'Remove project_id from assembly_bom_entries because it is not needed.';
    }

    public function mySQLUp(Schema $schema): void
    {
        $this->addSql('ALTER TABLE assembly_bom_entries DROP FOREIGN KEY `FK_8C74887EF12E799E`');
        $this->addSql('DROP INDEX IDX_8C74887EF12E799E ON assembly_bom_entries');
        $this->addSql('ALTER TABLE assembly_bom_entries DROP id_project');
    }

    public function mySQLDown(Schema $schema): void
    {
        $this->addSql('ALTER TABLE assembly_bom_entries ADD id_project INT DEFAULT NULL');
        $this->addSql('ALTER TABLE assembly_bom_entries ADD CONSTRAINT `FK_8C74887EF12E799E` FOREIGN KEY (id_project) REFERENCES projects (id)');
        $this->addSql('CREATE INDEX IDX_8C74887EF12E799E ON assembly_bom_entries (id_project)');
    }

    public function sqLiteUp(Schema $schema): void
    {
        //nothing to do. Already removed from AssemblyBOMEntry and Version20250304081039
    }

    public function sqLiteDown(Schema $schema): void
    {
        //nothing to do.
    }

    public function postgreSQLUp(Schema $schema): void
    {
        //nothing to do. Already removed from AssemblyBOMEntry and Version20250304081039
    }

    public function postgreSQLDown(Schema $schema): void
    {
        //nothing to do.
    }
}
