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

use Symfony\Component\Validator\Constraints as Assert;

readonly class InfoProviderCreatePartInput
{
    public function __construct(
        /** @var string The key of the info provider (e.g. "digikey", "trustedparts"), as returned by search_info_providers */
        #[Assert\NotBlank]
        public string $provider_key,

        /** @var string The provider-specific ID of the part, as returned by search_info_providers */
        #[Assert\NotBlank]
        public string $provider_id,

        /**
         * @var int|null The database ID of the category to file the new part under. A part must have a category,
         * and info providers only rarely report one that exists in this Part-DB - so unless the provider happens
         * to supply a matching category name, this is required for the part to be accepted.
         */
        #[Assert\Positive]
        public ?int $category_id = null,

        /**
         * @var bool Whether to bypass the info provider cache. Defaults to true, so a part is created from
         * current data instead of a possibly stale cached response.
         */
        public bool $no_cache = true,

        /**
         * @var bool If true, nothing is written to the database - the response tells you which part would have
         * been created. Use this to review what an automated run is about to do.
         */
        public bool $dry_run = false,
    ) {
    }
}
