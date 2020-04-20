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

namespace App\Services\Misc;

/**
 * This Parser allows to parse number ranges like 1-3, 4, 5
 */
class RangeParser
{
    /**
     * Converts the given range string to an array of all numbers in the given range.
     * @param  string  $range A range string like '1-3, 5, 6'
     * @return int[] An array with all numbers from the range (e.g. [1, 2, 3, 5, 6]
     */
    public function parse(string $range_str): array
    {
        //Normalize number separator (we allow , and ;):
        $range_str = str_replace(';', ',', $range_str);

        $numbers = explode(',', $range_str);
        $ranges = [];
        foreach ($numbers as $number) {
            $number = trim($number);
            //Extract min / max if token is a range
            $matches = [];
            if (preg_match('/^(-?\s*\d+)\s*-\s*(-?\s*\d+)$/', $number, $matches)) {
                $ranges[] = $this->generateMinMaxRange($matches[1], $matches[2]);
            } elseif (is_numeric($number)) {
                $ranges[] = [(int) $number];
            } elseif (empty($number)) { //Allow empty tokens
                continue;
            } else {
                throw new \InvalidArgumentException('Invalid range encoutered: ' . $number);
            }
        }

        //Flatten ranges array
        return array_merge([], ...$ranges);
    }

    /**
     * Checks if the given string is a valid range.
     * @param  string  $range_str The string that should be checked
     * @return bool True if the string is valid, false if not.
     */
    public function isValidRange(string $range_str): bool
    {
        try {
            $this->parse($range_str);
            return true;
        } catch (\InvalidArgumentException $exception) {
            return false;
        }
    }

    protected function generateMinMaxRange(string $min, string $max): array
    {
        $min = (int) $min;
        $max = (int) $max;

        //Ensure that $max > $min
        if ($min > $max) {
            $a = $max;
            $max = $min;
            $min = $a;
        }

        $tmp = [];
        while ($min <= $max) {
            $tmp[] = $min;
            $min++;
        };

        return $tmp;
    }
}