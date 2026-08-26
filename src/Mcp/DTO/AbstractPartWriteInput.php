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
 * Base for CreatePartInput/UpdatePartInput: every Part field the two operations have in common (everything
 * except "name", whose required-ness differs, and "id", which only update_part has), plus wasProvided()
 * presence-tracking.
 *
 * create_part tracks presence too, not just update_part, even though a brand-new Part has no prior value to
 * preserve - because that's what lets AbstractPartMutationProcessor::applyProvidedFields() use the exact same
 * "if wasProvided(field), apply it" logic for both operations, instead of CreatePartProcessor duplicating a
 * second copy of the same ~20 field-by-field assignments with `!== null` checks instead of wasProvided() checks.
 * For create, "not provided" and "provided as null" both correctly result in the entity's own constructor default,
 * exactly like leaving the setter uncalled would.
 *
 * The per-field @param notes below describe update_part's semantics, where the distinction actually matters:
 * omitting a key leaves the part's current value untouched, while including the key with value `null` clears
 * it - to actual null for fields that are genuinely nullable on the entity (mass, ipn, gtin, and the relation
 * ids below), or to the entity's empty default for fields that aren't (description/comment/tags become "",
 * favorite/needsReview become false, minAmount becomes 0). For create_part both cases - omitted or explicit
 * null - simply produce that same default, since there is no prior value to preserve.
 */
