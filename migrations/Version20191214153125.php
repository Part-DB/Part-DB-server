<?php
/**
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 * Copyright (C) 2019 - 2020 Jan Böhmer (https://github.com/jbtronics)
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Migration\AbstractMultiPlatformMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20191214153125 extends AbstractMultiPlatformMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function mySQLUp(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf('mysql' !== $this->getDatabaseType(), 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('CREATE TABLE u2f_keys (id INT AUTO_INCREMENT NOT NULL, user_id INT DEFAULT NULL, key_handle VARCHAR(64) NOT NULL, public_key VARCHAR(255) NOT NULL, certificate LONGTEXT NOT NULL, counter VARCHAR(255) NOT NULL, name VARCHAR(255) NOT NULL, last_modified DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, datetime_added DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, INDEX IDX_4F4ADB4BA76ED395 (user_id), UNIQUE INDEX user_unique (user_id, key_handle), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('ALTER TABLE u2f_keys ADD CONSTRAINT FK_4F4ADB4BA76ED395 FOREIGN KEY (user_id) REFERENCES `users` (id)');
        $this->addSql('ALTER TABLE `groups` ADD enforce_2fa TINYINT(1) NOT NULL');
        $this->addSql('ALTER TABLE users ADD google_authenticator_secret VARCHAR(255) DEFAULT NULL, ADD backup_codes LONGTEXT NOT NULL COMMENT \'(DC2Type:json)\', ADD backup_codes_generation_date DATETIME DEFAULT NULL, ADD trusted_device_cookie_version INT NOT NULL');
    }

    public function mySQLDown(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf('mysql' !== $this->getDatabaseType(), 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('DROP TABLE u2f_keys');
        $this->addSql('ALTER TABLE `groups` DROP enforce_2fa');
        $this->addSql('ALTER TABLE `users` DROP google_authenticator_secret, DROP backup_codes, DROP backup_codes_generation_date, DROP trusted_device_cookie_version');
    }

    public function sqLiteUp(Schema $schema): void
    {
        $this->warnIf(true, "Migration not needed for SQLite. Skipping...");
    }

    public function sqLiteDown(Schema $schema): void
    {
        $this->warnIf(true, "Migration not needed for SQLite. Skipping...");
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
