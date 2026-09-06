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
 * A relation constraint used by advanced_search_parts, e.g. {"operator": "INCLUDING_CHILDREN", "id": 5}, or
 * {"operator": "!=", "id": null} to match parts that have any value set for this relation at all.
 */
readonly class EntityFilterInput
{
    /**
     * @param string   $operator "=" or "!=" for an exact match (or, with "id" omitted/null, "has none set"/"has
     *                           any set"), or "INCLUDING_CHILDREN"/"EXCLUDING_CHILDREN" to also match/exclude every
     *                           descendant of "id" in its tree (categories, footprints, storage locations,
     *                           manufacturers, suppliers, measurement units, part custom states, attachment types
     *                           and projects are all hierarchical, so these two additionally apply here).
     * @param int|null $id      The database ID of the element to compare against. Omit or pass null together with
     *                          "=" to match parts with none set, or "!=" to match parts with any value set.
     */
    public function __construct(
        public string $operator,
        public ?int $id = null,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            operator: (string) ($data['operator'] ?? '='),
            id: isset($data['id']) ? (int) $data['id'] : null,
        );
    }
}
