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

use App\Migrations\AbstractMultiPlatformMigration;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20190913141126 extends AbstractMultiPlatformMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function mySQLUp(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf('mysql' !== $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE `groups` ADD perms_parts_category SMALLINT NOT NULL, ADD perms_parts_minamount SMALLINT NOT NULL, ADD perms_parts_lots SMALLINT NOT NULL, ADD perms_parts_tags SMALLINT NOT NULL, ADD perms_parts_unit SMALLINT NOT NULL, ADD perms_parts_mass SMALLINT NOT NULL, ADD perms_parts_status SMALLINT NOT NULL, ADD perms_parts_mpn SMALLINT NOT NULL, ADD perms_currencies INT NOT NULL, ADD perms_measurement_units INT NOT NULL, DROP perms_parts_instock, DROP perms_parts_mininstock, DROP perms_parts_storelocation');
        $this->addSql('ALTER TABLE users ADD currency_id INT DEFAULT NULL, ADD settings LONGTEXT NOT NULL COMMENT \'(DC2Type:json)\', ADD perms_parts_category SMALLINT NOT NULL, ADD perms_parts_minamount SMALLINT NOT NULL, ADD perms_parts_lots SMALLINT NOT NULL, ADD perms_parts_tags SMALLINT NOT NULL, ADD perms_parts_unit SMALLINT NOT NULL, ADD perms_parts_mass SMALLINT NOT NULL, ADD perms_parts_status SMALLINT NOT NULL, ADD perms_parts_mpn SMALLINT NOT NULL, ADD perms_currencies INT NOT NULL, ADD perms_measurement_units INT NOT NULL, DROP config_currency, DROP perms_parts_instock, DROP perms_parts_mininstock, DROP perms_parts_storelocation');
        $this->addSql('ALTER TABLE users ADD CONSTRAINT FK_1483A5E938248176 FOREIGN KEY (currency_id) REFERENCES currencies (id)');
        $this->addSql('CREATE INDEX IDX_1483A5E938248176 ON users (currency_id)');

        //Set some default permissions for the groups

        $sql = 'UPDATE `groups`
        SET perms_parts_category = 9, perms_parts_minamount = 9, perms_parts_lots = 169, perms_parts_tags= 9,
            perms_parts_unit = 9, perms_parts_mass = 9, perms_parts_status = 9, perms_parts_mpn = 9,
            perms_currencies = 9897, perms_measurement_units = 9897, perms_parts_attachements = 681,
            perms_parts_orderdetails = 681, perms_parts_prices = 681
        WHERE id = 2 AND name = "readonly";';

        $this->addSql($sql);

        $sql = 'UPDATE `groups`
        SET perms_parts_category = 5, perms_parts_minamount = 5, perms_parts_lots = 85, perms_parts_tags= 5,
            perms_parts_unit = 5, perms_parts_mass = 5, perms_parts_status = 5, perms_parts_mpn = 5,
            perms_currencies = 5461, perms_measurement_units = 5461, perms_parts_attachements = 341,
            perms_parts_orderdetails = 341, perms_parts_prices = 341
        WHERE (id = 1 AND name = "admins")
            OR (id = 3 AND name = "users");        
        ';

        $this->addSql($sql);

        $this->printPermissionUpdateMessage();
    }

    public function mySQLDown(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf('mysql' !== $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE `groups` ADD perms_parts_instock SMALLINT NOT NULL, ADD perms_parts_mininstock SMALLINT NOT NULL, ADD perms_parts_storelocation SMALLINT NOT NULL, DROP perms_parts_category, DROP perms_parts_minamount, DROP perms_parts_lots, DROP perms_parts_tags, DROP perms_parts_unit, DROP perms_parts_mass, DROP perms_parts_status, DROP perms_parts_mpn, DROP perms_currencies, DROP perms_measurement_units');
        $this->addSql('ALTER TABLE `users` DROP FOREIGN KEY FK_1483A5E938248176');
        $this->addSql('DROP INDEX IDX_1483A5E938248176 ON `users`');
        $this->addSql('ALTER TABLE `users` ADD config_currency VARCHAR(255) NOT NULL COLLATE utf8_general_ci, ADD perms_parts_instock SMALLINT NOT NULL, ADD perms_parts_mininstock SMALLINT NOT NULL, ADD perms_parts_storelocation SMALLINT NOT NULL, DROP currency_id, DROP settings, DROP perms_parts_category, DROP perms_parts_minamount, DROP perms_parts_lots, DROP perms_parts_tags, DROP perms_parts_unit, DROP perms_parts_mass, DROP perms_parts_status, DROP perms_parts_mpn, DROP perms_currencies, DROP perms_measurement_units');
    }

    public function sqLiteUp(Schema $schema): void
    {
        $this->skipIf(true, "Migration not needed for SQLite. Skipping...");
    }

    public function sqLiteDown(Schema $schema): void
    {
        $this->skipIf(true, "Migration not needed for SQLite. Skipping...");
    }
}
