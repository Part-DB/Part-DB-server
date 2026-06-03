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

use App\Doctrine\Functions\Regexp;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Platforms\SQLServerPlatform;
use PHPUnit\Framework\Attributes\DataProvider;

final class RegexpTest extends AbstractDoctrineFunctionTestCase
{
    public static function regexpPlatformProvider(): \Generator
    {
        yield 'mysql' => [new MySQLPlatform(), '(part_name REGEXP :regex)'];
        yield 'sqlite' => [new SQLitePlatform(), '(part_name REGEXP :regex)'];
        yield 'postgres' => [new PostgreSQLPlatform(), '(part_name ~* :regex)'];
    }

    #[DataProvider('regexpPlatformProvider')]
    public function testRegexpUsesExpectedOperator(AbstractPlatform $platform, string $expectedSql): void
    {
        $function = new Regexp('REGEXP');
        $this->setObjectProperty($function, 'value', $this->createNode('part_name'));
        $this->setObjectProperty($function, 'regexp', $this->createNode(':regex'));

        $sql = $function->getSql($this->createSqlWalker($platform));

        $this->assertSame($expectedSql, $sql);
    }

    public function testRegexpThrowsOnUnsupportedPlatform(): void
    {
        $function = new Regexp('REGEXP');
        $this->setObjectProperty($function, 'value', $this->createNode('part_name'));
        $this->setObjectProperty($function, 'regexp', $this->createNode(':regex'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('does not support regular expressions');

        $function->getSql($this->createSqlWalker(new SQLServerPlatform()));
    }
}

