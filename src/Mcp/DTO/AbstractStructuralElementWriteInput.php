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
 * Base for CreateStructuralElementInput/UpdateStructuralElementInput: every field the two operations share
 * (everything except "name", whose required-ness differs, and "id", which only update has), plus wasProvided()
 * presence-tracking. Mirrors AbstractPartWriteInput's design one-to-one, just for the much smaller field set
 * every AbstractStructuralDBElement subclass (Category, Footprint, Manufacturer, StorageLocation, Supplier, ...)
 * shares - "parentId" points at another element of the *same* concrete class (a Category's parent is always
 * another Category, etc.), resolved by the processor via the same class as the element itself.
 */
abstract readonly class AbstractStructuralElementWriteInput
{
    /**
     * @param array<string, bool> $providedFields Which top-level keys were actually present in the raw MCP arguments
     */
    public function __construct(
        /** Pass null (or "") to clear. */
        public ?string $comment,
        /** Pass null to reset to false. */
        public ?bool $notSelectable,
        /** Pass null to move this element to the root level (no parent). */
        public ?int $parentId,
        /** Comma-separated alternative names, used for searching (e.g. by the info provider system). Pass null (or "") to clear. */
        public ?string $alternativeNames,
        /** Optional message for the audit log; not a field of the element itself. */
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
     * own fromArray() and merge in their own extra fields (CreateStructuralElementInput: "name";
     * UpdateStructuralElementInput: "id", "name").
     *
     * @return array{
     *     comment: string|null,
     *     notSelectable: bool|null,
     *     parentId: int|null,
     *     alternativeNames: string|null,
     *     logComment: string|null,
     * }
     */
    protected static function extractSharedFields(array $data): array
    {
        return [
            'comment' => PartInputHelpers::str($data, 'comment'),
            'notSelectable' => PartInputHelpers::bool($data, 'notSelectable'),
            'parentId' => PartInputHelpers::int($data, 'parentId'),
            'alternativeNames' => PartInputHelpers::str($data, 'alternativeNames'),
            'logComment' => PartInputHelpers::str($data, 'logComment'),
        ];
    }
}
