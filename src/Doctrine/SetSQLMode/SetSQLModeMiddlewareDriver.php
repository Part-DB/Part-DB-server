<?php

declare(strict_types=1);

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
namespace App\Doctrine\SetSQLMode;

use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;

/**
 * This command sets the initial command parameter for MySQL connections, so we can set the SQL mode
 * We use this to disable the ONLY_FULL_GROUP_BY mode, which is enabled by default in MySQL 5.7.5 and higher and causes problems with our filters
 */
class SetSQLModeMiddlewareDriver extends AbstractDriverMiddleware
{
    public function connect(array $params): Connection
    {
        //Only set this on MySQL connections, as other databases don't support this parameter
        if($this->getDatabasePlatform() instanceof AbstractMySQLPlatform) {
            //1002 is \PDO::MYSQL_ATTR_INIT_COMMAND constant value
            $params['driverOptions'][1002] = 'SET SESSION sql_mode=(SELECT REPLACE(@@sql_mode, \'ONLY_FULL_GROUP_BY\', \'\'))';
        }

        return parent::connect($params);
    }
}
