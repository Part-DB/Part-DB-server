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

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\AbstractPostgreSQLDriver;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\ORM\Query\AST\Functions\FunctionNode;
use Doctrine\ORM\Query\AST\Node;
use Doctrine\ORM\Query\Parser;
use Doctrine\ORM\Query\SqlWalker;
use Doctrine\ORM\Query\TokenType;

class Natsort extends FunctionNode
{
    private ?Node $field = null;

    private static ?bool $supportsNaturalSort = null;

    private static bool $allowSlowNaturalSort = false;

    /**
     * As we can not inject parameters into the function, we use an event listener, to call the value on the static function.
     * This is the only way to inject the value into the function.
     * @param  bool  $allow
     * @return void
     */
    public static function allowSlowNaturalSort(bool $allow = true): void
    {
        self::$allowSlowNaturalSort = $allow;
    }

    /**
     * Check if the MariaDB version which is connected to supports the natural sort (meaning it has a version of 10.7.0 or higher)
     * The result is cached in memory.
     * @param  Connection  $connection
     * @return bool
     * @throws Exception
     */
    private function mariaDBSupportsNaturalSort(Connection $connection): bool
    {
        if (self::$supportsNaturalSort !== null) {
            return self::$supportsNaturalSort;
        }

        $version = $connection->getServerVersion();

        //Get the effective MariaDB version number
        $version = $this->getMariaDbMysqlVersionNumber($version);

        //We need at least MariaDB 10.7.0 to support the natural sort
        self::$supportsNaturalSort = version_compare($version, '10.7.0', '>=');
        return self::$supportsNaturalSort;
    }

    /**
     * Taken from Doctrine\DBAL\Driver\AbstractMySQLDriver
     *
     * Detect MariaDB server version, including hack for some mariadb distributions
     * that starts with the prefix '5.5.5-'
     *
     * @param string $versionString Version string as returned by mariadb server, i.e. '5.5.5-Mariadb-10.0.8-xenial'
     */
    private function getMariaDbMysqlVersionNumber(string $versionString) : string
    {
        if ( ! preg_match(
            '/^(?:5\.5\.5-)?(mariadb-)?(?P<major>\d+)\.(?P<minor>\d+)\.(?P<patch>\d+)/i',
            $versionString,
            $versionParts
        )) {
            throw new \RuntimeException('Could not detect MariaDB version from version string ' . $versionString);
        }

        return $versionParts['major'] . '.' . $versionParts['minor'] . '.' . $versionParts['patch'];
    }

    public function parse(Parser $parser): void
    {
        $parser->match(TokenType::T_IDENTIFIER);
        $parser->match(TokenType::T_OPEN_PARENTHESIS);

        $this->field = $parser->ArithmeticExpression();

        $parser->match(TokenType::T_CLOSE_PARENTHESIS);
    }

    public function getSql(SqlWalker $sqlWalker): string
    {
        assert($this->field !== null, 'Field is not set');

        $platform = $sqlWalker->getConnection()->getDatabasePlatform();

        if ($platform instanceof PostgreSQLPlatform) {
            return $this->field->dispatch($sqlWalker) . ' COLLATE numeric';
        }

        if ($platform instanceof MariaDBPlatform && $this->mariaDBSupportsNaturalSort($sqlWalker->getConnection())) {
            return 'NATURAL_SORT_KEY(' . $this->field->dispatch($sqlWalker) . ')';
        }

        //Do the following operations only if we allow slow natural sort
        if (self::$allowSlowNaturalSort) {
            if ($platform instanceof SQLitePlatform) {
                return $this->field->dispatch($sqlWalker).' COLLATE NATURAL_CMP';
            }

            if ($platform instanceof AbstractMySQLPlatform) {
                return 'NatSortKey(' . $this->field->dispatch($sqlWalker) . ', 0)';
            }
        }

         //For every other platform, return the field as is
        return $this->field->dispatch($sqlWalker);
    }


}