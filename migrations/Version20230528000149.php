<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Migration\AbstractMultiPlatformMigration;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20230528000149 extends AbstractMultiPlatformMigration
{
    public function getDescription(): string
    {
        return 'Add other_ui column to webauthn_keys table, needed for future compatibility with more complex webauthn authenticators';
    }

    public function mySQLUp(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE webauthn_keys ADD other_ui LONGTEXT DEFAULT NULL COMMENT \'(DC2Type:array)\'');
    }

    public function mySQLDown(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE webauthn_keys DROP other_ui');
    }

    public function sqLiteUp(Schema $schema): void
    {
        $this->addSql('CREATE TEMPORARY TABLE __temp__webauthn_keys AS SELECT id, user_id, public_key_credential_id, type, transports, attestation_type, trust_path, aaguid, credential_public_key, user_handle, counter, name, last_modified, datetime_added FROM webauthn_keys');
        $this->addSql('DROP TABLE webauthn_keys');
        $this->addSql('CREATE TABLE webauthn_keys (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, user_id INTEGER DEFAULT NULL, public_key_credential_id CLOB NOT NULL --(DC2Type:base64)
        , type VARCHAR(255) NOT NULL, transports CLOB NOT NULL --(DC2Type:array)
        , attestation_type VARCHAR(255) NOT NULL, trust_path CLOB NOT NULL --(DC2Type:trust_path)
        , aaguid CLOB NOT NULL --(DC2Type:aaguid)
        , credential_public_key CLOB NOT NULL --(DC2Type:base64)
        , user_handle VARCHAR(255) NOT NULL, counter INTEGER NOT NULL, name VARCHAR(255) NOT NULL, last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, other_ui CLOB DEFAULT NULL --(DC2Type:array)
        , CONSTRAINT FK_799FD143A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO webauthn_keys (id, user_id, public_key_credential_id, type, transports, attestation_type, trust_path, aaguid, credential_public_key, user_handle, counter, name, last_modified, datetime_added) SELECT id, user_id, public_key_credential_id, type, transports, attestation_type, trust_path, aaguid, credential_public_key, user_handle, counter, name, last_modified, datetime_added FROM __temp__webauthn_keys');
        $this->addSql('DROP TABLE __temp__webauthn_keys');
        $this->addSql('CREATE INDEX IDX_799FD143A76ED395 ON webauthn_keys (user_id)');
    }

    public function sqLiteDown(Schema $schema): void
    {
        $this->addSql('CREATE TEMPORARY TABLE __temp__webauthn_keys AS SELECT id, user_id, public_key_credential_id, type, transports, attestation_type, trust_path, aaguid, credential_public_key, user_handle, counter, name, last_modified, datetime_added FROM webauthn_keys');
        $this->addSql('DROP TABLE webauthn_keys');
        $this->addSql('CREATE TABLE webauthn_keys (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, user_id INTEGER DEFAULT NULL, public_key_credential_id CLOB NOT NULL --
(DC2Type:base64)
        , type VARCHAR(255) NOT NULL, transports CLOB NOT NULL --
(DC2Type:array)
        , attestation_type VARCHAR(255) NOT NULL, trust_path CLOB NOT NULL --
(DC2Type:trust_path)
        , aaguid CLOB NOT NULL --
(DC2Type:aaguid)
        , credential_public_key CLOB NOT NULL --
(DC2Type:base64)
        , user_handle VARCHAR(255) NOT NULL, counter INTEGER NOT NULL, name VARCHAR(255) NOT NULL, last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, CONSTRAINT FK_799FD143A76ED395 FOREIGN KEY (user_id) REFERENCES "users" (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO webauthn_keys (id, user_id, public_key_credential_id, type, transports, attestation_type, trust_path, aaguid, credential_public_key, user_handle, counter, name, last_modified, datetime_added) SELECT id, user_id, public_key_credential_id, type, transports, attestation_type, trust_path, aaguid, credential_public_key, user_handle, counter, name, last_modified, datetime_added FROM __temp__webauthn_keys');
        $this->addSql('DROP TABLE __temp__webauthn_keys');
        $this->addSql('CREATE INDEX IDX_799FD143A76ED395 ON webauthn_keys (user_id)');
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
