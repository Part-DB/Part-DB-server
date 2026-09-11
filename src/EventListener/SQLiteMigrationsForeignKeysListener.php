<?php
/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2026 Jan Böhmer (https://github.com/jbtronics)
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU Affero General Public License as published
 *  by the Free Software Foundation, either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU Affero General Public License for more details.
 *
 *  You should have received a copy of the GNU Affero General Public License
 *  along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

declare(strict_types=1);

namespace App\EventListener;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\Migrations\Event\MigrationsEventArgs;
use Doctrine\Migrations\Events;

/**
 * Several existing migrations recreate SQLite tables to work around SQLite's limited ALTER TABLE support
 * (create a new table, copy rows over, drop the old one) - a pattern that can transiently violate
 * self-referencing or forward-referencing foreign keys while they are copied. SQLite's own documentation
 * (https://www.sqlite.org/foreignkeys.html#fk_schemacommands) recommends disabling foreign key enforcement
 * for the duration of such schema changes, so this does that for the whole migration run (regardless of
 * DATABASE_SQLITE_ENFORCE_FOREIGN_KEYS, see App\Doctrine\Middleware\SQLiteForeignKeysMiddlewareDriver) and
 * restores it again once all migrations have been applied.
 */
#[AsDoctrineListener(event: Events::onMigrationsMigrating)]
#[AsDoctrineListener(event: Events::onMigrationsMigrated)]
class SQLiteMigrationsForeignKeysListener
{
    public function onMigrationsMigrating(MigrationsEventArgs $args): void
    {
        $this->setForeignKeys($args, false);
    }

    public function onMigrationsMigrated(MigrationsEventArgs $args): void
    {
        $this->setForeignKeys($args, true);
    }

    private function setForeignKeys(MigrationsEventArgs $args, bool $enabled): void
    {
        $connection = $args->getConnection();

        if (!$connection->getDatabasePlatform() instanceof SQLitePlatform) {
            return;
        }

        $connection->executeStatement('PRAGMA foreign_keys = ' . ($enabled ? 'ON' : 'OFF'));
    }
}
