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

use App\Doctrine\Functions\SiValueSort;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;

final class SiValueSortTest extends AbstractDoctrineFunctionTestCase
{
    public function testPostgreSQLGeneratesCaseExpression(): void
    {
        $function = new SiValueSort('SI_VALUE_SORT');
        $this->setObjectProperty($function, 'field', $this->createNode('part_name'));

        $sql = $function->getSql($this->createSqlWalker(new PostgreSQLPlatform()));

        $this->assertStringContainsString('CASE', $sql);
        $this->assertStringContainsString("REPLACE(part_name, ',', '.')", $sql);
        $this->assertStringContainsString('1e-12', $sql);
        $this->assertStringContainsString('1e-9', $sql);
        $this->assertStringContainsString('1e-6', $sql);
        $this->assertStringContainsString('1e-3', $sql);
        $this->assertStringContainsString('1e3', $sql);
        $this->assertStringContainsString('1e6', $sql);
        $this->assertStringContainsString('1e9', $sql);
        $this->assertStringContainsString('1e12', $sql);
    }

    public function testMySQLGeneratesCaseExpression(): void
    {
        $function = new SiValueSort('SI_VALUE_SORT');
        $this->setObjectProperty($function, 'field', $this->createNode('part_name'));

        $sql = $function->getSql($this->createSqlWalker(new MySQLPlatform()));

        $this->assertStringContainsString('CASE', $sql);
        $this->assertStringContainsString("REPLACE(part_name, ',', '.')", $sql);
        $this->assertStringContainsString('1e-12', $sql);
        $this->assertStringContainsString('1e6', $sql);
    }

    public function testSQLiteUsesSiValueFunction(): void
    {
        $function = new SiValueSort('SI_VALUE_SORT');
        $this->setObjectProperty($function, 'field', $this->createNode('part_name'));

        $sql = $function->getSql($this->createSqlWalker(new SQLitePlatform()));

        $this->assertSame('SI_VALUE(part_name)', $sql);
    }

    /**
     * @dataProvider sqliteSiValueProvider
     */
    public function testSqliteSiValue(?string $input, ?float $expected): void
    {
        $result = SiValueSort::sqliteSiValue($input);

        if ($expected === null) {
            $this->assertNull($result);
        } else {
            $this->assertEqualsWithDelta($expected, $result, $expected * 1e-9);
        }
    }

    /**
     * @return iterable<string, array{?string, ?float}>
     */
    public static function sqliteSiValueProvider(): iterable
    {
        // Basic SI prefix values
        yield 'pico' => ['10pF', 10e-12];
        yield 'nano' => ['100nF', 100e-9];
        yield 'micro_u' => ['1uF', 1e-6];
        yield 'micro_µ' => ['1µF', 1e-6];
        yield 'milli' => ['4.7mH', 4.7e-3];
        yield 'kilo_lower' => ['4.7k', 4.7e3];
        yield 'kilo_upper' => ['4.7K', 4.7e3];
        yield 'mega' => ['1M', 1e6];
        yield 'giga' => ['2.2G', 2.2e9];
        yield 'tera' => ['1T', 1e12];

        // No prefix (plain number)
        yield 'plain_integer' => ['100', 100.0];
        yield 'plain_decimal' => ['4.7', 4.7];

        // Decimal values with prefix (dot separator)
        yield 'decimal_nano' => ['4.7nF', 4.7e-9];
        yield 'decimal_micro' => ['0.1uF', 0.1e-6];
        yield 'decimal_kilo' => ['2.2k', 2.2e3];

        // Comma decimal separator (European locale)
        yield 'comma_kilo' => ['4,7k', 4.7e3];
        yield 'comma_micro' => ['2,2uF', 2.2e-6];
        yield 'comma_kilo_space' => ['1,2 kΩ', 1.2e3];

        // Number NOT at the start — should return NULL
        yield 'prefixed_name' => ['CAP-100nF', null];
        yield 'name_with_number' => ['R 4.7k 1%', null];
        yield 'crystal' => ['Crystal 20MHz', null];

        // Number at start with trailing text
        yield 'number_with_suffix' => ['10nF 25V', 10e-9];

        // Space between number and prefix
        yield 'space_before_prefix' => ['100 nF', 100e-9];

        // Leading whitespace before number
        yield 'leading_whitespace' => ['  10uF', 10e-6];

        // No number at all
        yield 'no_number' => ['Connector', null];
        yield 'text_only' => ['LED red', null];

        // Null input
        yield 'null' => [null, null];

        // Empty string
        yield 'empty' => ['', null];
    }

    /**
     * Test that the sort order is correct by comparing sqliteSiValue results.
     */
    public function testSortOrder(): void
    {
        $parts = ['1uF', '100nF', '10pF', '10uF', '0.1mF', '1F', '10kF', '1MF'];
        $expected = ['10pF', '100nF', '1uF', '10uF', '0.1mF', '1F', '10kF', '1MF'];

        // Sort using sqliteSiValue
        usort($parts, static function (string $a, string $b): int {
            $va = SiValueSort::sqliteSiValue($a);
            $vb = SiValueSort::sqliteSiValue($b);
            return $va <=> $vb;
        });

        $this->assertSame($expected, $parts);
    }

    /**
     * Test that NULL values sort last (after all numeric values).
     */
    public function testNullSortsLast(): void
    {
        $parts = ['Connector', '100nF', 'LED red', '10pF'];

        usort($parts, static function (string $a, string $b): int {
            $va = SiValueSort::sqliteSiValue($a);
            $vb = SiValueSort::sqliteSiValue($b);

            // NULL sorts last
            if ($va === null && $vb === null) {
                return 0;
            }
            if ($va === null) {
                return 1;
            }
            if ($vb === null) {
                return -1;
            }

            return $va <=> $vb;
        });

        $this->assertSame('10pF', $parts[0]);
        $this->assertSame('100nF', $parts[1]);
        // Last two should be the non-numeric names
        $this->assertContains('Connector', array_slice($parts, 2));
        $this->assertContains('LED red', array_slice($parts, 2));
    }
}
