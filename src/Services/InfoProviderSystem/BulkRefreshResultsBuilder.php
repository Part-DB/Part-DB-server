<?php

declare(strict_types=1);

/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2024 Jan Böhmer (https://github.com/jbtronics)
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

namespace App\Services\InfoProviderSystem;

use App\Entity\Parts\Part;
use App\Services\InfoProviderSystem\DTOs\BulkSearchPartResultDTO;
use App\Services\InfoProviderSystem\DTOs\BulkSearchPartResultsDTO;
use App\Services\InfoProviderSystem\DTOs\BulkSearchResponseDTO;
use App\Services\InfoProviderSystem\DTOs\SearchResultDTO;

/**
 * Builds the results of a bulk refresh, in the same shape a bulk search produces them, so that a refresh can reuse
 * the whole review step of the bulk import (see BulkInfoProviderImportController).
 *
 * Unlike a search, nothing has to be looked for here: a part which was created by an info provider already knows
 * which provider and which provider ID it came from, so its single "result" is exactly that provider entry.
 * No provider is contacted while building the results - the data itself is only fetched when the user actually
 * applies a part in the review step.
 *
 * @see \App\Tests\Services\InfoProviderSystem\BulkRefreshResultsBuilderTest
 */
final readonly class BulkRefreshResultsBuilder
{
    public function __construct(private ProviderRegistry $providerRegistry)
    {
    }

    /**
     * @param Part[] $parts The parts which should be refreshed
     * @throws \InvalidArgumentException If no parts were given
     */
    public function build(array $parts): BulkSearchResponseDTO
    {
        if ($parts === []) {
            throw new \InvalidArgumentException('No valid parts found for bulk refresh');
        }

        $part_results = [];

        foreach ($parts as $part) {
            $reference = $part->getProviderReference();
            $provider_key = $reference->getProviderKey();
            $provider_id = $reference->getProviderId();

            //Parts which can not be refreshed are kept in the job with an error, so they stay visible to the user
            //instead of silently disappearing from their selection
            if (!$reference->isProviderCreated() || $provider_key === null || $provider_id === null) {
                $part_results[] = new BulkSearchPartResultsDTO(part: $part, errors: [
                    'info_providers.bulk_refresh.error.no_provider_reference',
                ]);
                continue;
            }

            if (!$this->isProviderUsable($provider_key)) {
                $part_results[] = new BulkSearchPartResultsDTO(part: $part, errors: [
                    'info_providers.bulk_refresh.error.provider_unavailable',
                ]);
                continue;
            }

            $part_results[] = new BulkSearchPartResultsDTO(part: $part, searchResults: [
                new BulkSearchPartResultDTO(
                    searchResult: new SearchResultDTO(
                        provider_key: $provider_key,
                        provider_id: $provider_id,
                        name: $part->getName(),
                        description: $part->getDescription(),
                        manufacturer: $part->getManufacturer()?->getName(),
                        mpn: $part->getManufacturerProductNumber(),
                        provider_url: $reference->getProviderUrl(),
                    ),
                    //The part is not searched for, it is looked up by the provider ID it was created with
                    sourceField: 'provider_reference',
                    sourceKeyword: $provider_id,
                ),
            ]);
        }

        return new BulkSearchResponseDTO($part_results);
    }

    /**
     * Checks whether the provider with the given key exists in this installation and is currently usable.
     */
    private function isProviderUsable(string $provider_key): bool
    {
        try {
            $provider = $this->providerRegistry->getProviderByKey($provider_key);
        } catch (\InvalidArgumentException) {
            //The provider the part was created with is not part of this installation (anymore)
            return false;
        }

        return $provider->isActive();
    }
}
