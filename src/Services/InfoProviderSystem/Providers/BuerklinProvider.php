<?php
/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2023 Jan Böhmer (https://github.com/jbtronics)
 *  Copyright (C) 2025 Marc Kreidler (https://github.com/mkne)
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
use App\Services\InfoProviderSystem\DTOs\ParameterDTO;
use App\Services\InfoProviderSystem\DTOs\PartDetailDTO;
use App\Services\InfoProviderSystem\DTOs\PriceDTO;
use App\Services\InfoProviderSystem\DTOs\PurchaseInfoDTO;
use App\Services\OAuth\OAuthTokenManager;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpClient\HttpOptions;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class BuerklinProvider implements InfoProviderInterface
{

    private const ENDPOINT_URL = 'https://buerklin.com/buerklinws/v2/buerklin';

    public const DISTRIBUTOR_NAME = 'Buerklin';
    private const OAUTH_APP_NAME = 'ip_buerklin_oauth';

    public function __construct(private readonly HttpClientInterface $client,
        private readonly OAuthTokenManager $authTokenManager,
        private readonly CacheItemPoolInterface $partInfoCache,
        #[Autowire(env: "string:PROVIDER_BUERKLIN_CLIENT_ID")]
        private readonly string $clientId = "",
        #[Autowire(env: "string:PROVIDER_BUERKLIN_SECRET")]
        private readonly string $secret = "",
        #[Autowire(env: "string:PROVIDER_BUERKLIN_USERNAME")]
        private readonly string $username = "",
        #[Autowire(env: "string:PROVIDER_BUERKLIN_PASSWORD")]
        private readonly string $password = "",
        #[Autowire(env: "PROVIDER_BUERKLIN_LANGUAGE")]
        private readonly string $language = "en",
        #[Autowire(env: "PROVIDER_BUERKLIN_CURRENCY")]
        private readonly string $currency = "EUR"
        )
    {

    }

    /**
     * Gets the latest OAuth token for the Buerklin API, or creates a new one if none is available
     * @return string
     */
    private function getToken(): string
    {
        if (!$this->authTokenManager->hasToken(self::OAUTH_APP_NAME)) {
            $this->authTokenManager->retrieveClientCredentialsToken(self::OAUTH_APP_NAME);
        }
    
        $token = $this->authTokenManager->getAlwaysValidTokenString(self::OAUTH_APP_NAME);
    
        if ($token === null) {
            throw new \RuntimeException('Could not retrieve OAuth token for Buerklin');
        }
    
        return $token;
    }  

    /**
     * Make a http get request to the Buerklin API
     * @return array
     */
    private function makeAPICall(string $endpoint, array $queryParams = []): array
    {
        try {
            $response = $this->client->request('GET', self::ENDPOINT_URL . $endpoint, [
                'auth_bearer' => $this->getToken(),
                'query' => $queryParams,
            ]);

            return $response->toArray();
        } catch (\Exception $e) {
            throw new \RuntimeException("Buerklin API request failed: " . $e->getMessage());
        }
    }


    public function getProviderInfo(): array
    {
        return [
            'name' => 'Buerklin',
            'description' => 'This provider uses the Buerklin API to search for parts.',
            'url' => 'https://www.buerklin.com/',
            'disabled_help' => 'Set the environment variables PROVIDER_BUERKLIN_CLIENT_ID, PROVIDER_BUERKLIN_SECRET, PROVIDER_BUERKLIN_USERNAME and PROVIDER_BUERKLIN_PASSWORD.'
        ];
    }

    public function getProviderKey(): string
    {
        return 'buerklin';
    }

    // This provider is always active
    public function isActive(): bool
    {
        //The client ID has to be set and a token has to be available (user clicked connect)
        return $this->clientId !== '' && $this->secret !== '' && $this->username !== '' && $this->password !== '';
    }

    /**
     * @param  string  $id
     * @return PartDetailDTO
     */
    private function queryDetail(string $id): PartDetailDTO
    {
        $response = $this->makeAPICall('/products', ['sku' => $id]);

        $arr = $response->toArray();
        $product = $arr['result'] ?? null;

        if ($product === null) {
            throw new \RuntimeException('Could not find product code: ' . $id);
        }

        return $this->getPartDetail($product);
    }

    /**
     * Sanitizes a field by removing any HTML tags and other unwanted characters
     * @param  string|null  $field
     * @return string|null
     */
    private function sanitizeField(?string $field): ?string
    {
        if ($field === null) {
            return null;
        }

        return strip_tags($field);
    }

    /**
     * Takes a deserialized json object of the product and returns a PartDetailDTO
     * @param  array  $product
     * @return PartDetailDTO
     */
    private function getPartDetail(array $product): PartDetailDTO
    {
        // Get product images in advance
        $product_images = $this->getProductImages($product['images'] ?? null);
        $product['productImageUrl'] ??= null;

        // If the product does not have a product image but otherwise has attached images, use the first one which should be thumbnail.
        if (count($product_images) > 0) {
            $product['productImageUrl'] ??= self::ENDPOINT_URL . $product_images[0]->url;
        }

        // Find the footprint in classifications->features. en: name='Design'; de: name='Bauform'
        if (isset($product['classifications']['features'])) {
            foreach ($product['classifications']['features'] as $feature) {
                if (isset($feature['name']) && ($feature['name'] === 'Design' || $feature['name'] === 'Bauform')) {
                    $footprint = $feature['featureValues']['value'] ?? null;
                    break;
                }
            }
        }

        return new PartDetailDTO(
            provider_key: $this->getProviderKey(),
            provider_id: $product['code'],
            name: $product['name'],
            description: $this->sanitizeField($product['description']),
            category: $this->sanitizeField($product['classifications'][0]['name'] ?? null),
            manufacturer: $this->sanitizeField($product['manufacturer'] ?? null),
            mpn: $this->sanitizeField($product['manufacturerProductId'] ?? null),
            preview_image_url: $product['productImageUrl'],
            manufacturing_status: null,
            provider_url: $this->getProductShortURL($product['code']),
            footprint: $footprint ?? null,
            datasheets: null, //datasheet urls not found in API responses
            images: $product_images,
            parameters: $this->attributesToParameters($product['classifications']['features'] ?? []),
            vendor_infos: $this->pricesToVendorInfo($product['code'], $this->getProductShortURL($product['code']), $product['productPriceList'] ?? []),
            mass: $product['weight'] ?? null,
        );
    }

    /**
     * Converts the price array to a VendorInfoDTO array to be used in the PartDetailDTO
     * @param  string $sku
     * @param  string $url
     * @param  array  $prices
     * @return array
     */
    private function pricesToVendorInfo(string $sku, string $url, array $prices): array
    {
        $priceDTOs = array_map(fn($price) => new PriceDTO(
            minimum_discount_amount: $price['minQuantity'],
            price: $price['value'],
            currency_iso_code: $price['currencyIso'],
            includes_tax: false
        ), $prices);

        return [
            new PurchaseInfoDTO(
                distributor_name: self::DISTRIBUTOR_NAME,
                order_number: $sku,
                prices: $priceDTOs,
                product_url: $url,
            )
        ];
    }


    /**
     * Returns a valid Buerklin product short URL from product code
     * @param  string  $product_code
     * @return string
     */
    private function getProductShortURL(string $product_code): string
    {
        return 'https://www.buerklin.com/de/p/' . $product_code .'/';
    }

    /**
     * Returns a FileDTO array with a list of product images
     * @param  array|null  $images
     * @return FileDTO[]
     */
    private function getProductImages(?array $images): array
    {
        return array_map(static fn($image) => new FileDTO($image), $images ?? []);
    }

    /**
     * @param  array|null  $attributes
     * @return ParameterDTO[]
     */
    private function attributesToParameters(?array $attributes): array
    {
        $result = [];
    
        foreach ($attributes as $attribute) {
            if (!isset($attribute['name'], $attribute['featureValues']['value'])) {
                continue;
            }
    
            $value = trim((string)$attribute['featureValues']['value']);
            if ($value === '' || $value === '-') {
                continue;
            }
    
            $result[] = ParameterDTO::parseValueIncludingUnit(
                name: $attribute['name'],
                value: $value,
                group: null
            );
        }
    
        return $result;
    }
    

    public function searchByKeyword(string $keyword): array
    {
        $response = $this->makeAPICall('/products/search', [
            'curr' => $this->currency,
            'language' => $this->language,
            'pageSize' => '50',
            'currentPage' => '1',
            'keyword' => $keyword,
            'sort' => 'relevance']);
            
            return array_map(fn($product) => $this->getPartDetail($product), $response['products'] ?? []);
    }

    public function getDetails(string $id): PartDetailDTO
    {
        $response = $this->makeAPICall("/products", ['sku' => $id]);
    
        if (empty($response['result'])) {
            throw new \RuntimeException("No part found with ID $id");
        }
    
        return $this->getPartDetail($response['result']);
    }

    public function getCapabilities(): array
    {
        return [
            ProviderCapabilities::BASIC,
            ProviderCapabilities::FOOTPRINT,
            ProviderCapabilities::PICTURE,
            ProviderCapabilities::DATASHEET,
            ProviderCapabilities::PRICE,
        ];
    }
}
