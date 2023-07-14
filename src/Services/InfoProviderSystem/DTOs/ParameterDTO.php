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

    public static function parseValueField(string $name, string|float $value, ?string $unit = null, ?string $symbol = null, ?string $group = null): self
    {
        if (is_float($value) || is_numeric($value)) {
            return new self($name, value_typ: (float) $value, unit: $unit, symbol: $symbol, group: $group);
        }

        return new self($name, value_text: $value, unit: $unit, symbol: $symbol, group: $group);
    }

    public static function parseValueIncludingUnit(string $name, string|float $value, ?string $symbol = null, ?string $group = null): self
    {
        if (is_float($value) || is_numeric($value)) {
            return new self($name, value_typ: (float) $value, symbol: $symbol, group: $group);
        }

        $unit = null;
        if (preg_match('/^(?<value>[0-9.]+)\s*(?<unit>[a-zA-Z]+)$/', $value, $matches)) {
            $value = $matches['value'];
            $unit = $matches['unit'];
        }

        return new self($name, value_text: $value, unit: $unit, symbol: $symbol, group: $group);
    }
}