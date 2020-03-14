<?php
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

namespace App\Tests\Entity\Parameters;

use App\Entity\Parameters\PartParameter;
use PHPUnit\Framework\TestCase;

class PartParameterTest extends TestCase
{

    public function valueWithUnitDataProvider(): array
    {
        return [
            ['1', 1.0, ''],
            ['1 V', 1.0, 'V'],
            ['1.23', 1.23, ''],
            ['1.23 V', 1.23, 'V'],
        ];
    }

    public function formattedValueDataProvider(): array
    {
        return [
            ['Text Test', null, null, null, 'V', 'Text Test'],
            ['10.23 V', null, 10.23, null, 'V', ''],
            ['10.23 V [Text]', null, 10.23, null, 'V', 'Text'],
            ['max. 10.23 V', null, null, 10.23, 'V', ''],
            ['max. 10.23 [Text]', null, null, 10.23, '', 'Text'],
            ['min. 10.23 V', 10.23, null, null, 'V', ''],
            ['10.23 V ... 11 V', 10.23, null, 11, 'V', ''],
            ['10.23 V (9 V ... 11 V)', 9, 10.23, 11, 'V', ''],
            ['10.23 V (9 V ... 11 V) [Test]', 9, 10.23, 11, 'V', 'Test'],
        ];
    }

    /**
     * @dataProvider  valueWithUnitDataProvider
     * @param  string  $expected
     * @param  float  $value
     * @param  string  $unit
     */
    public function testGetValueMinWithUnit(string $expected, float $value, string $unit): void
    {
        $param = new PartParameter();
        $param->setUnit($unit);
        $param->setValueMin($value);
        $this->assertSame($expected, $param->getValueMinWithUnit());
    }

    /**
     * @dataProvider  valueWithUnitDataProvider
     * @param  string  $expected
     * @param  float  $value
     * @param  string  $unit
     */
    public function testGetValueMaxWithUnit(string $expected, float $value, string $unit): void
    {
        $param = new PartParameter();
        $param->setUnit($unit);
        $param->setValueMax($value);
        $this->assertSame($expected, $param->getValueMaxWithUnit());
    }

    /**
     * @dataProvider  valueWithUnitDataProvider
     * @param  string  $expected
     * @param  float  $value
     * @param  string  $unit
     */
    public function testGetValueTypicalWithUnit(string $expected, float $value, string $unit): void
    {
        $param = new PartParameter();
        $param->setUnit($unit);
        $param->setValueTypical($value);
        $this->assertSame($expected, $param->getValueTypicalWithUnit());
    }

    /**
     * @dataProvider formattedValueDataProvider
     * @param  string  $expected
     * @param  float  $min
     * @param  float  $typical
     * @param  float  $max
     * @param  string  $unit
     * @param  string  $text
     */
    public function testGetFormattedValue(string $expected, ?float $min, ?float $typical, ?float $max, string $unit, string $text): void
    {
        $param = new PartParameter();
        $param->setUnit($unit);
        $param->setValueMin($min);
        $param->setValueTypical($typical);
        $param->setValueMax($max);
        $param->setValueText($text);
        $this->assertSame($expected, $param->getFormattedValue());
    }
}
