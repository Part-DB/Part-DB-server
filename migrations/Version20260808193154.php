<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Migration\AbstractMultiPlatformMigration;
use Doctrine\DBAL\Schema\Schema;

final class Version20260808193154 extends AbstractMultiPlatformMigration
{
    public function getDescription(): string
    {
        return 'Add oauth_dynamically_registered_clients (marks which OAuth2 clients were created via RFC 7591 Dynamic Client Registration rather than by an admin; client_identifier is a FK to oauth2_client.identifier with ON DELETE CASCADE)';
    }

    public function mySQLUp(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE oauth_dynamically_registered_clients (
              client_identifier VARCHAR(32) NOT NULL,
              registered_at DATETIME NOT NULL,
              PRIMARY KEY (client_identifier)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
        SQL);
        $this->addSql('ALTER TABLE oauth_dynamically_registered_clients ADD CONSTRAINT FK_oauth_dyn_reg_client FOREIGN KEY (client_identifier) REFERENCES oauth2_client (identifier) ON DELETE CASCADE');
    }

    public function mySQLDown(Schema $schema): void
    {
        $this->addSql('ALTER TABLE oauth_dynamically_registered_clients DROP FOREIGN KEY FK_oauth_dyn_reg_client');
        $this->addSql('DROP TABLE oauth_dynamically_registered_clients');
    }

    public function sqLiteUp(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE oauth_dynamically_registered_clients (
              client_identifier VARCHAR(32) NOT NULL,
              registered_at DATETIME NOT NULL,
              PRIMARY KEY (client_identifier),
              CONSTRAINT FK_oauth_dyn_reg_client FOREIGN KEY (client_identifier) REFERENCES oauth2_client (identifier) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
            )
        SQL);
    }

    public function sqLiteDown(Schema $schema): void
    {
        $this->addSql('DROP TABLE oauth_dynamically_registered_clients');
    }

    public function postgreSQLUp(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE oauth_dynamically_registered_clients (
              client_identifier VARCHAR(32) NOT NULL,
              registered_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              PRIMARY KEY (client_identifier)
            )
        SQL);
        $this->addSql('ALTER TABLE oauth_dynamically_registered_clients ADD CONSTRAINT FK_oauth_dyn_reg_client FOREIGN KEY (client_identifier) REFERENCES oauth2_client (identifier) ON DELETE CASCADE');
    }

    public function postgreSQLDown(Schema $schema): void
    {
        $this->addSql('ALTER TABLE oauth_dynamically_registered_clients DROP CONSTRAINT FK_oauth_dyn_reg_client');
        $this->addSql('DROP TABLE oauth_dynamically_registered_clients');
    }
}
