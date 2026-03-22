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

use App\Doctrine\Functions\Natsort;
use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;

final class NatsortTest extends AbstractDoctrineFunctionTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Natsort::allowSlowNaturalSort(false);
        $this->setStaticProperty(Natsort::class, 'supportsNaturalSort', null);
    }

    public function testNatsortUsesPostgresCollation(): void
    {
        $function = new Natsort('NATSORT');
        $this->setObjectProperty($function, 'field', $this->createNode('part_name'));

        $sql = $function->getSql($this->createSqlWalker(new PostgreSQLPlatform()));

        $this->assertSame('part_name COLLATE numeric', $sql);
    }

    public function testNatsortUsesMariaDbNativeFunctionOnSupportedVersion(): void
    {
        $function = new Natsort('NATSORT');
        $this->setObjectProperty($function, 'field', $this->createNode('part_name'));

        $sql = $function->getSql($this->createSqlWalker(new MariaDBPlatform(), '10.11.2-MariaDB'));

        $this->assertSame('NATURAL_SORT_KEY(part_name)', $sql);
    }

    public function testNatsortFallsBackWithoutSlowSort(): void
    {
        $function = new Natsort('NATSORT');
        $this->setObjectProperty($function, 'field', $this->createNode('part_name'));

        $sql = $function->getSql($this->createSqlWalker(new MariaDBPlatform(), '10.6.10-MariaDB'));

        $this->assertSame('part_name', $sql);
    }

    public function testNatsortUsesSlowSortFunctionOnMySqlWhenEnabled(): void
    {
        Natsort::allowSlowNaturalSort();

        $function = new Natsort('NATSORT');
        $this->setObjectProperty($function, 'field', $this->createNode('part_name'));

        $sql = $function->getSql($this->createSqlWalker(new MySQLPlatform()));

        $this->assertSame('NatSortKey(part_name, 0)', $sql);
    }

    public function testNatsortUsesSlowSortCollationOnSqliteWhenEnabled(): void
    {
        Natsort::allowSlowNaturalSort();

        $function = new Natsort('NATSORT');
        $this->setObjectProperty($function, 'field', $this->createNode('part_name'));

        $sql = $function->getSql($this->createSqlWalker(new SQLitePlatform()));

        $this->assertSame('part_name COLLATE NATURAL_CMP', $sql);
    }
}

