<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Migration\AbstractMultiPlatformMigration;
use Doctrine\DBAL\Schema\Schema;

final class Version20260208190000 extends AbstractMultiPlatformMigration
{
    public function getDescription(): string
    {
        return 'Add kicad_export boolean column to parameters table';
    }

    public function mySQLUp(Schema $schema): void
    {
        $this->addSql('ALTER TABLE parameters ADD kicad_export TINYINT(1) NOT NULL DEFAULT 0');
    }

    public function mySQLDown(Schema $schema): void
    {
        $this->addSql('ALTER TABLE parameters DROP COLUMN kicad_export');
    }

    public function sqLiteUp(Schema $schema): void
    {
        $this->addSql('ALTER TABLE parameters ADD COLUMN kicad_export BOOLEAN NOT NULL DEFAULT 0');
    }

    public function sqLiteDown(Schema $schema): void
    {
        // SQLite does not support DROP COLUMN in older versions; recreate table if needed
        $this->addSql('ALTER TABLE parameters DROP COLUMN kicad_export');
    }

    public function postgreSQLUp(Schema $schema): void
    {
        $this->addSql('ALTER TABLE parameters ADD kicad_export BOOLEAN NOT NULL DEFAULT FALSE');
    }

    public function postgreSQLDown(Schema $schema): void
    {
        $this->addSql('ALTER TABLE parameters DROP COLUMN kicad_export');
    }
}
