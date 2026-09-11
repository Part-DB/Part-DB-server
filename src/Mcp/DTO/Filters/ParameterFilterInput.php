<?php
/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2026 Jan Böhmer (https://github.com/jbtronics)
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

namespace App\Mcp\DTO\Filters;

use App\Mcp\DTO\PartInputHelpers;

/**
 * Matches parts that have a parameter (e.g. "Resistance: 10k") satisfying the given constraints. Every part in the
 * result must have at least one parameter matching ALL of the conditions given here (name/symbol/unit are exact
 * matches; combine with the numeric "operator"/"value"/"value2" to also constrain the parameter's value).
 *
 * At least one of name/symbol/unit/operator should be given, otherwise this matches any part that has any
 * parameter at all.
 */
readonly class ParameterFilterInput
{
    /**
     * @param string|null $name     Exact parameter name to match (e.g. "Resistance").
     * @param string|null $symbol   Exact parameter symbol to match (e.g. "R").
     * @param string|null $unit     Exact parameter unit to match (e.g. "Ohm").
     * @param string|null $operator Compares against the parameter's typical value (falling back to its min/max
     *                              range for the range-aware operators): "=", "!=", "<", ">", "<=", ">=",
     *                              "BETWEEN" (needs "value"/"value2"), "IN_RANGE"/"NOT_IN_RANGE" (is "value"
     *                              inside the parameter's min/max range?), "GREATER_THAN_RANGE"/"GREATER_EQUAL_RANGE"/
     *                              "LESS_THAN_RANGE"/"LESS_EQUAL_RANGE" (compares "value" against the whole range),
     *                              "RANGE_IN_RANGE" (is the "value"/"value2" range fully inside the parameter's
     *                              range?), "RANGE_INTERSECT_RANGE" (do the two ranges overlap?).
     * @param float|null  $value    The value to compare the parameter against. Required together with "operator".
     * @param float|null  $value2   The upper bound, required only for the "BETWEEN"/"RANGE_*" operators.
     * @param string|null $valueText Text value to compare against the parameter's free-text value (e.g. for
     *                               parameters like "Package: SOT-23" that aren't numeric).
     */
    public function __construct(
        public ?string $name = null,
        public ?string $symbol = null,
        public ?string $unit = null,
        public ?string $operator = null,
        public ?float $value = null,
        public ?float $value2 = null,
        public ?string $valueText = null,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            name: PartInputHelpers::str($data, 'name'),
            symbol: PartInputHelpers::str($data, 'symbol'),
            unit: PartInputHelpers::str($data, 'unit'),
            operator: PartInputHelpers::str($data, 'operator'),
            value: PartInputHelpers::float($data, 'value'),
            value2: PartInputHelpers::float($data, 'value2'),
            valueText: PartInputHelpers::str($data, 'valueText'),
        );
    }
}
