<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Migration\AbstractMultiPlatformMigration;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20230220221024 extends AbstractMultiPlatformMigration
{
    public function getDescription(): string
    {
        return 'Added support for SAML/Keycloak';
    }

    public function mySQLUp(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `users` ADD saml_user TINYINT(1) NOT NULL DEFAULT 0');
    }

    public function mySQLDown(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `users` DROP saml_user');
    }

    public function sqLiteUp(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users ADD saml_user BOOLEAN NOT NULL DEFAULT 0');
    }

    public function sqLiteDown(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `users` DROP saml_user');
    }

    public function postgreSQLUp(Schema $schema): void
    {
        $this->warnIf(true, "Migration not needed for Postgres. Skipping...");
    }

    public function postgreSQLDown(Schema $schema): void
    {
        $this->warnIf(true, "Migration not needed for Postgres. Skipping...");
    }
}
