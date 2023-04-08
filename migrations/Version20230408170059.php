<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Migration\AbstractMultiPlatformMigration;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20230408170059 extends AbstractMultiPlatformMigration
{
    public function getDescription(): string
    {
        return 'Add show_email_on_profile option';
    }

    public function mySQLUp(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `users` ADD show_email_on_profile TINYINT(1) DEFAULT 0 NOT NULL');
    }

    public function mySQLDown(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `users` DROP show_email_on_profile');
    }

    public function sqLiteUp(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users ADD COLUMN show_email_on_profile BOOLEAN DEFAULT 0 NOT NULL');
    }

    public function sqLiteDown(Schema $schema): void
    {
        $this->addSql('CREATE TEMPORARY TABLE __temp__users AS SELECT id, group_id, currency_id, id_preview_attachment, disabled, config_theme, pw_reset_token, config_instock_comment_a, config_instock_comment_w, about_me, trusted_device_cookie_version, backup_codes, google_authenticator_secret, config_timezone, config_language, email, department, last_name, first_name, need_pw_change, password, name, settings, backup_codes_generation_date, pw_reset_expires, saml_user, last_modified, datetime_added, permissions_data FROM "users"');
        $this->addSql('DROP TABLE "users"');
        $this->addSql('CREATE TABLE "users" (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, group_id INTEGER DEFAULT NULL, currency_id INTEGER DEFAULT NULL, id_preview_attachment INTEGER DEFAULT NULL, disabled BOOLEAN NOT NULL, config_theme VARCHAR(255) DEFAULT NULL, pw_reset_token VARCHAR(255) DEFAULT NULL, config_instock_comment_a CLOB NOT NULL, config_instock_comment_w CLOB NOT NULL, about_me CLOB DEFAULT \'\' NOT NULL, trusted_device_cookie_version INTEGER NOT NULL, backup_codes CLOB NOT NULL --(DC2Type:json)
        , google_authenticator_secret VARCHAR(255) DEFAULT NULL, config_timezone VARCHAR(255) DEFAULT NULL, config_language VARCHAR(255) DEFAULT NULL, email VARCHAR(255) DEFAULT NULL, department VARCHAR(255) DEFAULT NULL, last_name VARCHAR(255) DEFAULT NULL, first_name VARCHAR(255) DEFAULT NULL, need_pw_change BOOLEAN NOT NULL, password VARCHAR(255) DEFAULT NULL, name VARCHAR(180) NOT NULL, settings CLOB NOT NULL --(DC2Type:json)
        , backup_codes_generation_date DATETIME DEFAULT NULL, pw_reset_expires DATETIME DEFAULT NULL, saml_user BOOLEAN NOT NULL, last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, permissions_data CLOB DEFAULT \'[]\' NOT NULL --(DC2Type:json)
        , CONSTRAINT FK_1483A5E9FE54D947 FOREIGN KEY (group_id) REFERENCES "groups" (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_1483A5E938248176 FOREIGN KEY (currency_id) REFERENCES currencies (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_1483A5E9EA7100A1 FOREIGN KEY (id_preview_attachment) REFERENCES "attachments" (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO "users" (id, group_id, currency_id, id_preview_attachment, disabled, config_theme, pw_reset_token, config_instock_comment_a, config_instock_comment_w, about_me, trusted_device_cookie_version, backup_codes, google_authenticator_secret, config_timezone, config_language, email, department, last_name, first_name, need_pw_change, password, name, settings, backup_codes_generation_date, pw_reset_expires, saml_user, last_modified, datetime_added, permissions_data) SELECT id, group_id, currency_id, id_preview_attachment, disabled, config_theme, pw_reset_token, config_instock_comment_a, config_instock_comment_w, about_me, trusted_device_cookie_version, backup_codes, google_authenticator_secret, config_timezone, config_language, email, department, last_name, first_name, need_pw_change, password, name, settings, backup_codes_generation_date, pw_reset_expires, saml_user, last_modified, datetime_added, permissions_data FROM __temp__users');
        $this->addSql('DROP TABLE __temp__users');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_1483A5E95E237E06 ON "users" (name)');
        $this->addSql('CREATE INDEX IDX_1483A5E9FE54D947 ON "users" (group_id)');
        $this->addSql('CREATE INDEX IDX_1483A5E938248176 ON "users" (currency_id)');
        $this->addSql('CREATE INDEX IDX_1483A5E9EA7100A1 ON "users" (id_preview_attachment)');
        $this->addSql('CREATE INDEX user_idx_username ON "users" (name)');
    }
}
