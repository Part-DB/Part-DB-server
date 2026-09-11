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
 * A single price break, nested inside an OrderdetailInput. A present `id` targets an existing pricedetail
 * (belonging to the same orderdetail); an absent `id` creates a new one.
 */
readonly class PricedetailInput
{
    public function __construct(
        public ?int $id,
        public string $price,
        public ?int $currencyId,
        public ?float $priceRelatedQuantity,
        public ?float $minDiscountQuantity,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            id: PartInputHelpers::int($data, 'id'),
            price: (string) ($data['price'] ?? '0'),
            currencyId: PartInputHelpers::int($data, 'currencyId'),
            priceRelatedQuantity: PartInputHelpers::float($data, 'priceRelatedQuantity'),
            minDiscountQuantity: PartInputHelpers::float($data, 'minDiscountQuantity'),
        );
    }
}
