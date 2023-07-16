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
        if (is_float($value) || is_numeric($value)) {
            return new self($name, value_typ: (float) $value, unit: $unit, symbol: $symbol, group: $group);
        }

        //Try to parse as range
        if (str_contains($value, '...')) {
            $parts = explode('...', $value);
            if (count($parts) === 2) {

                //Ensure that both parts are numerical
                if (is_numeric($parts[0]) && is_numeric($parts[1])) {
                    return new self($name, value_min: (float) $parts[0], value_max: (float) $parts[1], unit: $unit, symbol: $symbol, group: $group);
                }
            }
        }

        return new self($name, value_text: $value, unit: $unit, symbol: $symbol, group: $group);
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
        if (is_string($value) && preg_match('/^(?<value>[0-9.]+)\s*(?<unit>[°a-zA-Z_]+\s?\w{0,4})$/u', $value, $matches)) {
            $value = $matches['value'];
            $unit = $matches['unit'];

            return self::parseValueField(name: $name, value: $value, unit: $unit, symbol: $symbol, group: $group);
        }

        //Otherwise we assume that no unit is given
        return self::parseValueField(name: $name, value: $value, unit: null, symbol: $symbol, group: $group);
    }
}