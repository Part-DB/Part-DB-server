<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Migration\AbstractMultiPlatformMigration;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260726170000 extends AbstractMultiPlatformMigration
{
    public function getDescription(): string
    {
        return 'Add OAuth2 authorization server tables (oauth_clients, oauth_auth_codes, oauth_refresh_tokens) and link api_tokens to the OAuth client that issued them';
    }

    public function mySQLUp(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE oauth_clients (
              id INT AUTO_INCREMENT NOT NULL,
              identifier VARCHAR(255) NOT NULL,
              name VARCHAR(255) NOT NULL,
              redirect_uris JSON NOT NULL,
              registration_access_token VARCHAR(255) NOT NULL,
              dynamically_registered TINYINT(1) NOT NULL,
              last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
              datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
              UNIQUE INDEX UNIQ_13CE8101772E836A (identifier),
              UNIQUE INDEX UNIQ_13CE8101105C05AC (registration_access_token),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE oauth_auth_codes (
              id INT AUTO_INCREMENT NOT NULL,
              identifier VARCHAR(255) NOT NULL,
              scope_identifiers JSON NOT NULL,
              expiry_date_time DATETIME NOT NULL,
              redirect_uri VARCHAR(255) DEFAULT NULL,
              revoked TINYINT(1) NOT NULL,
              client_id INT NOT NULL,
              user_id INT NOT NULL,
              UNIQUE INDEX UNIQ_BB493F83772E836A (identifier),
              INDEX IDX_BB493F8319EB6921 (client_id),
              INDEX IDX_BB493F83A76ED395 (user_id),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE oauth_refresh_tokens (
              id INT AUTO_INCREMENT NOT NULL,
              identifier VARCHAR(255) NOT NULL,
              expiry_date_time DATETIME NOT NULL,
              revoked TINYINT(1) NOT NULL,
              family_id VARCHAR(255) NOT NULL,
              api_token_id INT NOT NULL,
              client_id INT NOT NULL,
              UNIQUE INDEX UNIQ_5AB687772E836A (identifier),
              INDEX IDX_5AB68792E52D36 (api_token_id),
              INDEX IDX_5AB68719EB6921 (client_id),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`
        SQL);
        $this->addSql('ALTER TABLE oauth_auth_codes ADD CONSTRAINT FK_BB493F8319EB6921 FOREIGN KEY (client_id) REFERENCES oauth_clients (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE oauth_auth_codes ADD CONSTRAINT FK_BB493F83A76ED395 FOREIGN KEY (user_id) REFERENCES `users` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE oauth_refresh_tokens ADD CONSTRAINT FK_5AB68792E52D36 FOREIGN KEY (api_token_id) REFERENCES api_tokens (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE oauth_refresh_tokens ADD CONSTRAINT FK_5AB68719EB6921 FOREIGN KEY (client_id) REFERENCES oauth_clients (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE api_tokens ADD oauth_client_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE api_tokens ADD CONSTRAINT FK_2CAD560EDCA49ED FOREIGN KEY (oauth_client_id) REFERENCES oauth_clients (id) ON DELETE CASCADE');
        $this->addSql('CREATE INDEX IDX_2CAD560EDCA49ED ON api_tokens (oauth_client_id)');
    }

    public function mySQLDown(Schema $schema): void
    {
        $this->addSql('ALTER TABLE oauth_auth_codes DROP FOREIGN KEY FK_BB493F8319EB6921');
        $this->addSql('ALTER TABLE oauth_auth_codes DROP FOREIGN KEY FK_BB493F83A76ED395');
        $this->addSql('ALTER TABLE oauth_refresh_tokens DROP FOREIGN KEY FK_5AB68792E52D36');
        $this->addSql('ALTER TABLE oauth_refresh_tokens DROP FOREIGN KEY FK_5AB68719EB6921');
        $this->addSql('ALTER TABLE api_tokens DROP FOREIGN KEY FK_2CAD560EDCA49ED');
        $this->addSql('DROP INDEX IDX_2CAD560EDCA49ED ON api_tokens');
        $this->addSql('ALTER TABLE api_tokens DROP oauth_client_id');
        $this->addSql('DROP TABLE oauth_auth_codes');
        $this->addSql('DROP TABLE oauth_refresh_tokens');
        $this->addSql('DROP TABLE oauth_clients');
    }

    public function sqLiteUp(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE oauth_clients (
              id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
              identifier VARCHAR(255) NOT NULL,
              name VARCHAR(255) NOT NULL,
              redirect_uris CLOB NOT NULL,
              registration_access_token VARCHAR(255) NOT NULL,
              dynamically_registered BOOLEAN NOT NULL,
              last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
              datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL
            )
        SQL);
        $this->addSql('CREATE UNIQUE INDEX UNIQ_13CE8101772E836A ON oauth_clients (identifier)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_13CE8101105C05AC ON oauth_clients (registration_access_token)');
        $this->addSql(<<<'SQL'
            CREATE TABLE oauth_auth_codes (
              id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
              identifier VARCHAR(255) NOT NULL,
              scope_identifiers CLOB NOT NULL,
              expiry_date_time DATETIME NOT NULL,
              redirect_uri VARCHAR(255) DEFAULT NULL,
              revoked BOOLEAN NOT NULL,
              client_id INTEGER NOT NULL,
              user_id INTEGER NOT NULL,
              CONSTRAINT FK_BB493F8319EB6921 FOREIGN KEY (client_id) REFERENCES oauth_clients (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE,
              CONSTRAINT FK_BB493F83A76ED395 FOREIGN KEY (user_id) REFERENCES "users" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
            )
        SQL);
        $this->addSql('CREATE UNIQUE INDEX UNIQ_BB493F83772E836A ON oauth_auth_codes (identifier)');
        $this->addSql('CREATE INDEX IDX_BB493F8319EB6921 ON oauth_auth_codes (client_id)');
        $this->addSql('CREATE INDEX IDX_BB493F83A76ED395 ON oauth_auth_codes (user_id)');
        $this->addSql(<<<'SQL'
            CREATE TABLE oauth_refresh_tokens (
              id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
              identifier VARCHAR(255) NOT NULL,
              expiry_date_time DATETIME NOT NULL,
              revoked BOOLEAN NOT NULL,
              family_id VARCHAR(255) NOT NULL,
              api_token_id INTEGER NOT NULL,
              client_id INTEGER NOT NULL,
              CONSTRAINT FK_5AB68792E52D36 FOREIGN KEY (api_token_id) REFERENCES api_tokens (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE,
              CONSTRAINT FK_5AB68719EB6921 FOREIGN KEY (client_id) REFERENCES oauth_clients (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
            )
        SQL);
        $this->addSql('CREATE UNIQUE INDEX UNIQ_5AB687772E836A ON oauth_refresh_tokens (identifier)');
        $this->addSql('CREATE INDEX IDX_5AB68792E52D36 ON oauth_refresh_tokens (api_token_id)');
        $this->addSql('CREATE INDEX IDX_5AB68719EB6921 ON oauth_refresh_tokens (client_id)');

        //SQLite can't ALTER TABLE ADD COLUMN with a foreign key constraint, so the table is rebuilt
        $this->addSql(<<<'SQL'
            CREATE TEMPORARY TABLE __temp__api_tokens AS
            SELECT id, user_id, name, valid_until, token, level, last_time_used, last_modified, datetime_added
            FROM api_tokens
        SQL);
        $this->addSql('DROP TABLE api_tokens');
        $this->addSql(<<<'SQL'
            CREATE TABLE api_tokens (
              id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
              user_id INTEGER DEFAULT NULL,
              name VARCHAR(255) NOT NULL,
              valid_until DATETIME DEFAULT NULL,
              token VARCHAR(68) NOT NULL,
              level SMALLINT NOT NULL,
              last_time_used DATETIME DEFAULT NULL,
              last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
              datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
              oauth_client_id INTEGER DEFAULT NULL,
              CONSTRAINT FK_2CAD560EA76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE,
              CONSTRAINT FK_2CAD560EDCA49ED FOREIGN KEY (oauth_client_id) REFERENCES oauth_clients (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
            )
        SQL);
        $this->addSql(<<<'SQL'
            INSERT INTO api_tokens (id, user_id, name, valid_until, token, level, last_time_used, last_modified, datetime_added)
            SELECT id, user_id, name, valid_until, token, level, last_time_used, last_modified, datetime_added
            FROM __temp__api_tokens
        SQL);
        $this->addSql('DROP TABLE __temp__api_tokens');
        $this->addSql('CREATE INDEX IDX_2CAD560EA76ED395 ON api_tokens (user_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_2CAD560E5F37A13B ON api_tokens (token)');
        $this->addSql('CREATE INDEX IDX_2CAD560EDCA49ED ON api_tokens (oauth_client_id)');
    }

    public function sqLiteDown(Schema $schema): void
    {
        $this->addSql('DROP TABLE oauth_auth_codes');
        $this->addSql('DROP TABLE oauth_refresh_tokens');
        $this->addSql('DROP TABLE oauth_clients');

        $this->addSql(<<<'SQL'
            CREATE TEMPORARY TABLE __temp__api_tokens AS
            SELECT id, name, valid_until, token, level, last_time_used, last_modified, datetime_added, user_id
            FROM api_tokens
        SQL);
        $this->addSql('DROP TABLE api_tokens');
        $this->addSql(<<<'SQL'
            CREATE TABLE api_tokens (
              id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
              name VARCHAR(255) NOT NULL,
              valid_until DATETIME DEFAULT NULL,
              token VARCHAR(68) NOT NULL,
              level SMALLINT NOT NULL,
              last_time_used DATETIME DEFAULT NULL,
              last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
              datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
              user_id INTEGER DEFAULT NULL,
              CONSTRAINT FK_2CAD560EA76ED395 FOREIGN KEY (user_id) REFERENCES "users" (id) NOT DEFERRABLE INITIALLY IMMEDIATE
            )
        SQL);
        $this->addSql(<<<'SQL'
            INSERT INTO api_tokens (id, name, valid_until, token, level, last_time_used, last_modified, datetime_added, user_id)
            SELECT id, name, valid_until, token, level, last_time_used, last_modified, datetime_added, user_id
            FROM __temp__api_tokens
        SQL);
        $this->addSql('DROP TABLE __temp__api_tokens');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_2CAD560E5F37A13B ON api_tokens (token)');
        $this->addSql('CREATE INDEX IDX_2CAD560EA76ED395 ON api_tokens (user_id)');
    }

    public function postgreSQLUp(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE oauth_clients (
              id INT GENERATED BY DEFAULT AS IDENTITY NOT NULL,
              identifier VARCHAR(255) NOT NULL,
              name VARCHAR(255) NOT NULL,
              redirect_uris JSON NOT NULL,
              registration_access_token VARCHAR(255) NOT NULL,
              dynamically_registered BOOLEAN NOT NULL,
              last_modified TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL,
              datetime_added TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP NOT NULL,
              PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE UNIQUE INDEX UNIQ_13CE8101772E836A ON oauth_clients (identifier)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_13CE8101105C05AC ON oauth_clients (registration_access_token)');
        $this->addSql(<<<'SQL'
            CREATE TABLE oauth_auth_codes (
              id INT GENERATED BY DEFAULT AS IDENTITY NOT NULL,
              identifier VARCHAR(255) NOT NULL,
              scope_identifiers JSON NOT NULL,
              expiry_date_time TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              redirect_uri VARCHAR(255) DEFAULT NULL,
              revoked BOOLEAN NOT NULL,
              client_id INT NOT NULL,
              user_id INT NOT NULL,
              PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE UNIQUE INDEX UNIQ_BB493F83772E836A ON oauth_auth_codes (identifier)');
        $this->addSql('CREATE INDEX IDX_BB493F8319EB6921 ON oauth_auth_codes (client_id)');
        $this->addSql('CREATE INDEX IDX_BB493F83A76ED395 ON oauth_auth_codes (user_id)');
        $this->addSql(<<<'SQL'
            CREATE TABLE oauth_refresh_tokens (
              id INT GENERATED BY DEFAULT AS IDENTITY NOT NULL,
              identifier VARCHAR(255) NOT NULL,
              expiry_date_time TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              revoked BOOLEAN NOT NULL,
              family_id VARCHAR(255) NOT NULL,
              api_token_id INT NOT NULL,
              client_id INT NOT NULL,
              PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE UNIQUE INDEX UNIQ_5AB687772E836A ON oauth_refresh_tokens (identifier)');
        $this->addSql('CREATE INDEX IDX_5AB68792E52D36 ON oauth_refresh_tokens (api_token_id)');
        $this->addSql('CREATE INDEX IDX_5AB68719EB6921 ON oauth_refresh_tokens (client_id)');
        $this->addSql('ALTER TABLE oauth_auth_codes ADD CONSTRAINT FK_BB493F8319EB6921 FOREIGN KEY (client_id) REFERENCES oauth_clients (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE oauth_auth_codes ADD CONSTRAINT FK_BB493F83A76ED395 FOREIGN KEY (user_id) REFERENCES "users" (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE oauth_refresh_tokens ADD CONSTRAINT FK_5AB68792E52D36 FOREIGN KEY (api_token_id) REFERENCES api_tokens (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE oauth_refresh_tokens ADD CONSTRAINT FK_5AB68719EB6921 FOREIGN KEY (client_id) REFERENCES oauth_clients (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE api_tokens ADD oauth_client_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE api_tokens ADD CONSTRAINT FK_2CAD560EDCA49ED FOREIGN KEY (oauth_client_id) REFERENCES oauth_clients (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('CREATE INDEX IDX_2CAD560EDCA49ED ON api_tokens (oauth_client_id)');
    }

    public function postgreSQLDown(Schema $schema): void
    {
        $this->addSql('ALTER TABLE oauth_auth_codes DROP CONSTRAINT FK_BB493F8319EB6921');
        $this->addSql('ALTER TABLE oauth_auth_codes DROP CONSTRAINT FK_BB493F83A76ED395');
        $this->addSql('ALTER TABLE oauth_refresh_tokens DROP CONSTRAINT FK_5AB68792E52D36');
        $this->addSql('ALTER TABLE oauth_refresh_tokens DROP CONSTRAINT FK_5AB68719EB6921');
        $this->addSql('ALTER TABLE api_tokens DROP CONSTRAINT FK_2CAD560EDCA49ED');
        $this->addSql('DROP INDEX IDX_2CAD560EDCA49ED');
        $this->addSql('ALTER TABLE api_tokens DROP oauth_client_id');
        $this->addSql('DROP TABLE oauth_auth_codes');
        $this->addSql('DROP TABLE oauth_refresh_tokens');
        $this->addSql('DROP TABLE oauth_clients');
    }
}
