<?php

declare(strict_types=1);

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
namespace App\Tests\Services\InfoProviderSystem\DTOs;

use PHPUnit\Framework\Attributes\DataProvider;
use App\Services\InfoProviderSystem\DTOs\ParameterDTO;
use PHPUnit\Framework\TestCase;

class ParameterDTOTest extends TestCase
{

    public static function parseValueFieldDataProvider(): \Generator
    {
        //Text value
        yield [
            new ParameterDTO('test', value_text: 'test', unit: 'm', symbol: 'm', group: 'test'),
            'test',
            'test',
            'm',
            'm',
            'test'
        ];

        //Numerical value
        yield [
            new ParameterDTO('test', value_typ: 1.0, unit: 'm', symbol: 'm', group: 'test'),
            'test',
            1.0,
            'm',
            'm',
            'test'
        ];

        //Numerical value with unit should be parsed as text value
        yield [
            new ParameterDTO('test', value_text: '1.0 m', unit: 'm', symbol: 'm', group: 'test'),
            'test',
            '1.0 m',
            'm',
            'm',
            'test'
        ];

        //Test ranges
        yield [
            new ParameterDTO('test', value_min: 1.0, value_max: 2.0, unit: 'kg', symbol: 'm', group: 'test'),
            'test',
            '1.0...2.0',
            'kg',
            'm',
            'test'
        ];

        //Test ranges
        yield [
            new ParameterDTO('test', value_min: 1.0, value_max: 2.0, unit: 'kg', symbol: 'm', group: 'test'),
            'test',
            '1.0..2.0',
            'kg',
            'm',
            'test'
        ];

        //Test ranges with tilde
        yield [
            new ParameterDTO('test', value_min: -1.0, value_max: 2.0, unit: 'kg', symbol: 'm', group: 'test'),
            'test',
            '-1.0~+2.0', //Leading signs are parsed correctly
            'kg',
            'm',
            'test'
        ];

        //Test ranges with comment
        yield [
            new ParameterDTO('test', value_text: "Test", value_min: -1.0, value_max: 2.0, unit: 'kg', symbol: 'm',
                group: 'test'),
            'test',
            '-1.0~+2.0 kg Test', //Leading signs are parsed correctly
            'kg',
            'm',
            'test'
        ];

        //Test @comment
        yield [
            new ParameterDTO('test', value_text: "@comment", value_typ: 1.0, unit: 'kg', symbol: 'm', group: 'test'),
            'test',
            '1.0@comment',
            'kg',
            'm',
            'test'
        ];

        //Test plus minus range (without unit)
        yield [
            new ParameterDTO('test', value_min: -1.0, value_max: +1.0, unit: 'kg', symbol: 'm', group: 'test'),
            'test',
            '±1.0',
            'kg',
            'm',
            'test'
        ];

        yield [ //And with unit
            new ParameterDTO('test', value_min: -1.0, value_max: +1.0, unit: 'kg', symbol: 'm', group: 'test'),
            'test',
            '±1.0kg',
            'kg',
            'm',
            'test'
        ];
    }

