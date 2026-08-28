<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Migration\AbstractMultiPlatformMigration;
use Doctrine\DBAL\Schema\Schema;

final class Version20260826000000 extends AbstractMultiPlatformMigration
{
    public function getDescription(): string
    {
        return 'Add deprecated choices to global parameter definitions.';
    }

    public function mySQLUp(Schema $schema): void
    {
        $this->addSql('ALTER TABLE parameter_definitions ADD deprecated_choices JSON DEFAULT NULL');
    }

    public function mySQLDown(Schema $schema): void
    {
        $this->addSql('ALTER TABLE parameter_definitions DROP deprecated_choices');
    }

    public function sqLiteUp(Schema $schema): void
    {
        $this->addSql('ALTER TABLE parameter_definitions ADD deprecated_choices CLOB DEFAULT NULL');
    }

    public function sqLiteDown(Schema $schema): void
    {
        $this->addSql('ALTER TABLE parameter_definitions DROP COLUMN deprecated_choices');
    }

    public function postgreSQLUp(Schema $schema): void
    {
        $this->addSql('ALTER TABLE parameter_definitions ADD deprecated_choices JSON DEFAULT NULL');
    }

    public function postgreSQLDown(Schema $schema): void
    {
        $this->addSql('ALTER TABLE parameter_definitions DROP deprecated_choices');
    }
}
