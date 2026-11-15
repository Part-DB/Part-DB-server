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

use App\Entity\Attribute\AdminPageEntity;

/**
 * Read-only value object that wraps a resolved #[AdminCrud] attribute for a given entity class,
 * enriched with attachment and parameter classes derived from Doctrine association metadata.
 */
final class AdminPageEntityConfig
{
    public function __construct(
        private readonly AdminPageEntity $attr,
        private readonly string $entityClass,
        /** Detected from the Doctrine 'attachments' association, or null if none exists. */
        private readonly ?string $attachmentClass,
        /** Detected from the Doctrine 'parameters' association, or null if none exists. */
        private readonly ?string $parameterClass,
    ) {
    }

    public function getEntityClass(): string
    {
        return $this->entityClass;
    }

    public function getFormClass(): string
    {
        return $this->attr->formClass;
    }

    public function getRouteBase(): string
    {
        return $this->attr->routeBase;
    }

    public function getTemplatePath(): string
    {
        if ($this->attr->templatePath !== '') {
            return $this->attr->templatePath;
        }

        return 'admin/' . $this->attr->routeBase . '_admin.html.twig';
    }

    public function getAttachmentClass(): ?string
    {
        return $this->attachmentClass;
    }

    public function getParameterClass(): ?string
    {
        return $this->parameterClass;
    }

    public function hasAttachments(): bool
    {
        return $this->attachmentClass !== null;
    }

    public function hasParameters(): bool
    {
        return $this->parameterClass !== null;
    }

    public function showParametersTab(): bool
    {
        return $this->attr->showParametersTab && $this->hasParameters();
    }
}
