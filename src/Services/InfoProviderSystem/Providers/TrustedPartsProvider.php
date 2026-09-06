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


namespace App\Services\InfoProviderSystem\Providers;

use App\Entity\UserSystem\User;
use App\Services\InfoProviderSystem\DTOs\FileDTO;
use App\Services\InfoProviderSystem\DTOs\ParameterDTO;
use App\Services\InfoProviderSystem\DTOs\PartDetailDTO;
use App\Services\InfoProviderSystem\DTOs\PriceDTO;
use App\Services\InfoProviderSystem\DTOs\ProviderInfoDTO;
use App\Services\InfoProviderSystem\DTOs\PurchaseInfoDTO;
use App\Settings\InfoProviderSystem\TrustedPartsSettings;
use Psr\Cache\CacheItemPoolInterface;
use Shivas\VersioningBundle\Service\VersionManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * This provider uses the TrustedParts.com (ECIA) Inventory API to search for parts and get the offers of the
 * authorized distributors for them.
 *
 * The API does not know any part IDs, parts are always identified by their manufacturer part number (and the
 * manufacturer name). Therefore, we build our own provider IDs in the form "manufacturer|mpn".
 * As a single search already returns all information we need (including the offers of all distributors), the results
 * are cached, so that the detail view does not need to query the API again.
 */
class TrustedPartsProvider implements BatchInfoProviderInterface
{
    public const PROVIDER_KEY = 'trustedparts';

    private const ENDPOINT_URL = 'https://api.trustedparts.com/v2/search';

    /** @var string The separator used to build the provider ID out of the manufacturer name and the MPN */
    private const ID_SEPARATOR = '|';

    /** @var int The API rejects requests which contain more than 50 part numbers */
    private const MAX_QUERIES_PER_REQUEST = 50;

