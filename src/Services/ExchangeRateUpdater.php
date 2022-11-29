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

namespace App\Services;

use App\Entity\PriceInformations\Currency;
use Brick\Math\BigDecimal;
use Swap\Swap;

class ExchangeRateUpdater
{
    private string $base_currency;
    private Swap $swap;

    public function __construct(string $base_currency, Swap $swap)
    {
        $this->base_currency = $base_currency;
        $this->swap = $swap;
    }

    /**
     * Updates the exchange rate of the given currency using the globally configured providers.
     */
    public function update(Currency $currency): Currency
    {
        $rate = $this->swap->latest($currency->getIsoCode().'/'.$this->base_currency);
        $currency->setExchangeRate(BigDecimal::of($rate->getValue()));

        return $currency;
    }
}
