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
 * A single text-field constraint used by advanced_search_parts, e.g. {"operator": "CONTAINS", "value": "resistor"}.
 */
readonly class TextFilterInput
{
    /**
     * @param string $operator One of "=", "!=", "STARTS", "ENDS", "CONTAINS", "LIKE", "REGEX". LIKE accepts raw
     *                         "%"/"_" SQL wildcards; the other non-equality operators escape them and add their
     *                         own wildcards automatically. REGEX is only supported when the database is MySQL/MariaDB.
     * @param string $value    The text to compare against.
     */
    public function __construct(
        public string $operator,
        public string $value,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            operator: (string) ($data['operator'] ?? '='),
            value: (string) ($data['value'] ?? ''),
        );
    }
}
