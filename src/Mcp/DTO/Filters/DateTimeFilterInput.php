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

/**
 * A single date/time constraint used by advanced_search_parts, e.g. {"operator": ">=", "value": "2026-01-01"} or
 * {"operator": "BETWEEN", "value": "2026-01-01", "value2": "2026-06-30"}. Values are parsed with PHP's usual
 * date/time string parsing, so both plain dates ("2026-01-01") and full ISO8601 timestamps are accepted.
 */
readonly class DateTimeFilterInput
{
    /**
     * @param string      $operator One of "=", "!=", "<", ">", "<=", ">=", "BETWEEN".
     * @param string      $value    The date/time to compare against (the lower bound when operator is "BETWEEN").
     * @param string|null $value2   The upper bound, required only when operator is "BETWEEN".
     */
    public function __construct(
        public string $operator,
        public string $value,
        public ?string $value2 = null,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            operator: (string) ($data['operator'] ?? '='),
            value: (string) ($data['value'] ?? ''),
            value2: isset($data['value2']) ? (string) $data['value2'] : null,
        );
    }
}
