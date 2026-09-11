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
 * A single numeric constraint used by advanced_search_parts, e.g. {"operator": ">=", "value": 10} or
 * {"operator": "BETWEEN", "value": 10, "value2": 20}.
 */
readonly class NumberFilterInput
{
    /**
     * @param string     $operator One of "=", "!=", "<", ">", "<=", ">=", "BETWEEN".
     * @param float      $value    The value to compare against (the lower bound when operator is "BETWEEN").
     * @param float|null $value2   The upper bound, required only when operator is "BETWEEN".
     */
    public function __construct(
        public string $operator,
        public float $value,
        public ?float $value2 = null,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            operator: (string) ($data['operator'] ?? '='),
            value: (float) ($data['value'] ?? 0),
            value2: isset($data['value2']) ? (float) $data['value2'] : null,
        );
    }
}