    public static function parseValueIncludingUnitDataProvider(): \Generator
    {
        //Text value
        yield [
            new ParameterDTO('test', value_text: 'test', unit: null, symbol: 'm', group: 'test'),
            'test',
            'test',
            'm',
            'test'
        ];

        //Numerical value
        yield [
            new ParameterDTO('test', value_typ: 1.0, unit: null, symbol: 'm', group: 'test'),
            'test',
            1.0,
            'm',
            'test'
        ];

        //Numerical value with unit should extract unit correctly
        yield [
            new ParameterDTO('test', value_typ: 1.0, unit: 'kg', symbol: 'm', group: 'test'),
            'test',
            '1.0 kg',
            'm',
            'test'
        ];

        //Should work without space between value and unit
        yield [
            new ParameterDTO('test', value_typ: 1.0, unit: 'kg', symbol: 'm', group: 'test'),
            'test',
            '1.0kg',
            'm',
            'test'
        ];

        //Allow ° as unit symbol
        yield [
            new ParameterDTO('test', value_typ: 1.0, unit: '°C', symbol: 'm', group: 'test'),
            'test',
            '1.0°C',
            'm',
            'test'
        ];

        //Allow _ in units
        yield [
            new ParameterDTO('test', value_typ: 1.0, unit: 'C_m', symbol: 'm', group: 'test'),
            'test',
            '1.0C_m',
            'm',
            'test'
        ];

        //Allow a single space in units
        yield [
            new ParameterDTO('test', value_typ: 1.0, unit: 'C m', symbol: 'm', group: 'test'),
            'test',
            '1.0C m',
            'm',
            'test'
        ];

        //Test ranges
        yield [
            new ParameterDTO('test', value_min: 1.0, value_max: 2.0, unit: 'kg', symbol: 'm', group: 'test'),
            'test',
            '1.0...2.0 kg',
            'm',
            'test'
        ];

        //Test ranges with tilde
        yield [
            new ParameterDTO('test', value_min: -1.0, value_max: 2.0, unit: 'kg', symbol: 'm', group: 'test'),
            'test',
            '-1.0kg~+2.0kg', //Leading signs are parsed correctly
            'm',
            'test'
        ];

        //Test @comment
        yield [
            new ParameterDTO('test', value_text: "@comment", value_typ: 1.0, unit: 'kg', symbol: 'm', group: 'test'),
            'test',
            '1.0 kg@comment',
            'm',
            'test'
        ];

        //Test plus minus range (without unit)
        yield [
            new ParameterDTO('test', value_min: -1.0, value_max: +1.0, unit: 'kg', symbol: 'm', group: 'test'),
            'test',
            '±1.0 kg',
            'm',
            'test'
        ];
    }

    /**
     * @return void
     */
    #[DataProvider('parseValueFieldDataProvider')]
    public function testParseValueField(ParameterDTO $expected, string $name, string|float $value, ?string $unit = null, ?string $symbol = null, ?string $group = null)
    {
        $this->assertEquals($expected, ParameterDTO::parseValueField($name, $value, $unit, $symbol, $group));
    }

    /**
     * @return void
     */
    #[DataProvider('parseValueIncludingUnitDataProvider')]
    public function testParseValueIncludingUnit(ParameterDTO $expected, string $name, string|float $value, ?string $symbol = null, ?string $group = null)
    {
        $this->assertEquals($expected, ParameterDTO::parseValueIncludingUnit($name, $value, $symbol, $group));
    }

    public function testSplitIntoValueAndUnit(): void
    {
        $this->assertSame(['1.0', 'kg'], ParameterDTO::splitIntoValueAndUnit('1.0 kg'));
        $this->assertSame(['1.0', 'kg'], ParameterDTO::splitIntoValueAndUnit('1.0kg'));
        $this->assertSame(['1', 'kg'], ParameterDTO::splitIntoValueAndUnit('1 kg'));

        $this->assertSame(['1.0', '°C'], ParameterDTO::splitIntoValueAndUnit('1.0°C'));
        $this->assertSame(['1.0', '°C'], ParameterDTO::splitIntoValueAndUnit('1.0 °C'));

        $this->assertSame(['1.0', 'C_m'], ParameterDTO::splitIntoValueAndUnit('1.0C_m'));
        $this->assertSame(["70", "℃"], ParameterDTO::splitIntoValueAndUnit("70℃"));

        $this->assertSame(["-5.0", "kg"], ParameterDTO::splitIntoValueAndUnit("-5.0 kg"));
        $this->assertSame(["-5.0", "µg"], ParameterDTO::splitIntoValueAndUnit("-5.0 µg"));

        $this->assertNull(ParameterDTO::splitIntoValueAndUnit('kg'));
        $this->assertNull(ParameterDTO::splitIntoValueAndUnit('Test'));
    }
}
