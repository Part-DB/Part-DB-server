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
use App\Services\InfoProviderSystem\DTOs\SearchResultDTO;
use App\Settings\InfoProviderSystem\BuerklinSettings;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class BuerklinProvider implements BatchInfoProviderInterface, URLHandlerInfoProviderInterface
{

    private const ENDPOINT_URL = 'https://www.buerklin.com/buerklinws/v2/buerklin';

    public const DISTRIBUTOR_NAME = 'Buerklin';

    private const CACHE_TTL = 600;
    /**
     * Local in-request cache to avoid hitting the PSR cache repeatedly for the same product.
     * @var array<string, array>
     */
    private array $productCache = [];

    public function __construct(
        private readonly HttpClientInterface $client,
        private readonly CacheItemPoolInterface $partInfoCache,
        private readonly BuerklinSettings $settings,
    ) {

    }

    /**
     * Gets the latest OAuth token for the Buerklin API, or creates a new one if none is available
     * TODO: Rework this to use the OAuth token manager system in the database...
     * @return string
     */
    private function getToken(): string
    {
        // Cache token to avoid hammering the auth server on every request
        $cacheKey = 'buerklin.oauth.token';
        $item = $this->partInfoCache->getItem($cacheKey);

        if ($item->isHit()) {
            $token = $item->get();
            if (is_string($token) && $token !== '') {
                return $token;
            }
        }

        // Buerklin OAuth2 password grant (ROPC)
        $resp = $this->client->request('POST', 'https://www.buerklin.com/authorizationserver/oauth/token/', [
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
            'body' => [
                'grant_type' => 'password',
                'client_id' => $this->settings->clientId,
                'client_secret' => $this->settings->secret,
                'username' => $this->settings->username,
                'password' => $this->settings->password,
            ],
        ]);

        $data = $resp->toArray(false);

        if (!isset($data['access_token'])) {
            throw new \RuntimeException(
                'Invalid token response from Buerklin: HTTP ' . $resp->getStatusCode() . ' body=' . $resp->getContent(false)
            );
        }

        $token = (string) $data['access_token'];

        // Cache for (expires_in - 30s) if available
        $ttl = 300;
        if (isset($data['expires_in']) && is_numeric($data['expires_in'])) {
            $ttl = max(60, (int) $data['expires_in'] - 30);
        }

        $item->set($token);
        $item->expiresAfter($ttl);
        $this->partInfoCache->save($item);

        return $token;
    }

    private function getDefaultQueryParams(): array
    {
        return [
            'curr' => $this->settings->currency ?: 'EUR',
            'language' => $this->settings->language ?: 'en',
        ];
    }

    private function getProduct(string $code): array
    {
        $code = strtoupper(trim($code));
        if ($code === '') {
            throw new \InvalidArgumentException('Product code must not be empty.');
        }

        $cacheKey = sprintf(
            'buerklin.product.%s',
            md5($code . '|' . $this->settings->language . '|' . $this->settings->currency)
        );

        if (isset($this->productCache[$cacheKey])) {
            return $this->productCache[$cacheKey];
        }

        $item = $this->partInfoCache->getItem($cacheKey);
        if ($item->isHit() && is_array($cached = $item->get())) {
            return $this->productCache[$cacheKey] = $cached;
        }

        $product = $this->makeAPICall('/products/' . rawurlencode($code) . '/');

        $item->set($product);
        $item->expiresAfter(self::CACHE_TTL);
        $this->partInfoCache->save($item);

        return $this->productCache[$cacheKey] = $product;
    }

    private function makeAPICall(string $endpoint, array $queryParams = []): array
    {
        try {
            $response = $this->client->request('GET', self::ENDPOINT_URL . $endpoint, [
                'auth_bearer' => $this->getToken(),
                'headers' => ['Accept' => 'application/json'],
                'query' => array_merge($this->getDefaultQueryParams(), $queryParams),
            ]);

            return $response->toArray();
        } catch (\Exception $e) {
            throw new \RuntimeException("Buerklin API request failed: " .
                "Endpoint: " . $endpoint .
                "Token: [redacted] " .
                "QueryParams: " . json_encode($queryParams, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . " " .
                "Exception message: " . $e->getMessage());
        }
    }


    public function getProviderInfo(): array
    {
        return [
            'name' => 'Buerklin',
            'description' => 'This provider uses the Buerklin API to search for parts.',
            'url' => 'https://www.buerklin.com/',
            'disabled_help' => 'Configure the API Client ID, Secret, Username and Password provided by Buerklin in the provider settings to enable.',
            'settings_class' => BuerklinSettings::class
        ];
    }

    public function getProviderKey(): string
    {
        return 'buerklin';
    }

    // This provider is considered active if settings are present
    public function isActive(): bool
    {
        // The client credentials and user credentials must be set
        return $this->settings->clientId !== null && $this->settings->clientId !== ''
            && $this->settings->secret !== null && $this->settings->secret !== ''
            && $this->settings->username !== null && $this->settings->username !== ''
            && $this->settings->password !== null && $this->settings->password !== '';
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
     * Takes a deserialized JSON object of the product and returns a PartDetailDTO
     * @param  array  $product
     * @return PartDetailDTO
     */
    private function getPartDetail(array $product): PartDetailDTO
    {
        // If this is a search-result object, it may not contain prices/features/images -> reload full details.
        if ((!isset($product['price']) && !isset($product['volumePrices'])) && isset($product['code'])) {
            try {
                $product = $this->getProduct((string) $product['code']);
            } catch (\Throwable $e) {
                // If reload fails, keep the partial product data and continue.
            }
        }

        // Extract images from API response
        $productImages = $this->getProductImages($product['images'] ?? null);

        // Set preview image
        $preview = $productImages[0]->url ?? null;

        // Extract features (parameters) from classifications[0].features of Buerklin JSON response
        $features = $product['classifications'][0]['features'] ?? [];

        // Feature parameters (from classifications->features)
        $featureParams = $this->attributesToParameters($features, ''); // leave group empty for normal parameters

        // Compliance parameters (from top-level fields like RoHS/SVHC/…)
        $complianceParams = $this->complianceToParameters($product, 'Compliance');

        // Merge all parameters
        $allParams = array_merge($featureParams, $complianceParams);

        // Assign footprint: "Design" (en) / "Bauform" (de) / "Enclosure" (en) / "Gehäuse" (de)
        $footprint = null;
        if (is_array($features)) {
            foreach ($features as $feature) {
                $name = $feature['name'] ?? null;
                if ($name === 'Design' || $name === 'Bauform' || $name === 'Enclosure' || $name === 'Gehäuse') {
                    $footprint = $feature['featureValues'][0]['value'] ?? null;
                    break;
                }
            }
        }

        // Prices: prefer volumePrices, fallback to single price
        $code = (string) ($product['orderNumber'] ?? $product['code'] ?? '');
        $prices = $product['volumePrices'] ?? null;

        if (!is_array($prices) || count($prices) === 0) {
            $pVal = $product['price']['value'] ?? null;
            $pCur = $product['price']['currencyIso'] ?? ($this->settings->currency ?: 'EUR');

            if (is_numeric($pVal)) {
                $prices = [
                    [
                        'minQuantity' => 1,
                        'value' => (float) $pVal,
                        'currencyIso' => (string) $pCur,
                    ]
                ];
            } else {
                $prices = [];
            }
        }

        return new PartDetailDTO(
            provider_key: $this->getProviderKey(),
            provider_id: (string) ($product['code'] ?? $code),

            name: (string) ($product['manufacturerProductId'] ?? $code),
            description: $this->sanitizeField($product['description'] ?? null),

            category: $this->sanitizeField($product['classifications'][0]['name'] ?? ($product['categories'][0]['name'] ?? null)),
            manufacturer: $this->sanitizeField($product['manufacturer'] ?? null),
            mpn: $this->sanitizeField($product['manufacturerProductId'] ?? null),

            preview_image_url: $preview,
            manufacturing_status: null,

            provider_url: $this->getProductShortURL((string) ($product['code'] ?? $code)),
            footprint: $footprint,

            datasheets: null, // not found in JSON response, the Buerklin website however has links to datasheets
            images: $productImages,

            parameters: $allParams,

            vendor_infos: $this->pricesToVendorInfo(
                sku: $code,
                url: $this->getProductShortURL($code),
                prices: $prices
            ),

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
        $priceDTOs = array_map(function ($price) {
            $val = $price['value'] ?? null;
            $valStr = is_numeric($val)
                ? number_format((float) $val, 6, '.', '') // 6 decimal places, trailing zeros are fine
                : (string) $val;

            // Optional: softly trim unnecessary trailing zeros (e.g. 75.550000 -> 75.55)
            $valStr = rtrim(rtrim($valStr, '0'), '.');

            return new PriceDTO(
                minimum_discount_amount: (float) ($price['minQuantity'] ?? 1),
                price: $valStr,
                currency_iso_code: (string) ($price['currencyIso'] ?? $this->settings->currency ?? 'EUR'),
                includes_tax: false
            );
        }, $prices);

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
        return 'https://www.buerklin.com/' . $this->settings->language . '/p/' . $product_code . '/';
    }

    /**
     * Returns a deduplicated list of product images as FileDTOs.
     *
     * - takes only real image arrays (with 'url' field)
     * - makes relative URLs absolute
     * - deduplicates using URL
     * - prefers 'zoom' format, then 'product' format, then all others
     *
     * @param  array|null $images
     * @return \App\Services\InfoProviderSystem\DTOs\FileDTO[]
     */
    private function getProductImages(?array $images): array
    {
        if (!is_array($images)) {
            return [];
        }

        // 1) Only real image entries with URL
        $imgs = array_values(array_filter($images, fn($i) => is_array($i) && !empty($i['url'])));

        // 2) Prefer zoom images
        $zoom = array_values(array_filter($imgs, fn($i) => ($i['format'] ?? null) === 'zoom'));
        $chosen = count($zoom) > 0
            ? $zoom
            : array_values(array_filter($imgs, fn($i) => ($i['format'] ?? null) === 'product'));

        // 3) If still none, take all
        if (count($chosen) === 0) {
            $chosen = $imgs;
        }

        // 4) Deduplicate by URL (after making absolute)
        $byUrl = [];
        foreach ($chosen as $img) {
            $url = (string) $img['url'];

            if (!str_starts_with($url, 'http://') && !str_starts_with($url, 'https://')) {
                $url = 'https://www.buerklin.com' . $url;
            }
            if (!filter_var($url, FILTER_VALIDATE_URL)) {
                continue;
            }

            $byUrl[$url] = $url;
        }

        return array_map(
            fn($url) => new FileDTO($url),
            array_values($byUrl)
        );
    }

    private function attributesToParameters(array $features, ?string $group = null): array
    {
        $out = [];

        foreach ($features as $f) {
            if (!is_array($f)) {
                continue;
            }

            $name = $f['name'] ?? null;
            if (!is_string($name) || trim($name) === '') {
                continue;
            }

            $vals = [];
            foreach (($f['featureValues'] ?? []) as $fv) {
                if (is_array($fv) && isset($fv['value']) && is_string($fv['value']) && trim($fv['value']) !== '') {
                    $vals[] = trim($fv['value']);
                }
            }
            if (empty($vals)) {
                continue;
            }

            // Multiple values: join with comma
            $value = implode(', ', array_values(array_unique($vals)));

            // Unit/symbol from Buerklin feature
            $unit = $f['featureUnit']['symbol'] ?? null;
            if (!is_string($unit) || trim($unit) === '') {
                $unit = null;
            }

            // ParameterDTO parses value field (handles value + unit)
            $out[] = ParameterDTO::parseValueField(
                name: $name,
                value: $value,
                unit: $unit,
                symbol: null,
                group: $group
            );
        }

        // Deduplicate by name
        $byName = [];
        foreach ($out as $p) {
            $byName[$p->name] ??= $p;
        }

        return array_values($byName);
    }

    /**
     * @return PartDetailDTO[]
     */
    public function searchByKeyword(string $keyword): array
    {
        $keyword = strtoupper(trim($keyword));
        if ($keyword === '') {
            return [];
        }

        $response = $this->makeAPICall('/products/search/', [
            'pageSize' => 50,
            'currentPage' => 0,
            'query' => $keyword,
            'sort' => 'relevance',
        ]);

        $products = $response['products'] ?? [];

        // Normal case: products found in search results
        if (is_array($products) && !empty($products)) {
            return array_map(fn($p) => $this->getPartDetail($p), $products);
        }

        // Fallback: try direct lookup by code
        try {
            $product = $this->getProduct($keyword);
            return [$this->getPartDetail($product)];
        } catch (\Throwable $e) {
            return [];
        }
    }

    public function getDetails(string $id): PartDetailDTO
    {
        // Detail endpoint is /products/{code}/
        $response = $this->getProduct($id);

        return $this->getPartDetail($response);
    }

    public function getCapabilities(): array
    {
        return [
            ProviderCapabilities::BASIC,
            ProviderCapabilities::PICTURE,
                //ProviderCapabilities::DATASHEET, // currently not implemented
            ProviderCapabilities::PRICE,
            ProviderCapabilities::FOOTPRINT,
        ];
    }

    private function complianceToParameters(array $product, ?string $group = 'Compliance'): array
    {
        $params = [];

        $add = function (string $name, $value) use (&$params, $group) {
            if ($value === null) {
                return;
            }

            if (is_bool($value)) {
                $value = $value ? 'Yes' : 'No';
            } elseif (is_array($value) || is_object($value)) {
                // Avoid dumping large or complex structures
                return;
            } else {
                $value = trim((string) $value);
                if ($value === '') {
                    return;
                }
            }

            $params[] = ParameterDTO::parseValueField(
                name: $name,
                value: (string) $value,
                unit: null,
                symbol: null,
                group: $group
            );
        };

        $add('RoHS conform', $product['labelRoHS'] ?? null);          // "yes"/"no"

        $rawRoHsDate = $product['dateRoHS'] ?? null;
        // Try to parse and reformat date to Y-m-d (do not use language-dependent formats)
        if (is_string($rawRoHsDate) && $rawRoHsDate !== '') {
            try {
                $dt = new \DateTimeImmutable($rawRoHsDate);
                $formatted = $dt->format('Y-m-d');
            } catch (\Exception $e) {
                $formatted = $rawRoHsDate;
            }
            // Always use the same parameter name (do not use language-dependent names)
            $add('RoHS date', $formatted);
        }
        $add('SVHC free', $product['SVHC'] ?? null);               // bool
        $add('Hazardous good', $product['hazardousGood'] ?? null);       // bool
        $add('Hazardous materials', $product['hazardousMaterials'] ?? null); // bool

        $add('Country of origin', $product['countryOfOrigin'] ?? null);
        // Customs tariff code must always be stored as string, otherwise "85411000" may be stored as "8.5411e+7"
        if (isset($product['articleCustomsCode'])) {
            // Raw value as string
            $codeRaw = (string) $product['articleCustomsCode'];

            // Optionally keep only digits (in case of spaces or other characters)
            $code = preg_replace('/\D/', '', $codeRaw) ?? $codeRaw;
            $code = trim($code);

            if ($code !== '') {
                $params[] = new ParameterDTO(
                    name: 'Customs code',
                    value_text: $code,
                    value_typ: null,
                    value_min: null,
                    value_max: null,
                    unit: null,
                    symbol: null,
                    group: $group
                );
            }
        }

        return $params;
    }

    /**
     * @param string[] $keywords
     * @return array<string, SearchResultDTO[]>
     */
    public function searchByKeywordsBatch(array $keywords): array
    {
        /** @var array<string, SearchResultDTO[]> $results */
        $results = [];

        foreach ($keywords as $keyword) {
            $keyword = strtoupper(trim((string) $keyword));
            if ($keyword === '') {
                continue;
            }

            // Reuse existing single search -> returns PartDetailDTO[]
            /** @var PartDetailDTO[] $partDetails */
            $partDetails = $this->searchByKeyword($keyword);

            // Convert to SearchResultDTO[]
            $results[$keyword] = array_map(
                fn(PartDetailDTO $detail) => $this->convertPartDetailToSearchResult($detail),
                $partDetails
            );
        }

        return $results;
    }

    /**
     * Converts a PartDetailDTO into a SearchResultDTO for bulk search.
     */
    private function convertPartDetailToSearchResult(PartDetailDTO $detail): SearchResultDTO
    {
        return new SearchResultDTO(
            provider_key: $detail->provider_key,
            provider_id: $detail->provider_id,
            name: $detail->name,
            description: $detail->description ?? '',
            category: $detail->category ?? null,
            manufacturer: $detail->manufacturer ?? null,
            mpn: $detail->mpn ?? null,
            preview_image_url: $detail->preview_image_url ?? null,
            manufacturing_status: $detail->manufacturing_status ?? null,
            provider_url: $detail->provider_url ?? null,
            footprint: $detail->footprint ?? null,
        );
    }

    public function getHandledDomains(): array
    {
        return ['buerklin.com'];
    }

    public function getIDFromURL(string $url): ?string
    {
        //Inputs: 
        //https://www.buerklin.com/de/p/bkl-electronic/niedervoltsteckverbinder/072341-l/40F1332/ 
        //https://www.buerklin.com/de/p/40F1332/
        //https://www.buerklin.com/en/p/bkl-electronic/dc-connectors/072341-l/40F1332/
        //https://www.buerklin.com/en/p/40F1332/
        //The ID is the last part after the manufacturer/category/mpn segment and before the final slash
        //https://www.buerklin.com/de/p/bkl-electronic/niedervoltsteckverbinder/072341-l/40F1332/#download should also work
        
        $path = parse_url($url, PHP_URL_PATH);
    
        if (!$path) {
            return null;
        }
    
        // Ensure it's actually a product URL
        if (strpos($path, '/p/') === false) {
            return null;
        }
    
        $id = basename(rtrim($path, '/'));
    
        return $id !== '' ? $id : null;
    }

}
