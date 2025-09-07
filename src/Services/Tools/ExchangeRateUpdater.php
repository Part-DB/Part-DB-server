<?php

declare(strict_types=1);

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
namespace App\Services\Tools;

use App\Entity\PriceInformations\Currency;
use App\Settings\SystemSettings\LocalizationSettings;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Exchanger\Exception\UnsupportedCurrencyPairException;
use Exchanger\Exception\UnsupportedExchangeQueryException;
use Swap\Swap;

class ExchangeRateUpdater
{
    public function __construct(private LocalizationSettings $localizationSettings, private readonly Swap $swap)
    {
    }

    /**
     * Updates the exchange rate of the given currency using the globally configured providers.
     */
    public function update(Currency $currency): Currency
    {
        try {
            //Try it in the direction QUOTE/BASE first, as most providers provide rates in this direction
            $rate = $this->swap->latest($currency->getIsoCode().'/'.$this->localizationSettings->baseCurrency);
            $effective_rate = BigDecimal::of($rate->getValue());
        } catch (UnsupportedCurrencyPairException|UnsupportedExchangeQueryException $exception) {
            //Otherwise try to get it inverse and calculate it ourselfes, from the format "BASE/QUOTE"
            $rate = $this->swap->latest($this->localizationSettings->baseCurrency.'/'.$currency->getIsoCode());
            //The rate says how many quote units are worth one base unit
            //So we need to invert it to get the exchange rate

            $rate_bd = BigDecimal::of($rate->getValue());
            $effective_rate = BigDecimal::one()->dividedBy($rate_bd, Currency::PRICE_SCALE, RoundingMode::HALF_UP);
        }

        $currency->setExchangeRate($effective_rate);

        return $currency;
    }
}
