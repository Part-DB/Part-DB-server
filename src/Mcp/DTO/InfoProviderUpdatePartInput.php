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

readonly class InfoProviderUpdatePartInput
{
    public function __construct(
        /** @var int The database ID of the part to update */
        #[Assert\Positive]
        public int $part_id,

        /**
         * @var string|null The key of the info provider to update from. Defaults to the provider the part was
         * originally created with. Must be given together with provider_id.
         */
        public ?string $provider_key = null,

        /**
         * @var string|null The provider-specific ID of the part to update from. Defaults to the ID the part was
         * originally created with. Must be given together with provider_key.
         */
        public ?string $provider_id = null,

        /**
         * @var bool Whether to bypass the info provider cache. Defaults to true: the point of an update is to get
         * current data, and a cached response would silently defeat that.
         */
        public bool $no_cache = true,

        /**
         * @var bool If true, nothing is written to the database - the response tells you what the update would
         * have changed. Use this to review what an automated run is about to do.
         */
        public bool $dry_run = false,
    ) {
    }
}
