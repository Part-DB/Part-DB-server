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
use App\Services\InfoProviderSystem\DTOs\SearchResultDTO;

interface InfoProviderInterface
{

    /**
     * Get information about this provider
     *
     * @return array An associative array with the following keys (? means optional):
     * - name: The (user friendly) name of the provider (e.g. "Digikey"), will be translated
     * - description?: A short description of the provider (e.g. "Digikey is a ..."), will be translated
     * - logo?: The logo of the provider (e.g. "digikey.png")
     * - url?: The url of the provider (e.g. "https://www.digikey.com")
     * - disabled_help?: A help text which is shown when the provider is disabled, explaining how to enable it
     *
     * @phpstan-return array{ name: string, description?: string, logo?: string, url?: string, disabled_help?: string }
     */
    public function getProviderInfo(): array;

    /**
     * Returns a unique key for this provider, which will be saved into the database
     * and used to identify the provider
     * @return string A unique key for this provider (e.g. "digikey")
     */
    public function getProviderKey(): string;

    /**
     * Checks if this provider is enabled or not (meaning that it can be used for searching)
     * @return bool True if the provider is enabled, false otherwise
     */
    public function isActive(): bool;

    /**
     * Searches for a keyword and returns a list of search results
     * @param  string  $keyword The keyword to search for
     * @return SearchResultDTO[] A list of search results
     */
    public function searchByKeyword(string $keyword): array;

    /**
     * Returns detailed information about the part with the given id
     * @param  string  $id
     * @return PartDetailDTO
     */
    public function getDetails(string $id): PartDetailDTO;

    /**
     * A list of capabilities this provider supports (which kind of data it can provide).
     * Not every part have to contain all of these data, but the provider should be able to provide them in general.
     * Currently, this list is purely informational and not used in functional checks.
     * @return ProviderCapabilities[]
     */
    public function getCapabilities(): array;
}