<?php
/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2022 Jan Böhmer (https://github.com/jbtronics)
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

namespace App\Doctrine;

use Doctrine\Bundle\DoctrineBundle\EventSubscriber\EventSubscriberInterface;
use Doctrine\DBAL\Event\ConnectionEventArgs;
use Doctrine\DBAL\Events;
use Doctrine\DBAL\Platforms\SqlitePlatform;

/**
 * This subscriber is used to add the regexp operator to the SQLite platform.
 * As a PHP callback is called for every entry to compare it is most likely much slower than using regex on MySQL.
 * But as regex is not often used, this should be fine for most use cases, also it is almost impossible to implement a better solution.
 */
class SQLiteRegexExtension implements EventSubscriberInterface
{
    public function postConnect(ConnectionEventArgs $eventArgs): void
    {
        $connection = $eventArgs->getConnection();

        //We only execute this on SQLite databases
        if ($connection->getDatabasePlatform() instanceof SqlitePlatform) {
            $native_connection = $connection->getNativeConnection();

            //Ensure that the function really exists on the connection, as it is marked as experimental according to PHP documentation
            if($native_connection instanceof \PDO && method_exists($native_connection, 'sqliteCreateFunction' )) {
                $native_connection->sqliteCreateFunction('REGEXP', function ($pattern, $value) {
                    return (false !== mb_ereg($pattern, $value)) ? 1 : 0;
                });
            }
        }
    }

    public function getSubscribedEvents()
    {
        return[
            Events::postConnect
        ];
    }
}