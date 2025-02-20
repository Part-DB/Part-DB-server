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


namespace App\Services\InfoProviderSystem;

use App\Entity\Parts\Part;
use App\Services\InfoProviderSystem\DTOs\PartDetailDTO;
use App\Services\InfoProviderSystem\DTOs\SearchResultDTO;
use App\Services\InfoProviderSystem\Providers\InfoProviderInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

final class PartInfoRetriever
{

    private const CACHE_DETAIL_EXPIRATION = 60 * 60 * 24 * 4; // 4 days
    private const CACHE_RESULT_EXPIRATION = 60 * 60 * 24 * 4; // 7 days

    public function __construct(private readonly ProviderRegistry $provider_registry,
        private readonly DTOtoEntityConverter $dto_to_entity_converter, private readonly CacheInterface $partInfoCache)
    {
    }

    /**
     * Search for a keyword in the given providers. The results can be cached
     * @param  string[]|InfoProviderInterface[]  $providers A list of providers to search in, either as provider keys or as provider instances
     * @param  string  $keyword The keyword to search for
     * @return SearchResultDTO[] The search results
     */
    public function searchByKeyword(string $keyword, array $providers): array
    {
        $results = [];

        foreach ($providers as $provider) {
            if (is_string($provider)) {
                $provider = $this->provider_registry->getProviderByKey($provider);
            }

            if (!$provider instanceof InfoProviderInterface) {
                throw new \InvalidArgumentException("The provider must be either a provider key or a provider instance!");
            }

            /** @noinspection SlowArrayOperationsInLoopInspection */
            $results = array_merge($results, $this->searchInProvider($provider, $keyword));
        }

        return $results;
    }

    /**
     * Search for a keyword in the given provider. The result is cached for 7 days.
     * @return SearchResultDTO[]
     */
    protected function searchInProvider(InfoProviderInterface $provider, string $keyword): array
    {
        //Generate key and escape reserved characters from the provider id
        $escaped_keyword = urlencode($keyword);
        return $this->partInfoCache->get("search_{$provider->getProviderKey()}_{$escaped_keyword}", function (ItemInterface $item) use ($provider, $keyword) {
            //Set the expiration time
            $item->expiresAfter(self::CACHE_RESULT_EXPIRATION);

            return $provider->searchByKeyword($keyword);
        });
    }

    /**
     * Retrieves the details for a part from the given provider with the given (provider) part id.
     * The result is cached for 4 days.
     * @param  string  $provider_key
     * @param  string  $part_id
     * @return PartDetailDTO
     */
    public function getDetails(string $provider_key, string $part_id): PartDetailDTO
    {
        $provider = $this->provider_registry->getProviderByKey($provider_key);

        //Generate key and escape reserved characters from the provider id
        $escaped_part_id = urlencode($part_id);
        return $this->partInfoCache->get("details_{$provider_key}_{$escaped_part_id}", function (ItemInterface $item) use ($provider, $part_id) {
            //Set the expiration time
            $item->expiresAfter(self::CACHE_DETAIL_EXPIRATION);

            return $provider->getDetails($part_id);
        });
    }

    /**
     * Retrieves the details for a part, based on the given search result.
     * @param  SearchResultDTO  $search_result
     * @return PartDetailDTO
     */
    public function getDetailsForSearchResult(SearchResultDTO $search_result): PartDetailDTO
    {
        return $this->getDetails($search_result->provider_key, $search_result->provider_id);
    }

    /**
     * Converts the given DTO to a part entity
     * @return Part
     */
    public function dtoToPart(PartDetailDTO $search_result): Part
    {
        return $this->createPart($search_result->provider_key, $search_result->provider_id);
    }

    /**
     * Use the given details to create a part entity
     */
    public function createPart(string $provider_key, string $part_id): Part
    {
        $details = $this->getDetails($provider_key, $part_id);

        return $this->dto_to_entity_converter->convertPart($details);
    }
}