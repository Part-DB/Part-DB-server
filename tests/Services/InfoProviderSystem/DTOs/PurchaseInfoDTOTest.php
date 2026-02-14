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

use App\Services\InfoProviderSystem\DTOs\PriceDTO;
use App\Services\InfoProviderSystem\DTOs\PurchaseInfoDTO;
use PHPUnit\Framework\TestCase;

class PurchaseInfoDTOTest extends TestCase
{
    public function testThrowOnInvalidType(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The prices array must only contain PriceDTO instances');
        new PurchaseInfoDTO('test', 'test', [new \stdClass()]);
    }

    public function testPricesIncludesVATHandling(): void
    {
        $pricesTrue = [
            new PriceDTO(minimum_discount_amount: 1, price: '10.00', currency_iso_code: 'USD', includes_tax: true),
            new PriceDTO(minimum_discount_amount: 5, price: '9.00', currency_iso_code: 'USD', includes_tax: true),
        ];
        $pricesFalse = [
            new PriceDTO(minimum_discount_amount: 1, price: '10.00', currency_iso_code: 'USD', includes_tax: false),
            new PriceDTO(minimum_discount_amount: 5, price: '9.00', currency_iso_code: 'USD', includes_tax: false),
        ];
        $pricesMixed = [
            new PriceDTO(minimum_discount_amount: 1, price: '10.00', currency_iso_code: 'USD', includes_tax: true),
            new PriceDTO(minimum_discount_amount: 5, price: '9.00', currency_iso_code: 'USD', includes_tax: false),
        ];
        $pricesNull = [
            new PriceDTO(minimum_discount_amount: 1, price: '10.00', currency_iso_code: 'USD', includes_tax: null),
            new PriceDTO(minimum_discount_amount: 5, price: '9.00', currency_iso_code: 'USD', includes_tax: null),
        ];

        //If the prices_include_vat parameter is given, use it:
        $dto = new PurchaseInfoDTO('test', 'test', $pricesMixed, prices_include_vat: true);
        $this->assertTrue($dto->prices_include_vat);
        $dto = new PurchaseInfoDTO('test', 'test', $pricesMixed, prices_include_vat: false);
        $this->assertFalse($dto->prices_include_vat);

        //If the prices_include_vat parameter is not given, try to deduct it from the prices:
        $dto = new PurchaseInfoDTO('test', 'test', $pricesTrue);
        $this->assertTrue($dto->prices_include_vat);
        $dto = new PurchaseInfoDTO('test', 'test', $pricesFalse);
        $this->assertFalse($dto->prices_include_vat);
        $dto = new PurchaseInfoDTO('test', 'test', $pricesMixed);
        $this->assertNull($dto->prices_include_vat);
        $dto = new PurchaseInfoDTO('test', 'test', $pricesNull);
        $this->assertNull($dto->prices_include_vat);
    }
}
