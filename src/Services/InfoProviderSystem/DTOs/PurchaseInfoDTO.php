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

/**
 * This DTO represents a purchase information for a part (supplier name, order number and prices).
 * @see \App\Tests\Services\InfoProviderSystem\DTOs\PurchaseInfoDTOTest
 */
readonly class PurchaseInfoDTO
{
    public function __construct(
        public string $distributor_name,
        public string $order_number,
        /** @var PriceDTO[] */
        public array $prices,
        /** @var string|null An url to the product page of the vendor */
        public ?string $product_url = null,
    )
    {
        //Ensure that the prices are PriceDTO instances
        foreach ($this->prices as $price) {
            if (!$price instanceof PriceDTO) {
                throw new \InvalidArgumentException('The prices array must only contain PriceDTO instances');
            }
        }
    }
}
