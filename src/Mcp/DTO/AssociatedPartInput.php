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
 * A single association to another part (e.g. "is a replacement for"), nested inside create_part/update_part as
 * `associatedPartsAsOwner`. A present `id` targets an existing association (belonging to the same part); an
 * absent `id` creates a new one. `type` is the name of an App\Entity\Parts\AssociationType enum case
 * (e.g. "COMPATIBLE", "SUPERSEDES", "OTHER").
 */
readonly class AssociatedPartInput
{
    public function __construct(
        public ?int $id,
        public int $otherPartId,
        public string $type,
        public ?string $otherType,
        public ?string $comment,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            id: PartInputHelpers::int($data, 'id'),
            otherPartId: (int) ($data['otherPartId'] ?? 0),
            type: (string) ($data['type'] ?? 'OTHER'),
            otherType: PartInputHelpers::str($data, 'otherType'),
            comment: PartInputHelpers::str($data, 'comment'),
        );
    }
}
