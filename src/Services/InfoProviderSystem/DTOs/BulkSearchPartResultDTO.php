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

use App\Entity\Parts\Part;

/**
 * Represents a single search result from bulk search with additional context information, like how the part was found.
 */
readonly class BulkSearchPartResultDTO
{
    public function __construct(
        /** The base search result DTO containing provider data */
        public SearchResultDTO $searchResult,
        /** The field that was used to find this result */
        public ?string $sourceField = null,
        /** The actual keyword that was searched for */
        public ?string $sourceKeyword = null,
        /** Local part that matches this search result, if any */
        public ?Part $localPart = null,
        /** Priority for this search result */
        public int $priority = 1
    ) {}
}
