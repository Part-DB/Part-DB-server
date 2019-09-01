<?php
/**
 *
 * part-db version 0.1
 * Copyright (C) 2005 Christoph Lechner
 * http://www.cl-projects.de/
 *
 * part-db version 0.2+
 * Copyright (C) 2009 K. Jacobs and others (see authors.php)
 * http://code.google.com/p/part-db/
 *
 * Part-DB Version 0.4+
 * Copyright (C) 2016 - 2019 Jan Böhmer
 * https://github.com/jbtronics
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA
 *
 */

namespace App\Services;

use App\Entity\PriceInformations\Currency;
use App\Entity\PriceInformations\Pricedetail;
use Locale;
use Money\Number;
use Symfony\Component\Intl\Currencies;

class PricedetailHelper
{
    protected $base_currency;
    protected $locale;

    public function __construct(string $base_currency)
    {
        $this->base_currency = $base_currency;
        $this->locale = Locale::getDefault();
    }

    /**
     * Converts the given value in origin currency to the choosen target currency
     * @param $value float|string The value that should be converted
     * @param Currency|null $originCurrency The currency the $value is given in.
     * Set to null, to use global base currency.
     * @param Currency|null $targetCurrency The target currency, to which $value should be converted.
     * Set to null, to use global base currency.
     * @return string|null The value in $targetCurrency given as bcmath string.
     * Returns null, if it was not possible to convert between both values (e.g. when the exchange rates are missing)
     */
    public function convertMoneyToCurrency($value, ?Currency $originCurrency = null, ?Currency $targetCurrency = null) : ?string
    {
        $value = (string) $value;

        //Convert value to base currency
        $val_base = $value;
        if($originCurrency !== null) {
            //Without an exchange rate we can not calculate the exchange rate
            if ((float) $originCurrency->getExchangeRate() === 0) {
                return null;
            }

            $val_base = bcmul($value, $originCurrency->getExchangeRate(), Pricedetail::PRICE_PRECISION);
        }

        //Convert value in base currency to target currency
        $val_target = $val_base;
        if ($targetCurrency !== null) {
            //Without an exchange rate we can not calculate the exchange rate
            if ($targetCurrency->getExchangeRate() === null) {
                return null;
            }

            $val_target = bcmul($val_base, $targetCurrency->getInverseExchangeRate(), Pricedetail::PRICE_PRECISION);
        }

        return $val_target;
    }
}