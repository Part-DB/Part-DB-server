<?php
/**
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 * Copyright (C) 2019 - 2022 Jan Böhmer (https://github.com/jbtronics)
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

namespace App\Services\Formatters;

use App\Entity\PriceInformations\Currency;
use Locale;
use NumberFormatter;

class MoneyFormatter
{
    protected string $locale;

    public function __construct(protected string $base_currency)
    {
        $this->locale = Locale::getDefault();
    }

    /**
     * Format the given value in the given currency.
     *
     * @param string|float  $value           the value that should be formatted
     * @param Currency|null $currency        The currency that should be used for formatting. If null the global one is used
     * @param  int  $decimals        the number of decimals that should be shown
     * @param bool          $show_all_digits if set to true, all digits are shown, even if they are null
     */
    public function format(string|float $value, ?Currency $currency = null, int $decimals = 5, bool $show_all_digits = false): string
    {
        $iso_code = $this->base_currency;
        if ($currency instanceof Currency && ($currency->getIsoCode() !== '')) {
            $iso_code = $currency->getIsoCode();
        }

        $number_formatter = new NumberFormatter($this->locale, NumberFormatter::CURRENCY);
        if ($show_all_digits) {
            $number_formatter->setAttribute(NumberFormatter::FRACTION_DIGITS, $decimals);
        } else {
            $number_formatter->setAttribute(NumberFormatter::MAX_FRACTION_DIGITS, $decimals);
        }

        return $number_formatter->formatCurrency((float) $value, $iso_code);
    }
}
