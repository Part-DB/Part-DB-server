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

use App\Entity\Parts\ManufacturingStatus;

class SearchResultDTO
{
    public function __construct(
        /** @var string The provider key (e.g. "digikey") */
        public readonly string $provider_key,
        /** @var string The ID which identifies the part in the provider system */
        public readonly string $provider_id,
        /** @var string The name of the part  */
        public readonly string $name,
        /** @var string A short description of the part */
        public readonly string $description,
        /** @var string|null The manufacturer of the part */
        public readonly ?string $manufacturer = null,
        /** @var string|null The manufacturer part number */
        public readonly ?string $mpn = null,
        /** @var string|null An URL to a preview image */
        public readonly ?string $preview_image_url = null,
        /** @var ManufacturingStatus|null The manufacturing status of the part */
        public readonly ?ManufacturingStatus $manufacturing_status = null,
        /** @var string|null A link to the part on the providers page */
        public readonly ?string $provider_url = null,
        /** @var string|null A footprint representation of the providers page */
        public readonly ?string $footprint = null,
    ) {

    }
}