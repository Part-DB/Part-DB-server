<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Migration\AbstractMultiPlatformMigration;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260726180454 extends AbstractMultiPlatformMigration
{
    public function getDescription(): string
    {
        return 'Add league/oauth2-server-bundle\'s own Doctrine tables (oauth2_client, oauth2_access_token, oauth2_refresh_token, oauth2_authorization_code) for OAuth2 authorization server support (API/MCP app auto-provisioning)';
    }

    public function mySQLUp(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE oauth2_client (
              name VARCHAR(128) NOT NULL,
              secret VARCHAR(128) DEFAULT NULL,
              redirect_uris TEXT DEFAULT NULL,
              grants TEXT DEFAULT NULL,
              scopes TEXT DEFAULT NULL,
              active TINYINT NOT NULL,
              allow_plain_text_pkce TINYINT DEFAULT 0 NOT NULL,
              identifier VARCHAR(32) NOT NULL,
              PRIMARY KEY (identifier)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE oauth2_access_token (
              identifier CHAR(80) NOT NULL,
              expiry DATETIME NOT NULL,
              user_identifier VARCHAR(128) DEFAULT NULL,
              scopes TEXT DEFAULT NULL,
              revoked TINYINT NOT NULL,
              client VARCHAR(32) NOT NULL,
              INDEX IDX_454D9673C7440455 (client),
              PRIMARY KEY (identifier)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE oauth2_authorization_code (
              identifier CHAR(80) NOT NULL,
              expiry DATETIME NOT NULL,
              user_identifier VARCHAR(128) DEFAULT NULL,
              scopes TEXT DEFAULT NULL,
              revoked TINYINT NOT NULL,
              client VARCHAR(32) NOT NULL,
              INDEX IDX_509FEF5FC7440455 (client),
              PRIMARY KEY (identifier)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE oauth2_refresh_token (
              identifier CHAR(80) NOT NULL,
              expiry DATETIME NOT NULL,
              revoked TINYINT NOT NULL,
              access_token CHAR(80) DEFAULT NULL,
              INDEX IDX_4DD90732B6A2DD68 (access_token),
              PRIMARY KEY (identifier)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
        SQL);
        $this->addSql('ALTER TABLE oauth2_access_token ADD CONSTRAINT FK_454D9673C7440455 FOREIGN KEY (client) REFERENCES oauth2_client (identifier) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE oauth2_authorization_code ADD CONSTRAINT FK_509FEF5FC7440455 FOREIGN KEY (client) REFERENCES oauth2_client (identifier) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE oauth2_refresh_token ADD CONSTRAINT FK_4DD90732B6A2DD68 FOREIGN KEY (access_token) REFERENCES oauth2_access_token (identifier) ON DELETE SET NULL');
    }

    public function mySQLDown(Schema $schema): void
    {
        $this->addSql('ALTER TABLE oauth2_access_token DROP FOREIGN KEY FK_454D9673C7440455');
        $this->addSql('ALTER TABLE oauth2_authorization_code DROP FOREIGN KEY FK_509FEF5FC7440455');
        $this->addSql('ALTER TABLE oauth2_refresh_token DROP FOREIGN KEY FK_4DD90732B6A2DD68');
        $this->addSql('DROP TABLE oauth2_access_token');
        $this->addSql('DROP TABLE oauth2_authorization_code');
        $this->addSql('DROP TABLE oauth2_refresh_token');
        $this->addSql('DROP TABLE oauth2_client');
    }

    public function sqLiteUp(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE oauth2_client (
              name VARCHAR(128) NOT NULL,
              secret VARCHAR(128) DEFAULT NULL,
              redirect_uris CLOB DEFAULT NULL,
              grants CLOB DEFAULT NULL,
              scopes CLOB DEFAULT NULL,
              active BOOLEAN NOT NULL,
              allow_plain_text_pkce BOOLEAN DEFAULT 0 NOT NULL,
              identifier VARCHAR(32) NOT NULL,
              PRIMARY KEY (identifier)
            )
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE oauth2_access_token (
              identifier CHAR(80) NOT NULL,
              expiry DATETIME NOT NULL,
              user_identifier VARCHAR(128) DEFAULT NULL,
              scopes CLOB DEFAULT NULL,
              revoked BOOLEAN NOT NULL,
              client VARCHAR(32) NOT NULL,
              PRIMARY KEY (identifier),
              CONSTRAINT FK_454D9673C7440455 FOREIGN KEY (client) REFERENCES oauth2_client (identifier) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
            )
        SQL);
        $this->addSql('CREATE INDEX IDX_454D9673C7440455 ON oauth2_access_token (client)');
        $this->addSql(<<<'SQL'
            CREATE TABLE oauth2_authorization_code (
              identifier CHAR(80) NOT NULL,
              expiry DATETIME NOT NULL,
              user_identifier VARCHAR(128) DEFAULT NULL,
              scopes CLOB DEFAULT NULL,
              revoked BOOLEAN NOT NULL,
              client VARCHAR(32) NOT NULL,
              PRIMARY KEY (identifier),
              CONSTRAINT FK_509FEF5FC7440455 FOREIGN KEY (client) REFERENCES oauth2_client (identifier) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
            )
        SQL);
        $this->addSql('CREATE INDEX IDX_509FEF5FC7440455 ON oauth2_authorization_code (client)');
        $this->addSql(<<<'SQL'
            CREATE TABLE oauth2_refresh_token (
              identifier CHAR(80) NOT NULL,
              expiry DATETIME NOT NULL,
              revoked BOOLEAN NOT NULL,
              access_token CHAR(80) DEFAULT NULL,
              PRIMARY KEY (identifier),
              CONSTRAINT FK_4DD90732B6A2DD68 FOREIGN KEY (access_token) REFERENCES oauth2_access_token (identifier) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE
            )
        SQL);
        $this->addSql('CREATE INDEX IDX_4DD90732B6A2DD68 ON oauth2_refresh_token (access_token)');
    }

    public function sqLiteDown(Schema $schema): void
    {
        $this->addSql('DROP TABLE oauth2_access_token');
        $this->addSql('DROP TABLE oauth2_authorization_code');
        $this->addSql('DROP TABLE oauth2_refresh_token');
        $this->addSql('DROP TABLE oauth2_client');
    }

    public function postgreSQLUp(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE oauth2_client (
              name VARCHAR(128) NOT NULL,
              secret VARCHAR(128) DEFAULT NULL,
              redirect_uris TEXT DEFAULT NULL,
              grants TEXT DEFAULT NULL,
              scopes TEXT DEFAULT NULL,
              active BOOLEAN NOT NULL,
              allow_plain_text_pkce BOOLEAN DEFAULT false NOT NULL,
              identifier VARCHAR(32) NOT NULL,
              PRIMARY KEY (identifier)
            )
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE oauth2_access_token (
              identifier CHAR(80) NOT NULL,
              expiry TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              user_identifier VARCHAR(128) DEFAULT NULL,
              scopes TEXT DEFAULT NULL,
              revoked BOOLEAN NOT NULL,
              client VARCHAR(32) NOT NULL,
              PRIMARY KEY (identifier)
            )
        SQL);
        $this->addSql('CREATE INDEX IDX_454D9673C7440455 ON oauth2_access_token (client)');
        $this->addSql(<<<'SQL'
            CREATE TABLE oauth2_authorization_code (
              identifier CHAR(80) NOT NULL,
              expiry TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              user_identifier VARCHAR(128) DEFAULT NULL,
              scopes TEXT DEFAULT NULL,
              revoked BOOLEAN NOT NULL,
              client VARCHAR(32) NOT NULL,
              PRIMARY KEY (identifier)
            )
        SQL);
        $this->addSql('CREATE INDEX IDX_509FEF5FC7440455 ON oauth2_authorization_code (client)');
        $this->addSql(<<<'SQL'
            CREATE TABLE oauth2_refresh_token (
              identifier CHAR(80) NOT NULL,
              expiry TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              revoked BOOLEAN NOT NULL,
              access_token CHAR(80) DEFAULT NULL,
              PRIMARY KEY (identifier)
            )
        SQL);
        $this->addSql('CREATE INDEX IDX_4DD90732B6A2DD68 ON oauth2_refresh_token (access_token)');
        $this->addSql('ALTER TABLE oauth2_access_token ADD CONSTRAINT FK_454D9673C7440455 FOREIGN KEY (client) REFERENCES oauth2_client (identifier) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE oauth2_authorization_code ADD CONSTRAINT FK_509FEF5FC7440455 FOREIGN KEY (client) REFERENCES oauth2_client (identifier) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE oauth2_refresh_token ADD CONSTRAINT FK_4DD90732B6A2DD68 FOREIGN KEY (access_token) REFERENCES oauth2_access_token (identifier) ON DELETE SET NULL');
    }

    public function postgreSQLDown(Schema $schema): void
    {
        $this->addSql('ALTER TABLE oauth2_access_token DROP CONSTRAINT FK_454D9673C7440455');
        $this->addSql('ALTER TABLE oauth2_authorization_code DROP CONSTRAINT FK_509FEF5FC7440455');
        $this->addSql('ALTER TABLE oauth2_refresh_token DROP CONSTRAINT FK_4DD90732B6A2DD68');
        $this->addSql('DROP TABLE oauth2_access_token');
        $this->addSql('DROP TABLE oauth2_authorization_code');
        $this->addSql('DROP TABLE oauth2_refresh_token');
        $this->addSql('DROP TABLE oauth2_client');
    }
}
