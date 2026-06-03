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

namespace App\Tests\Doctrine\Functions;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\ORM\Query\AST\Node;
use Doctrine\ORM\Query\SqlWalker;
use PHPUnit\Framework\TestCase;

abstract class AbstractDoctrineFunctionTestCase extends TestCase
{
    protected function createSqlWalker(AbstractPlatform $platform, string $serverVersion = '11.0.0-MariaDB'): SqlWalker
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('getDatabasePlatform')->willReturn($platform);
        $connection->method('getServerVersion')->willReturn($serverVersion);

        $sqlWalker = $this->getMockBuilder(SqlWalker::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getConnection'])
            ->getMock();

        $sqlWalker->method('getConnection')->willReturn($connection);

        return $sqlWalker;
    }

    protected function createNode(string $sql): Node
    {
        $node = $this->createMock(Node::class);
        $node->method('dispatch')->willReturn($sql);

        return $node;
    }

    protected function setObjectProperty(object $object, string $property, mixed $value): void
    {
        $reflection = new \ReflectionProperty($object, $property);
        $reflection->setValue($object, $value);
    }

    protected function setStaticProperty(string $class, string $property, mixed $value): void
    {
        $reflection = new \ReflectionProperty($class, $property);
        $reflection->setValue(null, $value);
    }
}
