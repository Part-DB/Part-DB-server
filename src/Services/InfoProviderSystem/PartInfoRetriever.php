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
use App\Exceptions\InfoProviderNotActiveException;
use App\Exceptions\OAuthReconnectRequiredException;
use App\Services\InfoProviderSystem\DTOs\PartDetailDTO;
use App\Services\InfoProviderSystem\DTOs\SearchResultDTO;
use App\Services\InfoProviderSystem\Providers\InfoProviderInterface;
use Psr\Http\Client\ClientExceptionInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpClient\Exception\ClientException;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

final class PartInfoRetriever
{

    private const CACHE_DETAIL_EXPIRATION = 60 * 60 * 24 * 4; // 4 days
    private const CACHE_RESULT_EXPIRATION = 60 * 60 * 24 * 4; // 7 days

    public function __construct(private readonly ProviderRegistry $provider_registry,
        private readonly DTOtoEntityConverter $dto_to_entity_converter, private readonly CacheInterface $partInfoCache,
        #[Autowire(param: "kernel.debug")]
        private readonly bool $debugMode = false)
    {
    }

    /**
     * Search for a keyword in the given providers. The results can be cached
     * @param  string[]|InfoProviderInterface[]  $providers A list of providers to search in, either as provider keys or as provider instances
     * @param  string  $keyword The keyword to search for
     * @param  array<string, mixed>  $options An associative array of options which can be used to modify the search behavior. The supported options depend on the provider and should be documented in the provider's documentation.
     * @return SearchResultDTO[] The search results
     * @throws InfoProviderNotActiveException if any of the given providers is not active
     * @throws ClientException if any of the providers throws an exception during the search
     * @throws \InvalidArgumentException if any of the given providers is not a valid provider key or instance
     * @throws TransportException if any of the providers throws an exception during the search
     * @throws OAuthReconnectRequiredException if any of the providers throws an exception during the search that indicates that the OAuth token needs to be refreshed
     */
    public function searchByKeyword(string $keyword, array $providers, array $options = []): array
    {
        $results = [];

        foreach ($providers as $provider) {
            if (is_string($provider)) {
                $provider = $this->provider_registry->getProviderByKey($provider);
            }

            //Ensure that the provider is active
            if (!$provider->isActive()) {
                throw InfoProviderNotActiveException::fromProvider($provider);
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
    protected function searchInProvider(InfoProviderInterface $provider, string $keyword, array $options = []): array
    {
        //Generate key and escape reserved characters from the provider id
        $escaped_keyword = hash('xxh3', $keyword);

        $no_cache = $options[InfoProviderInterface::OPTION_NO_CACHE] ?? false;

        //Exclude the no_cache option from the options hash, since it should not affect the cache key, as it only determines whether to bypass the cache or not, but does not change the actual search results
        $options_without_cache = $options;
        unset($options_without_cache[InfoProviderInterface::OPTION_NO_CACHE]);
        //Generate a hash for the options, to ensure that different options result in different cache entries
        $options_hash = hash('xxh3', json_encode($options_without_cache, JSON_THROW_ON_ERROR));

        $cache_key = "search_{$provider->getProviderKey()}_{$escaped_keyword}_{$options_hash}";

        //If no_cache is set, bypass the cache and get fresh results from the provider
        if ($no_cache) {
            $this->partInfoCache->delete($cache_key);
        }

        return $this->partInfoCache->get($cache_key, function (ItemInterface $item) use ($provider, $keyword, $options) {
            //Set the expiration time
            $item->expiresAfter(!$this->debugMode ? self::CACHE_RESULT_EXPIRATION : 10);

            return $provider->searchByKeyword($keyword, $options);
        });
    }

    /**
     * Retrieves the details for a part from the given provider with the given (provider) part id.
     * The result is cached for 4 days.
     * @param  string  $provider_key
     * @param  string  $part_id
     * @param array<string, mixed>  $options An associative array of options which can be used to modify the search behavior. The supported options depend on the provider and should be documented in the provider's documentation.
     * @return PartDetailDTO
     * @throws InfoProviderNotActiveException if the the given providers is not active
     */
    public function getDetails(string $provider_key, string $part_id, array $options = []): PartDetailDTO
    {
        $provider = $this->provider_registry->getProviderByKey($provider_key);

        //Ensure that the provider is active
        if (!$provider->isActive()) {
            throw InfoProviderNotActiveException::fromProvider($provider);
        }

        //Exclude the no_cache option from the options hash, since it should not affect the cache key, as it only determines whether to bypass the cache or not, but does not change the actual search results
        $options_without_cache = $options;
        unset($options_without_cache[InfoProviderInterface::OPTION_NO_CACHE]);
        //Generate a hash for the options, to ensure that different options result in different cache entries
        $options_hash = hash('xxh3', json_encode($options_without_cache, JSON_THROW_ON_ERROR));

        //Generate key and escape reserved characters from the provider id
        $escaped_part_id = hash('xxh3', $part_id);
        $cache_key = "details_{$provider_key}_{$escaped_part_id}_{$options_hash}";

        //Delete the cache entry if no_cache is set, to ensure that the next get call will fetch fresh data from the provider, instead of returning stale data from the cache.
        if ($options[InfoProviderInterface::OPTION_NO_CACHE] ?? false) {
            $this->partInfoCache->delete($cache_key);
        }

        return $this->partInfoCache->get($cache_key, function (ItemInterface $item) use ($provider, $part_id, $options) {
            //Set the expiration time
            $item->expiresAfter(!$this->debugMode ? self::CACHE_DETAIL_EXPIRATION : 10);

            return $provider->getDetails($part_id, $options);
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
