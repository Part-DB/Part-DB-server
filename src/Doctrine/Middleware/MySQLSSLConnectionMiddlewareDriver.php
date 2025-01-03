<?php
/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2023 Jan Böhmer (https://github.com/jbtronics)
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

use Composer\CaBundle\CaBundle;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;

/**
 * This middleware sets SSL options for MySQL connections
 */
class MySQLSSLConnectionMiddlewareDriver extends AbstractDriverMiddleware
{
    public function __construct(Driver $wrappedDriver, private readonly bool $enabled, private readonly bool $verify = true)
    {
        parent::__construct($wrappedDriver);
    }

    public function connect(array $params): Connection
    {
        //Only set this on MySQL connections, as other databases don't support this parameter
        if($this->enabled && $params['driver'] === 'pdo_mysql') {
            $params['driverOptions'][\PDO::MYSQL_ATTR_SSL_CA] = CaBundle::getSystemCaRootBundlePath();
            $params['driverOptions'][\PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = $this->verify;
        }

        return parent::connect($params);
    }
}