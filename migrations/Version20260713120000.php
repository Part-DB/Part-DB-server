<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Migration\AbstractMultiPlatformMigration;
use Doctrine\DBAL\Schema\Schema;

final class Version20260713120000 extends AbstractMultiPlatformMigration
{
    public function getDescription(): string
    {
        return 'Add eda_symbol_visibility nullable boolean column to parameters table (controls the "visible" flag of the exported KiCad field)';
    }

    public function mySQLUp(Schema $schema): void
    {
        $this->addSql('ALTER TABLE parameters ADD eda_symbol_visibility TINYINT(1) DEFAULT NULL');
    }

    public function mySQLDown(Schema $schema): void
    {
        $this->addSql('ALTER TABLE parameters DROP COLUMN eda_symbol_visibility');
    }

    public function sqLiteUp(Schema $schema): void
    {
        $this->addSql('ALTER TABLE parameters ADD COLUMN eda_symbol_visibility BOOLEAN DEFAULT NULL');
    }

    public function sqLiteDown(Schema $schema): void
    {
        $this->addSql('ALTER TABLE parameters DROP COLUMN eda_symbol_visibility');
    }

    public function postgreSQLUp(Schema $schema): void
    {
        $this->addSql('ALTER TABLE parameters ADD eda_symbol_visibility BOOLEAN DEFAULT NULL');
    }

    public function postgreSQLDown(Schema $schema): void
    {
        $this->addSql('ALTER TABLE parameters DROP COLUMN eda_symbol_visibility');
    }
}
