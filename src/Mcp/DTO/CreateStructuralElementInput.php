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

/**
 * Input shared by create_category/create_footprint/create_manufacturer/create_storage_location/create_supplier.
 * Only "name" is required; every other field is optional and, if omitted, the element is created with its
 * normal default value for that field.
 */
final readonly class CreateStructuralElementInput extends AbstractStructuralElementWriteInput
{
    public function __construct(
        /** The name of the new element. Required, cannot be empty. */
        public string $name,
        ?string $comment,
        ?bool $notSelectable,
        ?int $parentId,
        ?string $alternativeNames,
        ?string $logComment,
        array $providedFields,
    ) {
        parent::__construct(
            comment: $comment,
            notSelectable: $notSelectable,
            parentId: $parentId,
            alternativeNames: $alternativeNames,
            logComment: $logComment,
            providedFields: $providedFields,
        );
    }

    public static function fromArray(array $data): self
    {
        return new self(
            ...self::extractSharedFields($data),
            name: (string) ($data['name'] ?? ''),
            providedFields: array_fill_keys(array_keys($data), true),
        );
    }
}
