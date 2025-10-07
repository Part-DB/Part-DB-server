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

/**
 * A service that helps you to format values using the SI prefixes.
 * @see \App\Tests\Services\Formatters\SIFormatterTest
 */
class SIFormatter
{
    /**
     * Returns the magnitude of a value (the count of decimal place of the highest decimal place).
     * For example, for 100 (=10^2) this function returns 2. For -2500 (=-2.5*10^3) this function returns 3.
     *
     * @param float $value the value of which the magnitude should be determined
     *
     * @return int The magnitude of the value
     */
    public function getMagnitude(float $value): int
    {
        return intval(floor(log10(abs($value))));
    }

    /**
     * Returns the best SI prefix (and its corresponding divisor) for the given magnitude.
     *
     * @param int $magnitude the magnitude for which the prefix should be determined
     *
     * @return array An array, containing the divisor in first element, and the prefix symbol in second. For example, [1000, "k"].
     */
    public function getPrefixByMagnitude(int $magnitude): array
    {
        $prefixes_pos = ['', 'k', 'M', 'G', 'T', 'P', 'E', 'Z', 'Y'];
        $prefixes_neg = ['', 'm', 'μ', 'n', 'p', 'f', 'a', 'z', 'y'];

        if ($magnitude >= 0) {
            $nearest = (int) floor(abs($magnitude) / 3);
            $symbol = $prefixes_pos[$nearest];
        } else {
            $nearest = (int) round(abs($magnitude) / 3);
            $symbol = $prefixes_neg[$nearest];
        }

        if ($magnitude < 0) {
            $nearest *= -1;
        }

        return [10 ** (3 * $nearest), $symbol];
    }

    public function convertValue(float $value): array
    {
        //Choose the prefix to use
        $tmp = $this->getPrefixByMagnitude($this->getMagnitude($value));

        return [
            'value' => $value / $tmp[0],
            'prefix_magnitude' => log10($tmp[0]),
            'prefix' => $tmp[1],
        ];
    }

    /**
     * Formats the given value to a string, using the given options.
     *
     * @param float  $value    The value that should be converted
     * @param string $unit     The unit that should be appended after the prefix
     * @param int    $decimals the number of decimals (after decimal dot) that should be outputed
     *
     * @return string The formatted value
     */
    public function format(float $value, string $unit = '', int $decimals = 2): string
    {
        [$divisor, $symbol] = $this->getPrefixByMagnitude($this->getMagnitude($value));
        $value /= $divisor;
        //Build the format string, e.g.: %.2d km
        $format_string = '' !== $unit || '' !== $symbol ? '%.'.$decimals.'f '.$symbol.$unit : '%.'.$decimals.'f';

        return sprintf($format_string, $value);
    }
}
