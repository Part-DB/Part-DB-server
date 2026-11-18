<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Migration\AbstractMultiPlatformMigration;
use Doctrine\DBAL\Schema\Schema;

final class Version20261118100000 extends AbstractMultiPlatformMigration
{
    public function getDescription(): string
    {
        return 'Let print agents be shared with other users';
    }

    public function mySQLUp(Schema $schema): void
    {
        $this->addSql('ALTER TABLE agents ADD shared TINYINT(1) DEFAULT 0 NOT NULL');
    }

    public function mySQLDown(Schema $schema): void
    {
        $this->addSql('ALTER TABLE agents DROP shared');
    }

    public function sqLiteUp(Schema $schema): void
    {
        $this->addSql('ALTER TABLE agents ADD shared BOOLEAN DEFAULT 0 NOT NULL');
    }

    public function sqLiteDown(Schema $schema): void
    {
        $this->addSql('ALTER TABLE agents DROP COLUMN shared');
    }

    public function postgreSQLUp(Schema $schema): void
    {
        $this->addSql('ALTER TABLE agents ADD shared BOOLEAN DEFAULT false NOT NULL');
    }

    public function postgreSQLDown(Schema $schema): void
    {
        $this->addSql('ALTER TABLE agents DROP shared');
    }
}
