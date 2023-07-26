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

namespace App\Tests\Doctrine;

use App\Doctrine\SQLiteRegexExtension;
use PHPUnit\Framework\TestCase;

class SQLiteRegexExtensionTest extends TestCase
{

    public function regexpDataProvider(): \Generator
    {
        yield [1, 'a', 'a'];
        yield [0, 'a', 'b'];
        yield [1, 'a', 'ba'];
        yield [1, 'a', 'ab'];
        yield [1, 'a', 'baa'];

        yield [1, '^a$', 'a'];
        yield [0, '^a$', 'ab'];
        yield [1, '^a\d+$', 'a123'];
    }

    /**
     * @dataProvider regexpDataProvider
     */
    public function testRegexp(int $expected, string $pattern, string $value): void
    {
        $this->assertSame($expected, SQLiteRegexExtension::regexp($pattern, $value));
    }

    public function fieldDataProvider(): \Generator
    {

        // Null cases
        yield [0, null, []];
        yield [0, null, [1]];
        yield [0, 42, [1, 2]];

        // Ints
        yield [1, 1, [1]];
        yield [1, 2, [2, 1]];
        yield [2, 1, [2, 1]];
        yield [6, 3, [2, 1, 2, 1, 2, 3]];
        yield [1, 2, [2, 1, 2, 1, 2, 1, 2, 1, 2, 1]];
        yield [3, 5, [2, 1, 5, 3]];

        // Strings
        yield [1, 'a', ['a']];
        yield [1, 'b', ['b', 'a']];
        yield [2, 'a', ['b', 'a']];
        yield [1, 'b', ['b', 'a', 'b']];
        yield [6, 'c', ['b', 'a', 'b', 'a', 'b', 'c']];
    }

    /**
     * @dataProvider fieldDataProvider
     */
    public function testField(int $expected, string|int|null $value, array $array): void
    {
        $this->assertSame($expected, SQLiteRegexExtension::field($value, ...$array));
    }

    /**
     * @dataProvider fieldDataProvider
     */
    public function testField2(int $expected, string|int|null $value, array $array): void
    {
        //Should be the same as field, but with the array comma imploded
        $string = implode(',', $array);
        $this->assertSame($expected, SQLiteRegexExtension::field2($value, $string));
    }
}
