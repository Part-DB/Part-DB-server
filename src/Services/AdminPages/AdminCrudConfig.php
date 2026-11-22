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

use App\Entity\Attribute\AdminCrud;
use App\Services\EntitySystem\EntityConfigData;

/**
 * Read-only value object combining a #[AdminPageEntity] attribute with the
 * generic #[EntityConfig] data (route base, attachment/parameter classes) for the same entity.
 */
final class AdminCrudConfig
{
    public function __construct(
        private readonly AdminCrud $attr,
        private readonly EntityConfigData $entityConfig,
    ) {
    }

    public function getEntityClass(): string
    {
        return $this->entityConfig->getEntityClass();
    }

    public function getFormClass(): string
    {
        return $this->attr->formClass;
    }

    public function getRouteBase(): string
    {
        return $this->entityConfig->getRouteBase();
    }

    public function getTemplatePath(): string
    {
        if ($this->attr->templatePath !== '') {
            return $this->attr->templatePath;
        }

        return 'admin/' . $this->entityConfig->getRouteBase() . '_admin.html.twig';
    }

    public function getAttachmentClass(): ?string
    {
        return $this->entityConfig->getAttachmentClass();
    }

    public function getParameterClass(): ?string
    {
        return $this->entityConfig->getParameterClass();
    }

    public function hasAttachments(): bool
    {
        return $this->entityConfig->hasAttachments();
    }

    public function hasParameters(): bool
    {
        return $this->entityConfig->hasParameters();
    }

    public function showParametersTab(): bool
    {
        return $this->attr->showParametersTab && $this->hasParameters();
    }

    public function showAlternativeNamesField(): bool
    {
        return $this->attr->showAlternativeNames;
    }

    /** Returns the alphabetical grouping threshold for the admin sidebar tree, or 0 if grouping is disabled. */
    public function getTreeAlphaGroupingThreshold(): int
    {
        return $this->attr->treeAlphaGroupingThreshold;
    }

    /**
     * Returns the FQCN of the AdminPageExtensionInterface service for this entity, or null if none.
     *
     * @return class-string<\App\Services\AdminPages\AdminPageExtensionInterface>|null
     */
    public function getExtensionClass(): ?string
    {
        return $this->attr->extensionClass;
    }
}
