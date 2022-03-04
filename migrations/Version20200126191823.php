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
final class Version20200126191823 extends AbstractMultiPlatformMigration
{
    public function getDescription(): string
    {
        return 'Improve the schema of the log table';
    }

    public function mySQLUp(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf('mysql' !== $this->getDatabaseType(), 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE log CHANGE datetime datetime DATETIME NOT NULL, CHANGE level level TINYINT, CHANGE extra extra LONGTEXT NOT NULL COMMENT \'(DC2Type:json)\'');
        $this->addSql('DROP INDEX id_user ON log');
        $this->addSql('ALTER TABLE log ADD CONSTRAINT FK_8F3F68C56B3CA4B FOREIGN KEY (id_user) REFERENCES `users` (id)');
        $this->addSql('CREATE INDEX IDX_8F3F68C56B3CA4B ON log (id_user)');
    }

    public function mySQLDown(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf('mysql' !== $this->getDatabaseType(), 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE log DROP FOREIGN KEY FK_8F3F68C56B3CA4B');
        $this->addSql('ALTER TABLE log DROP FOREIGN KEY FK_8F3F68C56B3CA4B');
        $this->addSql('ALTER TABLE log CHANGE datetime datetime DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, CHANGE level level TINYINT(1) NOT NULL, CHANGE extra extra MEDIUMTEXT CHARACTER SET utf8 NOT NULL COLLATE `utf8_general_ci`');
        $this->addSql('DROP INDEX idx_8f3f68c56b3ca4b ON log');
        $this->addSql('CREATE INDEX id_user ON log (id_user)');
        $this->addSql('ALTER TABLE log ADD CONSTRAINT FK_8F3F68C56B3CA4B FOREIGN KEY (id_user) REFERENCES `users` (id)');
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
