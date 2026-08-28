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
 * A single supplier order detail, nested inside create_part/update_part. A present `id` targets an existing
 * orderdetail (belonging to the same part); an absent `id` creates a new one.
 */
readonly class OrderdetailInput
{
    /**
     * @param array<int, array<string, mixed>> $pricedetails Raw nested price-break items, converted via PricedetailInput::fromArray()
     */
    public function __construct(
        public ?int $id,
        public int $supplierId,
        public ?string $supplierPartNr,
        public ?bool $obsolete,
        public ?string $supplierProductUrl,
        public ?bool $pricesIncludesVat,
        public array $pricedetails,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            id: PartInputHelpers::int($data, 'id'),
            supplierId: (int) ($data['supplierId'] ?? 0),
            supplierPartNr: PartInputHelpers::str($data, 'supplierPartNr'),
            obsolete: PartInputHelpers::bool($data, 'obsolete'),
            supplierProductUrl: PartInputHelpers::str($data, 'supplierProductUrl'),
            pricesIncludesVat: PartInputHelpers::bool($data, 'pricesIncludesVat'),
            pricedetails: PartInputHelpers::arr($data, 'pricedetails'),
        );
    }
}