abstract readonly class AbstractPartWriteInput
{
    /**
     * @param string|null $description               Pass null (or "") to clear.
     * @param string|null $comment                    Pass null (or "") to clear.
     * @param bool|null   $favorite                    Pass null to reset to false.
     * @param int|null    $categoryId                  Can be changed to another category but not cleared - every part must have one.
     * @param int|null    $footprintId                 Pass null to remove the footprint.
     * @param int|null    $manufacturerId              Pass null to remove the manufacturer.
     * @param string|null $manufacturerProductUrl      Pass null (or "") to clear.
     * @param string|null $manufacturerProductNumber   Pass null (or "") to clear.
     * @param string|null $manufacturingStatus         Pass null to reset to "not set".
     * @param float|null  $minAmount                    Pass null to reset to 0.
     * @param int|null    $partUnitId                  Pass null to remove the measurement unit (falls back to plain quantities).
     * @param bool|null   $needsReview                 Pass null to reset to false.
     * @param string|null $tags                        Pass null (or "") to clear all tags.
     * @param float|null  $mass                         Pass null to clear (mass becomes unknown).
     * @param string|null $ipn                         Pass null to clear the internal part number.
     * @param int|null    $partCustomStateId           Pass null to remove the custom state.
     * @param string|null $gtin                        Pass null to clear the GTIN.
     * @param int|null    $masterPictureAttachmentId   Pass null to remove the master picture.
     * @param array<int, array<string, mixed>> $partLots               Raw nested items, converted via PartLotInput::fromArray(). Omitting this key leaves the whole collection untouched; an explicit empty array removes every existing lot.
     * @param array<int, array<string, mixed>> $parameters             Raw nested items, converted via ParameterInput::fromArray(). Omitting this key leaves the whole collection untouched; an explicit empty array removes every existing parameter.
     * @param array<int, array<string, mixed>> $orderdetails           Raw nested items, converted via OrderdetailInput::fromArray(). Omitting this key leaves the whole collection untouched; an explicit empty array removes every existing orderdetail.
     * @param array<int, array<string, mixed>> $associatedPartsAsOwner Raw nested items, converted via AssociatedPartInput::fromArray(). Omitting this key leaves the whole collection untouched; an explicit empty array removes every existing association.
     * @param EdaInfoInput|null $edaInfo                Pass null to clear every EDA info field. When provided, wholesale-replaces all of them rather than merging field-by-field.
     * @param string|null $logComment                  Optional message for the audit log; not a part field itself.
     * @param array<string, bool>              $providedFields         Which top-level keys were actually present in the raw MCP arguments
     */
    public function __construct(
        public ?string $description,
        public ?string $comment,
        public ?bool $favorite,
        public ?int $categoryId,
        public ?int $footprintId,
        public ?int $manufacturerId,
        public ?string $manufacturerProductUrl,
        public ?string $manufacturerProductNumber,
        public ?string $manufacturingStatus,
        public ?float $minAmount,
        public ?int $partUnitId,
        public ?bool $needsReview,
        public ?string $tags,
        public ?float $mass,
        public ?string $ipn,
        public ?int $partCustomStateId,
        public ?string $gtin,
        public ?int $masterPictureAttachmentId,
        public array $partLots,
        public array $parameters,
        public array $orderdetails,
        public array $associatedPartsAsOwner,
        public ?EdaInfoInput $edaInfo,
        public ?string $logComment,
        private array $providedFields,
    ) {
    }

    public function wasProvided(string $field): bool
    {
        return $this->providedFields[$field] ?? false;
    }

    /**
     * Parses every field this base class owns out of a raw MCP arguments array. Subclasses call this from their
     * own fromArray() and merge in their own extra fields (CreatePartInput: "name"; UpdatePartInput: "id", "name").
     *
     * The precise array shape (rather than a plain array<string, mixed>) lets PHPStan verify that spreading this
     * into `new self(...)` actually supplies exactly the parent constructor's remaining named parameters.
     *
     * @return array{
     *     description: string|null,
     *     comment: string|null,
     *     favorite: bool|null,
     *     categoryId: int|null,
     *     footprintId: int|null,
     *     manufacturerId: int|null,
     *     manufacturerProductUrl: string|null,
     *     manufacturerProductNumber: string|null,
     *     manufacturingStatus: string|null,
     *     minAmount: float|null,
     *     partUnitId: int|null,
     *     needsReview: bool|null,
     *     tags: string|null,
     *     mass: float|null,
     *     ipn: string|null,
     *     partCustomStateId: int|null,
     *     gtin: string|null,
     *     masterPictureAttachmentId: int|null,
     *     partLots: array<int, array<string, mixed>>,
     *     parameters: array<int, array<string, mixed>>,
     *     orderdetails: array<int, array<string, mixed>>,
     *     associatedPartsAsOwner: array<int, array<string, mixed>>,
     *     edaInfo: EdaInfoInput|null,
     *     logComment: string|null,
     * }
     */
    protected static function extractSharedFields(array $data): array
    {
        return [
            'description' => PartInputHelpers::str($data, 'description'),
            'comment' => PartInputHelpers::str($data, 'comment'),
            'favorite' => PartInputHelpers::bool($data, 'favorite'),
            'categoryId' => PartInputHelpers::int($data, 'categoryId'),
            'footprintId' => PartInputHelpers::int($data, 'footprintId'),
            'manufacturerId' => PartInputHelpers::int($data, 'manufacturerId'),
            'manufacturerProductUrl' => PartInputHelpers::str($data, 'manufacturerProductUrl'),
            'manufacturerProductNumber' => PartInputHelpers::str($data, 'manufacturerProductNumber'),
            'manufacturingStatus' => PartInputHelpers::str($data, 'manufacturingStatus'),
            'minAmount' => PartInputHelpers::float($data, 'minAmount'),
            'partUnitId' => PartInputHelpers::int($data, 'partUnitId'),
            'needsReview' => PartInputHelpers::bool($data, 'needsReview'),
            'tags' => PartInputHelpers::str($data, 'tags'),
            'mass' => PartInputHelpers::float($data, 'mass'),
            'ipn' => PartInputHelpers::str($data, 'ipn'),
            'partCustomStateId' => PartInputHelpers::int($data, 'partCustomStateId'),
            'gtin' => PartInputHelpers::str($data, 'gtin'),
            'masterPictureAttachmentId' => PartInputHelpers::int($data, 'masterPictureAttachmentId'),
            'partLots' => PartInputHelpers::arr($data, 'partLots'),
            'parameters' => PartInputHelpers::arr($data, 'parameters'),
            'orderdetails' => PartInputHelpers::arr($data, 'orderdetails'),
            'associatedPartsAsOwner' => PartInputHelpers::arr($data, 'associatedPartsAsOwner'),
            'edaInfo' => is_array($data['edaInfo'] ?? null) ? EdaInfoInput::fromArray($data['edaInfo']) : null,
            'logComment' => PartInputHelpers::str($data, 'logComment'),
        ];
    }
}
