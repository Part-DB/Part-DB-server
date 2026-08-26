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
 * Input for update_part. Every field besides `id` is optional and, unlike create_part, a field that is *omitted*
 * from the tool call must leave the corresponding part property untouched - that's what wasProvided() (declared
 * on AbstractPartWriteInput) is for. Nested collections (partLots, parameters, orderdetails,
 * associatedPartsAsOwner) follow the same rule at the top level: the collection is only reconciled at all if its
 * key was provided (an omitted key leaves the whole collection untouched; an explicit empty array removes every
 * existing item).
 */
final readonly class UpdatePartInput extends AbstractPartWriteInput
{
    /**
     * @param int         $id   The database ID of the part to update. Required.
     * @param string|null $name Pass null (or "") to clear - though an empty name will likely fail validation.
     */
    public function __construct(
        public int $id,
        public ?string $name,
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
        ?string $providerKey,
        ?string $providerId,
        ?string $providerUrl,
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
            providerKey: $providerKey,
            providerId: $providerId,
            providerUrl: $providerUrl,
            logComment: $logComment,
            providedFields: $providedFields,
        );
    }

    public static function fromArray(array $data): self
    {
        return new self(
            ...self::extractSharedFields($data),
            id: (int) ($data['id'] ?? 0),
            name: PartInputHelpers::str($data, 'name'),
            providedFields: array_fill_keys(array_keys($data), true),
        );
    }
}