    /**
     * @var int How long the retrieved parts are cached. The API terms of use allow caching for a maximum of one week.
     */
    private const CACHE_TTL = 60 * 60 * 24;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly TrustedPartsSettings $settings,
        private readonly CacheItemPoolInterface $partInfoCache,
        private readonly Security $security,
        private readonly VersionManagerInterface $versionManager,
    ) {
    }

    public function getProviderInfo(): ProviderInfoDTO
    {
        return new ProviderInfoDTO(
            key: self::PROVIDER_KEY,
            name: 'TrustedParts',
            description: 'This provider uses the TrustedParts.com (ECIA) API to search for parts and retrieve the offers of authorized distributors.',
            url: 'https://www.trustedparts.com/',
            disabledHelp: 'Configure the company ID and the API key in the provider settings to enable.',
            settingsClass: TrustedPartsSettings::class,
            capabilities: [
                ProviderCapabilities::BASIC,
                ProviderCapabilities::DATASHEET,
                ProviderCapabilities::PRICE,
                ProviderCapabilities::FOOTPRINT,
                ProviderCapabilities::PARAMETERS,
            ],
        );
    }

    public function isActive(): bool
    {
        return $this->settings->companyId !== null && $this->settings->companyId !== ''
            && $this->settings->apiKey !== null && $this->settings->apiKey !== '';
    }

    public function searchByKeyword(string $keyword, array $options = []): array
    {
        $keyword = trim($keyword);
        //The API requires a search term of at least two characters
        if (mb_strlen($keyword) < 2) {
            return [];
        }

        //Partial matches are only allowed for requests containing a single part number
        $results = $this->apiSearch([['SearchToken' => $keyword]], false);

        $tmp = [];
        foreach ($results as $part) {
            $dto = $this->partResultToDTO($part);
            //Cache the part, so the details can be shown later without querying the API again
            $this->saveToCache($dto);
            $tmp[] = $dto;

            if (count($tmp) >= $this->settings->searchLimit) {
                break;
            }
        }

        return $tmp;
    }

    public function searchByKeywordsBatch(array $keywords, array $options = []): array
    {
        $results = [];
        $queries = [];

        foreach ($keywords as $keyword) {
            $results[$keyword] = [];

            //The API requires a search term of at least two characters
            if (mb_strlen(trim($keyword)) >= 2) {
                $queries[$keyword] = ['SearchToken' => trim($keyword)];
            }
        }

        foreach (array_chunk($queries, self::MAX_QUERIES_PER_REQUEST, true) as $chunk) {
            //If we search for multiple part numbers at once, only exact matches are possible
            $parts = $this->apiSearch(array_values($chunk), true);

            //The response does not tell us which query a result belongs to, so we have to match them by part number.
            //Like the API itself, we ignore punctuation and spacing while doing so.
            $keywords_by_part_number = [];
            foreach (array_keys($chunk) as $keyword) {
                $keywords_by_part_number[$this->normalizePartNumber((string) $keyword)][] = $keyword;
            }

            foreach ($parts as $part) {
                $dto = $this->partResultToDTO($part);
                $this->saveToCache($dto);

                foreach ($keywords_by_part_number[$this->normalizePartNumber($dto->mpn ?? '')] ?? [] as $keyword) {
                    $results[$keyword][] = $dto;
                }
            }
        }

        return $results;
    }

    public function getDetails(string $id, array $options = []): PartDetailDTO
    {
        $no_cache = $options[self::OPTION_NO_CACHE] ?? false;

        //Normally the part was already retrieved during the search, so we can use the cached version
        $cached = $no_cache ? null : $this->getFromCache($id);
        if ($cached !== null) {
            return $cached;
        }

        [$manufacturer, $mpn] = $this->splitId($id);
        if ($mpn === '') {
            throw new \RuntimeException('Invalid TrustedParts part ID: '.$id);
        }

        $query = ['SearchToken' => $mpn];
        if ($manufacturer !== '') {
            $query['Manufacturers'] = [$manufacturer];
        }

        $fallback = null;
        foreach ($this->apiSearch([$query], true) as $part) {
            $dto = $this->partResultToDTO($part);
            $this->saveToCache($dto);

            if ($dto->provider_id === $id) {
                return $dto;
            }

            //An exact match search also returns parts whose part number only differs in punctuation, so keep the
            //first result with a matching part number as fallback
            if ($fallback === null && $this->normalizePartNumber($dto->mpn ?? '') === $this->normalizePartNumber($mpn)) {
                $fallback = $dto;
            }
        }

        return $fallback ?? throw new \RuntimeException('No part found with ID '.$id);
    }

    /**
     * Performs a search request against the API and returns the part results of the response.
     * @param  array<int, array<string, mixed>>  $queries The queries (each containing at least a SearchToken)
     * @param  bool  $exactMatch Whether only exact matches should be returned
     * @return array<int, array<string, mixed>>
     */
    private function apiSearch(array $queries, bool $exactMatch): array
    {
        $response = $this->httpClient->request('POST', self::ENDPOINT_URL, [
            'headers' => [
                'Accept' => 'application/json',
            ],
            'json' => [
                'CompanyId' => $this->settings->companyId,
                'ApiKey' => $this->settings->apiKey,
                'Queries' => $queries,
                'CountryCode' => $this->settings->country,
                'CurrencyCode' => $this->settings->currency,
                'LanguageCode' => $this->settings->language,
                'InStockOnly' => $this->settings->inStockOnly,
                'ExactMatch' => $exactMatch,
                'UseCachedData' => $this->settings->useCachedData,
                //Part-DB is not a website showing the results to an anonymous visitor, so this is never a crawler
                'IsCrawler' => false,
                'UserAgent' => $this->buildUserAgent(),
            ],
        ]);

        if ($response->getStatusCode() !== 200) {
            throw new \RuntimeException(sprintf(
                'TrustedParts API returned HTTP %d: %s',
                $response->getStatusCode(),
                $response->getContent(false)
            ));
        }

        $data = $response->toArray();

        if (isset($data['ErrorMessage']) && $data['ErrorMessage'] !== '') {
            throw new \RuntimeException('TrustedParts API returned an error: '.$data['ErrorMessage']);
        }

        return $data['PartResults'] ?? [];
    }

    /**
     * Builds the user agent which is sent with every request.
     * The API terms of use require that applications which are not a website identify themselves with their name and
     * a unique user identifier (which must not contain any personally identifiable information).
     */
    private function buildUserAgent(): string
    {
        $user = $this->security->getUser();
        $identifier = ($user instanceof User && $user->getID() !== null) ? 'user-'.$user->getID() : 'system';

        return sprintf('Part-DB/%s (%s)', $this->versionManager->getVersion()->toString(), $identifier);
    }

    /**
     * Converts a part result of the API response into a PartDetailDTO.
     * @param  array<string, mixed>  $part
     */
    private function partResultToDTO(array $part): PartDetailDTO
    {
        $mpn = trim((string) ($part['PartNumber'] ?? ''));
        $manufacturer = $part['Manufacturer'] ?? null;

        //Parse the specifications
        $parameters = [];
        $specifications = [];
        foreach ($part['Specifications'] ?? [] as $specification) {
            $name = trim((string) ($specification['Key'] ?? ''));
            $value = trim((string) ($specification['Value'] ?? ''));

            if ($name === '' || $value === '') {
                continue;
            }

            $specifications[$name] = $value;
            $parameters[] = ParameterDTO::parseValueIncludingUnit(name: $name, value: $value);
        }

        //Some of the specifications are useful for other fields of the part
        $footprint = $specifications['Package / Case'] ?? null;
        $category = null;
        foreach (['Subcategory', 'Product Type', 'Product'] as $category_key) {
            if (isset($specifications[$category_key])) {
                $category = $specifications[$category_key];
                break;
            }
        }

        //Parse the offers of the distributors. The part itself has no description or datasheet, so we take these
        //from the distributor results too.
        $description = '';
        $datasheets = [];
        $orderinfos = [];

        foreach ($part['Distributors'] ?? [] as $distributor) {
            $distributor_name = trim((string) ($distributor['Name'] ?? ''));

            foreach ($distributor['DistributorResults'] ?? [] as $offer) {
                if ($description === '') {
                    $description = trim((string) ($offer['Description'] ?? ''));
                }

                $product_url = null;
                foreach ($offer['Links'] ?? [] as $link) {
                    $url = trim((string) ($link['Url'] ?? ''));
                    if ($url === '') {
                        continue;
                    }

                    if (strcasecmp((string) ($link['Type'] ?? ''), 'Datasheet') === 0) {
                        //Every distributor links its own copy of the datasheet, so we name them after the
                        //distributor. The URL is used as key to filter out duplicates.
                        $datasheets[$url] = new FileDTO($url,
                            $distributor_name === '' ? 'Datasheet' : 'Datasheet ('.$distributor_name.')');
                    } elseif ($product_url === null) {
                        //The link to the offer is either of type "Buy" (orderable) or "View"
                        $product_url = $url;
                    }
                }

                if ($distributor_name === '') {
                    continue;
                }

                $currency = $offer['Pricing']['CurrencyCode'] ?? null;
                $prices = [];
                foreach ($offer['Pricing']['Prices'] ?? [] as $price) {
                    if (!isset($price['Quantity'], $price['Amount'])) {
                        continue;
                    }

                    $prices[] = new PriceDTO(
                        minimum_discount_amount: (float) $price['Quantity'],
                        price: $this->formatPrice((float) $price['Amount']),
                        currency_iso_code: $currency,
                        includes_tax: false,
                    );
                }

                //Not every distributor provides a part number for its offers
                $order_number = trim((string) ($offer['DistributorPartNumber'] ?? ''));
                if ($order_number === '' || strcasecmp($order_number, 'N/A') === 0) {
                    $order_number = $mpn;
                }

                $orderinfos[] = new PurchaseInfoDTO(
                    distributor_name: $distributor_name,
                    order_number: $order_number,
                    prices: $prices,
                    product_url: $product_url,
                    prices_include_vat: false,
                );
            }
        }

        return new PartDetailDTO(
            provider_key: self::PROVIDER_KEY,
            provider_id: $this->buildId($manufacturer, $mpn),
            name: $mpn,
            description: $description,
            category: $category,
            manufacturer: $manufacturer,
            mpn: $mpn,
            provider_url: $part['ProductUrl'] ?? null,
            footprint: $footprint,
            datasheets: array_values($datasheets),
            parameters: $parameters,
            vendor_infos: $orderinfos,
        );
    }

    /**
     * Builds the provider ID for the part with the given manufacturer and part number.
     */
    private function buildId(?string $manufacturer, string $mpn): string
    {
        return ($manufacturer ?? '').self::ID_SEPARATOR.$mpn;
    }

    /**
     * Splits a provider ID into the manufacturer name and the part number.
     * @return array{string, string}
     */
    private function splitId(string $id): array
    {
        $parts = explode(self::ID_SEPARATOR, $id, 2);

        //IDs without a separator are interpreted as a plain part number
        if (count($parts) < 2) {
            return ['', trim($id)];
        }

        return [trim($parts[0]), trim($parts[1])];
    }

    /**
     * Removes everything but letters and digits from a part number, so that part numbers can be compared the same
     * way the API determines an exact match (e.g. "BAT-54C" is an exact match for "BAT54C").
     */
    private function normalizePartNumber(string $part_number): string
    {
        return strtoupper(preg_replace('/[^a-zA-Z0-9]/', '', $part_number) ?? '');
    }

    /**
     * Formats a price for the PriceDTO. We can not simply cast it to a string, as that could result in a scientific
     * notation, which can not be parsed as a decimal number.
     */
    private function formatPrice(float $price): string
    {
        $tmp = rtrim(rtrim(sprintf('%.10F', $price), '0'), '.');

        return $tmp === '' ? '0' : $tmp;
    }

    private function saveToCache(PartDetailDTO $part): void
    {
        $item = $this->partInfoCache->getItem($this->cacheKey($part->provider_id));
        $item->set($part);
        $item->expiresAfter(self::CACHE_TTL);
        $this->partInfoCache->save($item);
    }

    private function getFromCache(string $id): ?PartDetailDTO
    {
        $item = $this->partInfoCache->getItem($this->cacheKey($id));

        return $item->isHit() ? $item->get() : null;
    }

    private function cacheKey(string $id): string
    {
        //The IDs contain characters which are not allowed in cache keys, so we hash them
        return 'trustedparts_part_'.hash('xxh3', $id);
    }
}
