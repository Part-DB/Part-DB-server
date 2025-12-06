<?php
/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2025 Jan Böhmer (https://github.com/jbtronics)
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


namespace App\Services\InfoProviderSystem\Providers;

use App\Services\InfoProviderSystem\DTOs\SearchResultDTO;

/**
 * This interface marks a provider as a info provider which can provide information directly in batch operations
 */
interface BatchInfoProviderInterface extends InfoProviderInterface
{
    /**
     * Search for multiple keywords in a single batch operation and return the results, ordered by the keywords.
     * This allows for a more efficient search compared to running multiple single searches.
     * @param  string[]  $keywords
     * @return array<string, SearchResultDTO[]> An associative array where the key is the keyword and the value is the search results for that keyword
     */
    public function searchByKeywordsBatch(array $keywords): array;
}
