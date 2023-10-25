<?php
/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2023 Jan Böhmer (https://github.com/jbtronics)
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


namespace App\Services\InfoProviderSystem\DTOs;

use Brick\Math\BigDecimal;

/**
 * This DTO represents a price for a single unit in a certain discount range
 */
class PriceDTO
{
    private readonly BigDecimal $price_as_big_decimal;

    public function __construct(
        /** @var float The minimum amount that needs to get ordered for this price to be valid */
        public readonly float $minimum_discount_amount,
        /** @var string The price as string (with .) */
        public readonly string $price,
        /** @var string The currency of the used ISO code of this price detail */
        public readonly ?string $currency_iso_code,
        /** @var bool If the price includes tax */
        public readonly ?bool $includes_tax = true,
        /** @var float the price related quantity */
        public readonly ?float $price_related_quantity = 1.0,
    )
    {
        $this->price_as_big_decimal = BigDecimal::of($this->price);
    }

    /**
     * Gets the price as BigDecimal
     * @return BigDecimal
     */
    public function getPriceAsBigDecimal(): BigDecimal
    {
        return $this->price_as_big_decimal;
    }
}
