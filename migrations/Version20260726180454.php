<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Migration\AbstractMultiPlatformMigration;
use Doctrine\DBAL\Schema\Schema;

final class Version20260726180454 extends AbstractMultiPlatformMigration
{
    public function getDescription(): string
    {
        return 'Add OAuth2 authorization server support (API/MCP app auto-provisioning): league/oauth2-server-bundle\'s own Doctrine tables (oauth2_client, oauth2_access_token, oauth2_refresh_token, oauth2_authorization_code), oauth_client_grant_preferences (per user+client OAuth2 consent preferences: granted scope level, friendly name, refresh token TTL) and oauth_dynamically_registered_clients (marks which OAuth2 clients were created via RFC 7591 Dynamic Client Registration rather than by an admin)';
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

        $this->addSql(<<<'SQL'
            CREATE TABLE oauth_client_grant_preferences (
              id INT AUTO_INCREMENT NOT NULL,
              user_identifier VARCHAR(128) NOT NULL,
              client_identifier VARCHAR(32) NOT NULL,
              scope_level SMALLINT NOT NULL,
              friendly_name VARCHAR(255) DEFAULT NULL,
              refresh_token_ttl_days INT DEFAULT NULL,
              last_used_at DATETIME DEFAULT NULL,
              UNIQUE INDEX oauth_client_grant_pref_user_client (user_identifier, client_identifier),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
        SQL);

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

        $this->addSql('DROP TABLE oauth_client_grant_preferences');

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

        $this->addSql(<<<'SQL'
            CREATE TABLE oauth_client_grant_preferences (
              id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
              user_identifier VARCHAR(128) NOT NULL,
              client_identifier VARCHAR(32) NOT NULL,
              scope_level SMALLINT NOT NULL,
              friendly_name VARCHAR(255) DEFAULT NULL,
              refresh_token_ttl_days INTEGER DEFAULT NULL,
              last_used_at DATETIME DEFAULT NULL
            )
        SQL);
        $this->addSql('CREATE UNIQUE INDEX oauth_client_grant_pref_user_client ON oauth_client_grant_preferences (user_identifier, client_identifier)');

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
        $this->addSql('DROP TABLE oauth_client_grant_preferences');
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

        $this->addSql(<<<'SQL'
            CREATE TABLE oauth_client_grant_preferences (
              id INT GENERATED BY DEFAULT AS IDENTITY NOT NULL,
              user_identifier VARCHAR(128) NOT NULL,
              client_identifier VARCHAR(32) NOT NULL,
              scope_level SMALLINT NOT NULL,
              friendly_name VARCHAR(255) DEFAULT NULL,
              refresh_token_ttl_days INT DEFAULT NULL,
              last_used_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
              PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE UNIQUE INDEX oauth_client_grant_pref_user_client ON oauth_client_grant_preferences (user_identifier, client_identifier)');

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

        $this->addSql('DROP TABLE oauth_client_grant_preferences');

        $this->addSql('ALTER TABLE oauth2_access_token DROP CONSTRAINT FK_454D9673C7440455');
        $this->addSql('ALTER TABLE oauth2_authorization_code DROP CONSTRAINT FK_509FEF5FC7440455');
        $this->addSql('ALTER TABLE oauth2_refresh_token DROP CONSTRAINT FK_4DD90732B6A2DD68');
        $this->addSql('DROP TABLE oauth2_access_token');
        $this->addSql('DROP TABLE oauth2_authorization_code');
        $this->addSql('DROP TABLE oauth2_refresh_token');
        $this->addSql('DROP TABLE oauth2_client');
    }
}
