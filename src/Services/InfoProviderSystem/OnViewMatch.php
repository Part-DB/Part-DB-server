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


namespace App\Services\InfoProviderSystem;

use App\Entity\PriceInformations\Orderdetail;
use App\Services\InfoProviderSystem\Providers\InfoProviderInterface;

/**
 * Describes how a part without info provider data was recognized as a product of an info provider, by one of its
 * orderdetails (see ProviderOnViewMatcher).
 */
final readonly class OnViewMatch
{
    /**
     * @param  InfoProviderInterface  $provider The provider which can supply the data of the part
     * @param  Orderdetail  $orderdetail The orderdetail of the part, which led to the provider
     * @param  string|null  $providerId The ID of the product at the provider, if the product URL of the orderdetail
     * gave it away. Null if the product has to be looked up by the supplier part number first.
     * @param  string|null  $supplierPartNr The supplier part number to look the product up with, if $providerId is null
     */
    public function __construct(
        public InfoProviderInterface $provider,
        public Orderdetail $orderdetail,
        public ?string $providerId = null,
        public ?string $supplierPartNr = null,
    ) {
    }

    public function getProviderKey(): string
    {
        return $this->provider->getProviderInfo()->key;
    }

    public function getProviderName(): string
    {
        return $this->provider->getProviderInfo()->name;
    }
}
