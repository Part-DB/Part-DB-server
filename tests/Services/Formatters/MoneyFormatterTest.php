<?php

declare(strict_types=1);

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

namespace App\Tests\Services\Formatters;

use App\Entity\PriceInformations\Currency;
use App\Services\Formatters\MoneyFormatter;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class MoneyFormatterTest extends WebTestCase
{
    private static MoneyFormatter $service;

    public static function setUpBeforeClass(): void
    {
        self::bootKernel();
        self::$service = self::getContainer()->get(MoneyFormatter::class);
    }

    public function testFormatWithFloatInput(): void
    {
        $currency = new Currency();
        $currency->setIsoCode('USD');
        $result = self::$service->format(1.5, $currency);

        $this->assertSame('$ 1.50', $result);
    }

    public function testFormatWithNullCurrencyUsesBaseCurrency(): void
    {
        $result = self::$service->format(1.5);
        // Should return a non-empty formatted string
        $this->assertNotEmpty($result);
        $this->assertIsString($result);
    }

    public function testFormatWithExplicitCurrencyUsesThatCurrency(): void
    {
        $currency = new Currency();
        $currency->setIsoCode('USD');

        $result = self::$service->format(10.0, $currency);
        $this->assertNotEmpty($result);
        $this->assertStringContainsString('10', $result);
    }

    public function testFormatStringInputWorksSameAsFloat(): void
    {
        $resultFloat = self::$service->format(1.5);
        $resultString = self::$service->format('1.5');
        $this->assertSame($resultFloat, $resultString);
    }

    public function testShowAllDigitsRespectsFractionCount(): void
    {
        // With show_all_digits = true and decimals = 3, we expect exactly 3 decimal places
        $result = self::$service->format(1.5, null, 3, true);
        // The number should contain exactly 3 decimal digits
        $this->assertMatchesRegularExpression('/\d{3}(?!\d)/', $result);
    }

    public function testZeroIsFormattedCorrectly(): void
    {
        $result = self::$service->format(0.0);
        $this->assertNotEmpty($result);
        $this->assertStringContainsString('0', $result);
    }

    public function testCurrencyWithEmptyIsoCodeFallsBackToBaseCurrency(): void
    {
        $currency = new Currency();
        // Empty ISO code → should fall back to base currency
        $resultWithEmpty = self::$service->format(1.0, $currency);
        $resultWithNull = self::$service->format(1.0, null);
        $this->assertSame($resultWithNull, $resultWithEmpty);
    }
}
