<?php
/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2024 Jan Böhmer (https://github.com/jbtronics)
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


namespace App\Doctrine\Functions;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\ORM\Query\SqlWalker;

/**
 * Similar to the regexp function, but with support for multi platform.
 */
class Regexp extends \DoctrineExtensions\Query\Mysql\Regexp
{
    public function getSql(SqlWalker $sqlWalker): string
    {
        $platform = $sqlWalker->getConnection()->getDatabasePlatform();

        //
        if ($platform instanceof AbstractMySQLPlatform || $platform instanceof SQLitePlatform) {
            $operator = 'REGEXP';
        } elseif ($platform instanceof PostgreSQLPlatform) {
            //Use the case-insensitive operator, to have the same behavior as MySQL
            $operator = '~*';
        } else {
            throw new \RuntimeException('Platform ' . gettype($platform) . ' does not support regular expressions.');
        }

        return '(' . $this->value->dispatch($sqlWalker) . ' ' . $operator . ' ' . $this->regexp->dispatch($sqlWalker) . ')';
    }
}