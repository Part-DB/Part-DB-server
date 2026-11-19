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

namespace App\Services\EntitySystem;

use App\Entity\Attribute\EntityConfig;

/**
 * Read-only value object wrapping a resolved #[EntityConfig] attribute for a given entity class,
 * enriched with attachment and parameter classes derived from Doctrine association metadata.
 */
final class EntityConfigData
{
    public function __construct(
        private readonly EntityConfig $attr,
        private readonly string $entityClass,
        /** Detected from the Doctrine 'attachments' association, or null if none exists. */
        private readonly ?string $attachmentClass,
        /** Detected from the Doctrine 'parameters' association, or null if none exists. */
        private readonly ?string $parameterClass,
    ) {
    }

    /** @return class-string */
    public function getEntityClass(): string
    {
        return $this->entityClass;
    }

    public function getRouteBase(): string
    {
        return $this->attr->routeBase;
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
}
