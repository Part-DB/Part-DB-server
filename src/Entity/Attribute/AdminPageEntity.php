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

use App\Form\AdminPages\BaseEntityAdminForm;

/**
 * Declares that an entity class is managed by the generic admin CRUD system.
 * Place this attribute on the entity class (not the controller).
 *
 * Attachment and parameter classes are auto-detected from the Doctrine association
 * metadata (fields named 'attachments' and 'parameters').
 *
 * @see \App\Services\AdminPages\AdminPageEntityRegistry
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class AdminPageEntity
{
    public function __construct(
        /** Route prefix used for redirect targets, e.g. 'category' → 'category_edit', 'category_new' */
        public readonly string $routeBase,
        /** Form class used to build the admin edit/new form. Defaults to the generic base form. */
        public readonly string $formClass = BaseEntityAdminForm::class,
        /** Twig template path. Empty string uses the convention 'admin/{routeBase}_admin.html.twig'. */
        public readonly string $templatePath = '',
        /** Set to false to suppress the Parameters tab (even if the entity has a 'parameters' association). */
        public readonly bool   $showParametersTab = true,
    ) {
    }
}
