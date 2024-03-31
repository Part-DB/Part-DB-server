<?php
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

declare(strict_types=1);


namespace App\Services\InfoProviderSystem\DTOs;

/**
 * This DTO represents a parameter of a part (similar to the AbstractParameter entity).
 * This could be a voltage, a current, a temperature or similar.
 */
class ParameterDTO
{
    public function __construct(
        public readonly string $name,
        public readonly ?string $value_text = null,
        public readonly ?float $value_typ = null,
        public readonly ?float $value_min = null,
        public readonly ?float $value_max = null,
        public readonly ?string $unit = null,
        public readonly ?string $symbol = null,
        public readonly ?string $group = null,
    ) {

    }

    /**
     * This function tries to decide on the value, if it is a numerical value (which is then stored in one of the value_*) fields) or a text value (which is stored in value_text).
     * It is possible to give ranges like 1...2 here, which will be parsed as value_min: 1.0, value_max: 2.0.
     * @param  string  $name
     * @param  string|float  $value
     * @param  string|null  $unit
     * @param  string|null  $symbol
     * @param  string|null  $group
     * @return self
     */
    public static function parseValueField(string $name, string|float $value, ?string $unit = null, ?string $symbol = null, ?string $group = null): self
    {
        if (is_string($value) && preg_match('/^(.+)(@.+)$/', $value, $matches) === 1) {
            $value = $matches[1];
            $value_text = $matches[2];
        } else {
            $value_text = null;
        }

        if (is_float($value) || is_numeric($value)) {
            return new self($name, value_typ: (float) $value, value_text: $value_text, unit: $unit, symbol: $symbol, group: $group);
        }

        //If the attribute contains a tilde we assume it is a range
        if (preg_match('/(\.{3}|~)/', $value) === 1) {
            $parts = preg_split('/\s*(\.{3}|~)\s*/', $value);
            if (count($parts) === 2) {
                //Try to extract number and unit from value (allow leading +)
                [$number, $unit] = self::splitIntoValueAndUnit(ltrim($parts[0], " +")) ?? [$parts[0], null];
                // If the second part has some extra info, we'll save that into value_text
                if (!empty($unit) && preg_match('/^(.+' . preg_quote($unit) . ')\s*(.+)$/', $parts[1], $matches) > 0) {
                    $parts[1] = $matches[1];
                    $value_text2 = $matches[2];
                }
                [$number2, $unit2] = self::splitIntoValueAndUnit(ltrim($parts[1], " +")) ?? [$parts[1], null];

                //If both parts have the same unit and both values are numerical, we assume it is a range
                if ($unit === $unit2 && is_numeric($number) && is_numeric($number2)) {
                    return new self(name: $name, value_min: (float) $number, value_max: (float) $number2, value_text: $value_text2, unit: $unit, group: null);
                }
            }
        //If it's a plus/minus value, we'll also treat it as a range
        } elseif (str_starts_with($value, '±')) {
          [$number, $unit] = self::splitIntoValueAndUnit(ltrim($value, " ±")) ?? [$value, null];
          if (is_numeric($number)) {
            return new self(name: $name, value_min: -abs((float) $number), value_max: abs((float) $number), unit: $unit, group: null);
          }
        }

        //If no unit was passed to us, try to extract it from the value
        if (empty($unit)) {
            [$value, $unit] = self::splitIntoValueAndUnit($value) ?? [$value, null];
        }

        //Were we successful in trying to reduce the value to a number?
        if ($value_text !== null && is_numeric($value)) {
            return new self($name, value_typ: (float) $value, value_text: $value_text, unit: $unit, symbol: $symbol, group: $group);
        }

        return new self($name, value_text: $value.$value_text, unit: $unit, symbol: $symbol, group: $group);
    }

    /**
     * This function tries to decide on the value, if it is a numerical value (which is then stored in one of the value_*) fields) or a text value (which is stored in value_text).
     * It also tries to extract the unit from the value field (so 3kg will be parsed as value_typ: 3.0, unit: kg).
     * Ranges like 1...2 will be parsed as value_min: 1.0, value_max: 2.0.
     * @param  string  $name
     * @param  string|float  $value
     * @param  string|null  $symbol
     * @param  string|null  $group
     * @return self
     */
    public static function parseValueIncludingUnit(string $name, string|float $value, ?string $symbol = null, ?string $group = null): self
    {
        //Try to extract unit from value
        $unit = null;
        if (is_string($value)) {
            [$number, $unit] = self::splitIntoValueAndUnit($value) ?? [$value, null];

            return self::parseValueField(name: $name, value: $number, unit: $unit, symbol: $symbol, group: $group);
        }

        //Otherwise we assume that no unit is given
        return self::parseValueField(name: $name, value: $value, unit: null, symbol: $symbol, group: $group);
    }

    /**
     * Splits the given value into a value and a unit part if possible.
     * If the value is not in the expected format, null is returned.
     * @param  string  $value The value to split
     * @return array|null An array with the value and the unit part or null if the value is not in the expected format
     * @phpstan-return array{0: string, 1: string}|null
     */
    public static function splitIntoValueAndUnit(string $value): ?array
    {
       if (preg_match('/^(?<value>-?[0-9\.]+)\s*(?<unit>[%Ωµ°℃a-z_\/]+\s?\w{0,4})$/iu', $value, $matches)) {
           $value = $matches['value'];
           $unit = $matches['unit'];

           return [$value, $unit];
       }

         return null;
    }
}
