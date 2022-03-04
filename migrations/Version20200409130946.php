<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Migration\AbstractMultiPlatformMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200409130946 extends AbstractMultiPlatformMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function mySQLUp(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf('mysql' !== $this->getDatabaseType(), 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE u2f_keys CHANGE key_handle key_handle VARCHAR(128) NOT NULL');
    }

    public function mySQLDown(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf('mysql' !== $this->getDatabaseType(), 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE u2f_keys CHANGE key_handle key_handle VARCHAR(64) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`');
    }

    public function sqLiteUp(Schema $schema): void
    {
        $this->warnIf(true, "Migration not needed for SQLite. Skipping...");
    }

    public function sqLiteDown(Schema $schema): void
    {
        $this->warnIf(true, "Migration not needed for SQLite. Skipping...");
    }
}
