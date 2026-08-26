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
 * Input for create_part. Only `name` is required; every other field is optional and, if omitted, the part is
 * created with the entity's normal default value for that field. See AbstractPartWriteInput for the shared
 * fields and why even create_part tracks field presence.
 */
final readonly class CreatePartInput extends AbstractPartWriteInput
{
    /**
     * @param string $name The name of the new part. Required, cannot be empty.
     */
    public function __construct(
        public string $name,
        ?string $description,
        ?string $comment,
        ?bool $favorite,
        ?int $categoryId,
        ?int $footprintId,
        ?int $manufacturerId,
        ?string $manufacturerProductUrl,
        ?string $manufacturerProductNumber,
        ?string $manufacturingStatus,
        ?float $minAmount,
        ?int $partUnitId,
        ?bool $needsReview,
        ?string $tags,
        ?float $mass,
        ?string $ipn,
        ?int $partCustomStateId,
        ?string $gtin,
        ?int $masterPictureAttachmentId,
        array $partLots,
        array $parameters,
        array $orderdetails,
        array $associatedPartsAsOwner,
        ?EdaInfoInput $edaInfo,
        ?string $logComment,
        array $providedFields,
    ) {
        parent::__construct(
            description: $description,
            comment: $comment,
            favorite: $favorite,
            categoryId: $categoryId,
            footprintId: $footprintId,
            manufacturerId: $manufacturerId,
            manufacturerProductUrl: $manufacturerProductUrl,
            manufacturerProductNumber: $manufacturerProductNumber,
            manufacturingStatus: $manufacturingStatus,
            minAmount: $minAmount,
            partUnitId: $partUnitId,
            needsReview: $needsReview,
            tags: $tags,
            mass: $mass,
            ipn: $ipn,
            partCustomStateId: $partCustomStateId,
            gtin: $gtin,
            masterPictureAttachmentId: $masterPictureAttachmentId,
            partLots: $partLots,
            parameters: $parameters,
            orderdetails: $orderdetails,
            associatedPartsAsOwner: $associatedPartsAsOwner,
            edaInfo: $edaInfo,
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
