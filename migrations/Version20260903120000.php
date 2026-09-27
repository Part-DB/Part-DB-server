<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Migration\AbstractMultiPlatformMigration;
use Doctrine\DBAL\Schema\Schema;

final class Version20260903120000 extends AbstractMultiPlatformMigration
{
    public function getDescription(): string
    {
        return 'Add nullable color column to part_custom_states table (semantic Bootstrap color used to render the state as a badge)';
    }

    public function mySQLUp(Schema $schema): void
    {
        $this->addSql('ALTER TABLE part_custom_states ADD color VARCHAR(20) DEFAULT NULL');
    }

    public function mySQLDown(Schema $schema): void
    {
        $this->addSql('ALTER TABLE part_custom_states DROP COLUMN color');
    }

    public function sqLiteUp(Schema $schema): void
    {
        $this->addSql('ALTER TABLE part_custom_states ADD COLUMN color VARCHAR(20) DEFAULT NULL');
    }

    public function sqLiteDown(Schema $schema): void
    {
        $this->addSql('ALTER TABLE part_custom_states DROP COLUMN color');
    }

    public function postgreSQLUp(Schema $schema): void
    {
        $this->addSql('ALTER TABLE part_custom_states ADD color VARCHAR(20) DEFAULT NULL');
    }

    public function postgreSQLDown(Schema $schema): void
    {
        $this->addSql('ALTER TABLE part_custom_states DROP COLUMN color');
    }
}
