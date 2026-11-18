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

namespace App\Services\AdminPages;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Per-entity hook interface for the admin page system.
 * Implement this to add custom behaviour for a specific entity's admin pages.
 * Register an implementation by setting extensionClass on the entity's #[AdminCrud] attribute.
 */
#[AutoconfigureTag]
interface AdminPageExtensionInterface
{
    /**
     * Called at the very start of the edit action, before the form is built.
     * Throw an exception or return a Response to short-circuit.
     * Return null to proceed normally.
     */
    public function beforeEdit(object $entity, Request $request): ?Response;

    /**
     * Called at the very start of the new/list action.
     * Throw an exception or return a Response to short-circuit.
     * Return null to proceed normally.
     */
    public function beforeNew(Request $request): ?Response;

    /**
     * Called at the very start of the delete action, before the CSRF check.
     * Throw an exception or return a Response to short-circuit.
     * Return null to proceed normally.
     */
    public function beforeDelete(object $entity, Request $request): ?Response;

    /**
     * Called when the edit form is valid, before the entity is persisted.
     * Return false to abort the save (e.g. because a button click was handled already).
     */
    public function onEditFormValid(FormInterface $form, object $entity): bool;

    /**
     * Called when the new/create form is valid, before the entity is persisted.
     * Return false to abort the save.
     */
    public function onNewFormValid(FormInterface $form, object $entity): bool;

    /**
     * Extra delete safety check, called after the CSRF check but before removal.
     * Flash an error message and return false to abort the deletion.
     */
    public function checkDelete(object $entity): bool;

    /**
     * Extra form options merged into the form factory call for both edit and new actions.
     */
    public function formOptions(object $entity): array;

    /**
     * Extra template variables merged into the render call for both edit and new actions.
     */
    public function additionalTemplateVars(object $entity, FormInterface $form): array;
}
