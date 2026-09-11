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


namespace App\Doctrine\Middleware;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;

/**
 * This middleware enables SQLite foreign key constraint enforcement ("PRAGMA foreign_keys = ON") on every
 * new connection. SQLite parses and stores foreign key definitions regardless, but only enforces them when
 * this pragma is set, and it defaults to off and is not persisted in the database file, so it has to be set
 * on every single connection.
 */
class SQLiteForeignKeysMiddlewareDriver extends AbstractDriverMiddleware
{
    public function __construct(Driver $wrappedDriver, private readonly bool $enabled)
    {
        parent::__construct($wrappedDriver);
    }

    public function connect(array $params): Connection
    {
        $connection = parent::connect($params);

        if ($this->enabled && $params['driver'] === 'pdo_sqlite') {
            $connection->exec('PRAGMA foreign_keys = ON');
        }

        return $connection;
    }
}
