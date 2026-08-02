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


namespace App\Services\InfoProviderSystem\Providers;

use App\Services\InfoProviderSystem\DTOs\PartDetailDTO;
use App\Services\InfoProviderSystem\DTOs\ProviderInfoDTO;
use App\Services\InfoProviderSystem\DTOs\SearchResultDTO;

interface InfoProviderInterface
{
    public const OPTION_NO_CACHE = 'no_cache'; // if set to true, the provider should not use any cache and retrieve fresh data from the source
    public const OPTION_SKIP_DELEGATION = 'skip_delegation'; // if set to true, the provider should not delegate the request to other providers, even if it supports delegation.
    public const OPTION_SUBMITTED_PAGE_TOKEN = 'submitted_page_token'; // if set to a non-empty string, the provider should use the browser-submitted page with the given token (and retrieve it from BrowserHtmlSessionStorage)

    /**
     * Get information about this provider
     */
    public function getProviderInfo(): ProviderInfoDTO;

    /**
     * Checks if this provider is enabled or not (meaning that it can be used for searching)
     * @return bool True if the provider is enabled, false otherwise
     */
    public function isActive(): bool;

    /**
     * Searches for a keyword and returns a list of search results
     * @param  string  $keyword The keyword to search for
     * @param  array   $options  An associative array of options for the search, which can be used to pass additional parameters to the provider (e.g. filters, pagination, etc.). The content of this array is provider specific and not defined by the interface
     * @return SearchResultDTO[] A list of search results
     */
    public function searchByKeyword(string $keyword, array $options = []): array;

    /**
     * Returns detailed information about the part with the given id
     * @param  string  $id
     * @param  array   $options  An associative array of options for the search, which can be used to pass additional parameters to the provider (e.g. filters, pagination, etc.). The content of this array is provider specific and not defined by the interface
     * @return PartDetailDTO
     */
    public function getDetails(string $id, array $options = []): PartDetailDTO;
}
