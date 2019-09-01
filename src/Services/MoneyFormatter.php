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
use Locale;

class MoneyFormatter
{

    protected $base_currency;
    protected $locale;

    public function __construct(string $base_currency)
    {
        $this->base_currency = $base_currency;
        $this->locale = Locale::getDefault();
    }

    /**
     * @param string $value The value that should be
     * @param Currency|null $currency
     * @param int $decimals
     * @return string
     */
    public function format(string $value, ?Currency $currency = null, $decimals = 5)
    {
        $iso_code = $this->base_currency;
        if ($currency !== null && !empty($currency->getIsoCode())) {
            $iso_code = $currency->getIsoCode();
        }

        $number_formatter = new \NumberFormatter($this->locale, \NumberFormatter::CURRENCY);
        $number_formatter->setAttribute(\NumberFormatter::FRACTION_DIGITS, $decimals);

        return $number_formatter->formatCurrency((float) $value, $iso_code);
    }

}