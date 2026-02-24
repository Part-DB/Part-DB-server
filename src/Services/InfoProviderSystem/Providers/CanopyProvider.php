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

use App\Services\InfoProviderSystem\DTOs\FileDTO;
use App\Services\InfoProviderSystem\DTOs\PartDetailDTO;
use App\Services\InfoProviderSystem\DTOs\PriceDTO;
use App\Services\InfoProviderSystem\DTOs\PurchaseInfoDTO;
use App\Services\InfoProviderSystem\DTOs\SearchResultDTO;
use App\Settings\InfoProviderSystem\BuerklinSettings;
use App\Settings\InfoProviderSystem\CanopySettings;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\DependencyInjection\Attribute\When;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Use canopy API to retrieve infos from amazon
 */
class CanopyProvider implements InfoProviderInterface
{

    public const BASE_URL = "https://rest.canopyapi.co/api";
    public const SEARCH_API_URL = self::BASE_URL . "/amazon/search";
    public const DETAIL_API_URL = self::BASE_URL . "/amazon/product";

    public const DISTRIBUTOR_NAME = 'Amazon';

    public function __construct(private readonly CanopySettings $settings,
        private readonly HttpClientInterface $httpClient, private readonly CacheItemPoolInterface $partInfoCache)
    {

    }

    public function getProviderInfo(): array
    {
        return [
            'name' => 'Amazon (Canopy)',
            'description' => 'Retrieves part infos from Amazon using the Canopy API',
            'url' => 'https://canopyapi.co',
            'disabled_help' => 'Set Canopy API key in the provider configuration to enable this provider',
            'settings_class' => CanopySettings::class
        ];
    }

    public function getProviderKey(): string
    {
        return 'canopy';
    }

    public function isActive(): bool
    {
        return $this->settings->apiKey !== null;
    }

    private function productPageFromASIN(string $asin): string
    {
        return "https://www.{$this->settings->getRealDomain()}/dp/{$asin}";
    }

    /**
     * Saves the given part to the cache.
     * Everytime this function is called, the cache is overwritten.
     * @param  PartDetailDTO  $part
     * @return void
     */
    private function saveToCache(PartDetailDTO $part): void
    {
        $key = 'canopy_part_'.$part->provider_id;

        $item = $this->partInfoCache->getItem($key);
        $item->set($part);
        $item->expiresAfter(3600 * 24); //Cache for 1 day
        $this->partInfoCache->save($item);
    }

    /**
     * Retrieves a from the cache, or null if it was not cached yet.
     * @param  string  $id
     * @return PartDetailDTO|null
     */
    private function getFromCache(string $id): ?PartDetailDTO
    {
        $key = 'canopy_part_'.$id;

        $item = $this->partInfoCache->getItem($key);
        if ($item->isHit()) {
            return $item->get();
        }

        return null;
    }

    public function searchByKeyword(string $keyword): array
    {
        $response = $this->httpClient->request('GET', self::SEARCH_API_URL, [
            'query' => [
                'domain' => $this->settings->domain,
                'searchTerm' => $keyword,
            ],
            'headers' => [
                'API-KEY' => $this->settings->apiKey,
            ]
        ]);

        $data = $response->toArray();
        $results = $data['data']['amazonProductSearchResults']['productResults']['results'] ?? [];

        $out = [];
        foreach ($results as $result) {


            $dto = new PartDetailDTO(
                provider_key: $this->getProviderKey(),
                provider_id: $result['asin'],
                name: $result["title"],
                description: "",
                preview_image_url: $result["mainImageUrl"] ?? null,
                provider_url: $this->productPageFromASIN($result['asin']),
                vendor_infos: [$this->priceToPurchaseInfo($result['price'], $result['asin'])]
            );

            $out[] = $dto;
            $this->saveToCache($dto);
        }

        return $out;
    }

    private function categoriesToCategory(array $categories): ?string
    {
        if (count($categories) === 0) {
            return null;
        }

        return implode(" -> ", array_map(static fn($cat) => $cat['name'], $categories));
    }

    private function feauturesBulletsToNotes(array $featureBullets): string
    {
        $notes = "<ul>";
        foreach ($featureBullets as $bullet) {
            $notes .= "<li>" . $bullet . "</li>";
        }
        $notes .= "</ul>";
        return $notes;
    }

    private function priceToPurchaseInfo(?array $price, string $asin): PurchaseInfoDTO
    {
        $priceDtos = [];
        if ($price !== null) {
            $priceDtos[] = new PriceDTO(minimum_discount_amount: 1, price: (string) $price['value'], currency_iso_code: $price['currency'], includes_tax: true);
        }


        return new PurchaseInfoDTO(self::DISTRIBUTOR_NAME, order_number: $asin, prices: $priceDtos, product_url: $this->productPageFromASIN($asin));
    }

    public function getDetails(string $id): PartDetailDTO
    {
        //Check that the id is a valid ASIN (10 characters, letters and numbers)
        if (!preg_match('/^[A-Z0-9]{10}$/', $id)) {
            throw new \InvalidArgumentException("The id must be a valid ASIN (10 characters, letters and numbers)");
        }

        //Use cached details if available and the settings allow it, to avoid unnecessary API requests, since the search results already contain most of the details
        if(!$this->settings->alwaysGetDetails && ($cached = $this->getFromCache($id)) !== null) {
            return $cached;
        }

        $response = $this->httpClient->request('GET', self::DETAIL_API_URL, [
            'query' => [
                'asin' => $id,
                'domain' => $this->settings->domain,
            ],
            'headers' => [
                'API-KEY' => $this->settings->apiKey,
            ],
        ]);

        $product = $response->toArray()['data']['amazonProduct'];


        if ($product === null) {
            throw new \RuntimeException("Product with ASIN $id not found");
        }

        return new PartDetailDTO(
            provider_key: $this->getProviderKey(),
            provider_id: $product['asin'],
            name: $product['title'],
            description: '',
            category: $this->categoriesToCategory($product['categories']),
            manufacturer: $product['brand'] ?? null,
            preview_image_url: $product['mainImageUrl'] ?? $product['imageUrls'][0] ?? null,
            provider_url: $this->productPageFromASIN($product['asin']),
            notes: $this->feauturesBulletsToNotes($product['featureBullets'] ?? []),
            vendor_infos: [$this->priceToPurchaseInfo($product['price'], $product['asin'])]
        );
    }

    public function getCapabilities(): array
    {
        return [
            ProviderCapabilities::BASIC,
            ProviderCapabilities::PICTURE,
            ProviderCapabilities::PRICE,
        ];
    }
}
