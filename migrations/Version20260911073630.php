<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Migration\AbstractMultiPlatformMigration;
use Doctrine\DBAL\Schema\Schema;

final class Version20260911073630 extends AbstractMultiPlatformMigration
{
    public function getDescription(): string
    {
        return 'Add grid parameters (grid_columns, grid_rows) to label options for N-up label printing';
    }

    public function mySQLUp(Schema $schema): void
    {
        $this->addSql('ALTER TABLE label_profiles ADD options_grid_columns SMALLINT NOT NULL DEFAULT 1');
        $this->addSql('ALTER TABLE label_profiles ADD options_grid_rows SMALLINT NOT NULL DEFAULT 1');
    }

    public function mySQLDown(Schema $schema): void
    {
        $this->addSql('ALTER TABLE label_profiles DROP COLUMN options_grid_columns, DROP COLUMN options_grid_rows');
    }

    public function sqLiteUp(Schema $schema): void
    {
        $this->addSql('ALTER TABLE label_profiles ADD COLUMN options_grid_columns SMALLINT NOT NULL DEFAULT 1');
        $this->addSql('ALTER TABLE label_profiles ADD COLUMN options_grid_rows SMALLINT NOT NULL DEFAULT 1');
    }

    public function sqLiteDown(Schema $schema): void
    {
        $this->addSql('ALTER TABLE label_profiles DROP COLUMN options_grid_columns');
        $this->addSql('ALTER TABLE label_profiles DROP COLUMN options_grid_rows');
    }

    public function postgreSQLUp(Schema $schema): void
    {
        $this->addSql('ALTER TABLE label_profiles ADD options_grid_columns SMALLINT NOT NULL DEFAULT 1, ADD options_grid_rows SMALLINT NOT NULL DEFAULT 1');
    }

    public function postgreSQLDown(Schema $schema): void
    {
        $this->addSql('ALTER TABLE label_profiles DROP COLUMN options_grid_columns, DROP COLUMN options_grid_rows');
    }
}
