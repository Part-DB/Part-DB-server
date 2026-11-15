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

namespace App\Entity\Attribute;

/**
 * Declares entity-level metadata usable across the whole application.
 *
 * Place this attribute on any entity class that participates in the CRUD
 * or attachment/parameter systems — including Part, which is not managed
 * via the generic admin CRUD system but still has routes and associations.
 *
 * Attachment and parameter classes are auto-detected from the Doctrine
 * association metadata (fields named 'attachments' and 'parameters').
 *
 * @see \App\Services\EntitySystem\EntityConfigRegistry
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class EntityConfig
{
    public function __construct(
        /** Route base used to build URLs: 'category' → 'category_edit', 'category_new', etc. */
        public readonly string $routeBase,
    ) {
    }
}
