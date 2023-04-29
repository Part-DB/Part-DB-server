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

declare(strict_types=1);

namespace App\Tests\Entity\PriceSystem;

use App\Entity\PriceInformations\Currency;
use Brick\Math\BigDecimal;
use PHPUnit\Framework\TestCase;

class CurrencyTest extends TestCase
{
    public function testGetInverseExchangeRate(): void
    {
        $currency = new Currency();

        //By default, the inverse exchange rate is not set:
        $this->assertNull($currency->getInverseExchangeRate());

        $currency->setExchangeRate(BigDecimal::zero());
        $this->assertNull($currency->getInverseExchangeRate());

        $currency->setExchangeRate(BigDecimal::of('1.45643'));
        $this->assertSame((string) BigDecimal::of('0.68661'), (string) $currency->getInverseExchangeRate());
    }
}
