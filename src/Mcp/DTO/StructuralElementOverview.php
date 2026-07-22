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

namespace App\Mcp\DTO;

use Symfony\Component\Serializer\Annotation\Groups;

/**
 * A lean overview projection of a structural "master data" element (category, footprint, manufacturer, ...),
 * used by the list_X MCP tools. Use the corresponding get_X_details tool with the id to fetch full details.
 *
 * The properties are tagged with the 'mcp_structural_overview:read' group (and the list_X mcp tools set
 * this as their normalizationContext), because otherwise the operation would silently fall back to the
 * backing entity's own (unrelated) normalization groups, which don't match these plain properties and
 * would filter them out entirely.
 */
readonly class StructuralElementOverview
{
    public function __construct(
        /** @var int The database ID of the element, to be used with the corresponding get_X_details tool */
        #[Groups(['mcp_structural_overview:read'])]
        public int $id,
        /** @var string The name of the element */
        #[Groups(['mcp_structural_overview:read'])]
        public string $name,
    ) {
    }
}
