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
 * EDA (electronic design automation) info for a part. Unlike the other nested inputs, this has no `id` (it's a
 * singular embeddable, not a to-many collection): when provided on update_part, it wholesale-replaces all fields
 * of the part's EDA info rather than being merged field-by-field.
 */
readonly class EdaInfoInput
{
    public function __construct(
        public ?string $referencePrefix,
        public ?string $value,
        public ?bool $visibility,
        public ?bool $excludeFromBom,
        public ?bool $excludeFromBoard,
        public ?bool $excludeFromSim,
        public ?string $kicadSymbol,
        public ?string $kicadFootprint,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            referencePrefix: PartInputHelpers::str($data, 'referencePrefix'),
            value: PartInputHelpers::str($data, 'value'),
            visibility: PartInputHelpers::bool($data, 'visibility'),
            excludeFromBom: PartInputHelpers::bool($data, 'excludeFromBom'),
            excludeFromBoard: PartInputHelpers::bool($data, 'excludeFromBoard'),
            excludeFromSim: PartInputHelpers::bool($data, 'excludeFromSim'),
            kicadSymbol: PartInputHelpers::str($data, 'kicadSymbol'),
            kicadFootprint: PartInputHelpers::str($data, 'kicadFootprint'),
        );
    }
}
