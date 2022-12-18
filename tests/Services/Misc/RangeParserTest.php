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

declare(strict_types=1);

/**
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 * Copyright (C) 2019 - 2020 Jan Böhmer (https://github.com/jbtronics)
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

namespace App\Tests\Services\Misc;

use App\Services\Misc\RangeParser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class RangeParserTest extends WebTestCase
{
    /**
     * @var RangeParser
     */
    protected $service;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->service = self::getContainer()->get(RangeParser::class);
    }

    public function dataProvider(): array
    {
        return [
            [[], ''],
            [[], '   '],
            [[], "\t"],
            [[1], '1'],
            [[1, 2, 3], '1,2, 3'],
            [[1, 2, 3], '1-3'],
            [[1, 2, 3, 4], '1- 3, 4'],
            [[1, 2, 3, 4], '1, 2,3 -   4'],
            [[1, 2, 3], '  1; 2, 3'],
            [[-1, 0, 1, 2], '-1; 0; 1, 2'],
            [[4, 3, 1, 2], '4,3, 1;2'],
            [[1, 2, 3, 4], '2-1, 3-4'],
            [[1], '1-1'],
            [[-3, -2, -1], '-3--1'],
            [[1, 2, 3], '1,,2;;,,3'],
            [[100, 1000, 1], '100, 1000, 1'],
            [[], 'test', true],
            [[], '1-2-3-4,5', true],
            [[], '1 2 3, 455, 23', true],
            [[], '1, 2, test', true],
        ];
    }

    public function validDataProvider(): array
    {
        return [
            [true, ''],
            [true, '    '],
            [true, '1, 2, 3'],
            [true, '1-2,3, 4- 5'],
            [true, '1 -2, 3- 4, 6'],
            [true, '1--2'],
            [true, '1- -2'],
            [true, ',,12,33'],
            [false, 'test'],
            [false, '1-2-3'],
            [false, '1, 2 test'],
        ];
    }

    /**
     * @dataProvider  dataProvider
     */
    public function testParse(array $expected, string $input, bool $must_throw = false): void
    {
        if ($must_throw) {
            $this->expectException(\InvalidArgumentException::class);
            $this->service->parse($input);
        } else {
            $this->assertSame($expected, $this->service->parse($input));
        }
    }

    /**
     * @dataProvider validDataProvider
     */
    public function testIsValidRange(bool $expected, string $input): void
    {
        $this->assertSame($expected, $this->service->isValidRange($input));
    }
}
