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

    private const ENDPOINT_URL = 'https://www.buerklin.com/buerklinws/v2/buerklin';

    public const DISTRIBUTOR_NAME = 'Buerklin';
    private const OAUTH_APP_NAME = 'ip_buerklin_oauth';

    public function __construct(
        private readonly HttpClientInterface $client,
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
        #[Autowire(env: "string:PROVIDER_BUERKLIN_LANGUAGE")]
        private readonly string $language = "en",
        #[Autowire(env: "string:PROVIDER_BUERKLIN_CURRENCY")]
        private readonly string $currency = "EUR"
    ) {

    }

    /**
     * Gets the latest OAuth token for the Buerklin API, or creates a new one if none is available
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

        // Bürklin OAuth2 password grant (ROPC)
        $resp = $this->client->request('POST', 'https://www.buerklin.com/authorizationserver/oauth/token/', [
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
            'body' => [
                'grant_type' => 'password',
                'client_id' => $this->clientId,
                'client_secret' => $this->secret,
                'username' => $this->username,
                'password' => $this->password,
            ],
        ]);

        $data = $resp->toArray(false);

        if (!isset($data['access_token'])) {
            throw new \RuntimeException(
                'Invalid token response from Bürklin: HTTP ' . $resp->getStatusCode() . ' body=' . $resp->getContent(false)
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

    private function makeAPICall(string $endpoint, array $queryParams = []): array
    {
        try {
            $response = $this->client->request('GET', self::ENDPOINT_URL . $endpoint, [
                'auth_bearer' => $this->getToken(),
                'headers' => ['Accept' => 'application/json'],
                'query' => array_merge(['curr' => $this->currency ?: 'EUR', 'language' => $this->language ?: 'de'], $queryParams),
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
            'oauth_app_name' => self::OAUTH_APP_NAME,
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
        return $this->clientId !== ''
            && $this->secret !== ''
            && $this->username !== ''
            && $this->password !== '';
    }

    /**
     * @param  string  $id
     * @return PartDetailDTO
     */
    private function queryDetail(string $id): PartDetailDTO
    {
        $product = $this->makeAPICall('/products/' . rawurlencode($id) . '/', [
            'curr' => $this->currency,
            'language' => $this->language,
        ]);
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
        // If this is a search-result object, it may not contain prices/features/images -> reload full detail.
        if ((!isset($product['price']) && !isset($product['volumePrices'])) && isset($product['code'])) {
            try {
                $product = $this->makeAPICall('/products/' . rawurlencode((string) $product['code']) . '/');
            } catch (\Throwable $e) {
                // If reload fails, keep the partial product data and continue.
            }
        }

        // Images (already absolute + dedup in getProductImages())
        $productImages = $this->getProductImages($product['images'] ?? null);

        // Preview image: DO NOT prefix ENDPOINT_URL here (images are already absolute)
        $preview = $productImages[0]->url ?? null;

        // Features live in classifications[0].features in Bürklin JSON
        $features = $product['classifications'][0]['features'] ?? [];
        $group = $product['classifications'][0]['name'] ?? null;


        // 1) Feature-Parameter (aus classifications->features)
        $featureParams = $this->attributesToParameters($features, $group);

        // 2) Compliance-Parameter (aus Top-Level Feldern wie RoHS/SVHC/…)
        $complianceParams = $this->complianceToParameters($product, 'Compliance');

        // 3) Zusammenführen
        $allParams = array_merge($featureParams, $complianceParams);

        // Footprint: "Design" (en) / "Bauform" (de)
        $footprint = null;
        if (is_array($features)) {
            foreach ($features as $feature) {
                $name = $feature['name'] ?? null;
                if ($name === 'Design' || $name === 'Bauform') {
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
            $pCur = $product['price']['currencyIso'] ?? ($this->currency ?: 'EUR');

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

            datasheets: null, // not in /products/{code}/ JSON; you decided to skip for now
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
                ? number_format((float) $val, 6, '.', '') // 6 Nachkommastellen, trailing zeros ok
                : (string) $val;

            // Optional: weich kürzen (z.B. 75.550000 -> 75.55)
            $valStr = rtrim(rtrim($valStr, '0'), '.');

            return new PriceDTO(
                minimum_discount_amount: (float) ($price['minQuantity'] ?? 1),
                price: $valStr,
                currency_iso_code: (string) ($price['currencyIso'] ?? $this->currency ?? 'EUR'),
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
        return 'https://www.buerklin.com/de/p/' . $product_code . '/';
    }

    /**
     * Returns a deduplicated list of product images as FileDTOs.
     *
     * Bürklin liefert oft mehrere Einträge mit gleicher URL (und verschiedene "format"s).
     * Diese Variante:
     * - nimmt nur echte URL-Strings
     * - macht relative URLs absolut
     * - dedupliziert nach URL
     * - bevorzugt zoom/product vor thumbnail
     *
     * @param  array|null $images
     * @return \App\Services\InfoProviderSystem\DTOs\FileDTO[]
     */
    private function getProductImages(?array $images): array
    {
        if (!is_array($images))
            return [];

        // 1) Nur echte Image-Arrays
        $imgs = array_values(array_filter($images, fn($i) => is_array($i) && !empty($i['url'])));

        // 2) Bevorzuge zoom; wenn vorhanden, nimm ausschließlich zoom
        $zoom = array_values(array_filter($imgs, fn($i) => ($i['format'] ?? null) === 'zoom'));
        $chosen = count($zoom) > 0
            ? $zoom
            : array_values(array_filter($imgs, fn($i) => ($i['format'] ?? null) === 'product'));

        // 3) Falls auch keine product-Bilder da sind, nimm alles (letzter Fallback)
        if (count($chosen) === 0) {
            $chosen = $imgs;
        }

        // 4) Dedupliziere nach URL + relativ -> absolut
        $byUrl = [];
        foreach ($chosen as $img) {
            $url = (string) $img['url'];

            if (!str_starts_with($url, 'http://') && !str_starts_with($url, 'https://')) {
                $url = 'https://www.buerklin.com' . $url;
            }
            if (!filter_var($url, FILTER_VALIDATE_URL))
                continue;

            $byUrl[$url] = $url;
        }

        return array_map(
            fn($url) => new \App\Services\InfoProviderSystem\DTOs\FileDTO($url),
            array_values($byUrl)
        );
    }



    /**
     * @param  array|null  $attributes
     * @return ParameterDTO[]
     */
    private function attributesToParameters(array $features, ?string $group = null): array
    {
        if (!is_array($features)) {
            return [];
        }

        $out = [];

        foreach ($features as $f) {
            if (!is_array($f))
                continue;

            $name = $f['name'] ?? null;
            if (!is_string($name) || trim($name) === '')
                continue;

            // Bürklin: featureValues ist ein Array von { value: "..." }
            $vals = [];
            foreach (($f['featureValues'] ?? []) as $fv) {
                if (is_array($fv) && isset($fv['value']) && is_string($fv['value']) && trim($fv['value']) !== '') {
                    $vals[] = trim($fv['value']);
                }
            }
            if (count($vals) === 0)
                continue;

            // Mehrfachwerte zusammenführen
            $value = implode(', ', array_values(array_unique($vals)));

            // Unit/Symbol aus Bürklin (optional)
            $unit = $f['featureUnit']['symbol'] ?? null;
            if (!is_string($unit) || trim($unit) === '') {
                $unit = null;
            }

            // ParameterDTO kann Zahl/Range/Unit parsing selbst
            $out[] = ParameterDTO::parseValueField(
                name: $name,
                value: $value,
                unit: $unit,
                symbol: null,
                group: $group
            );
        }

        // Dedupe nach Name (falls Bürklin doppelt liefert)
        $byName = [];
        foreach ($out as $p) {
            $byName[$p->name] ??= $p;
        }

        return array_values($byName);
    }


    public function searchByKeyword(string $keyword): array
    {
        $keyword = strtoupper(trim($keyword));
        if ($keyword === '') {
            return [];
        }

        $response = $this->makeAPICall('/products/search/', [
            'pageSize' => 50,
            'currentPage' => 1,
            'query' => $keyword,
            'sort' => 'relevance',
        ]);

        $products = $response['products'] ?? [];

        // Normalfall: Search liefert Treffer
        if (is_array($products) && count($products) > 0) {
            return array_map(fn($p) => $this->getPartDetail($p), $products);
        }

        // Fallback: Bestellnummer/Code direkt abfragen
        // (funktioniert bei deinen Postman-Tests für /products/{code}/)
        try {
            $product = $this->makeAPICall('/products/' . rawurlencode($keyword) . '/');
            return [$this->getPartDetail($product)];
        } catch (\Throwable $e) {
            return [];
        }
    }



    public function getDetails(string $id): PartDetailDTO
    {
        // Detail endpoint is /products/{code}/
        $response = $this->makeAPICall('/products/' . rawurlencode($id) . '/', [
            'curr' => $this->currency,
            'language' => $this->language,
        ]);

        return $this->getPartDetail($response);
    }

    public function getCapabilities(): array
    {
        return [
            ProviderCapabilities::BASIC,
            ProviderCapabilities::PICTURE,
            ProviderCapabilities::DATASHEET,
            ProviderCapabilities::PRICE,
            ProviderCapabilities::FOOTPRINT,
        ];
    }
    private function complianceToParameters(array $product, ?string $group = 'Compliance'): array
    {
        $params = [];

        $add = function (string $name, $value) use (&$params, $group) {
            if ($value === null)
                return;

            if (is_bool($value)) {
                $value = $value ? 'Yes' : 'No';
            } elseif (is_array($value) || is_object($value)) {
                // avoid dumping huge structures
                return;
            } else {
                $value = trim((string) $value);
                if ($value === '')
                    return;
            }

            $params[] = ParameterDTO::parseValueField(
                name: $name,
                value: (string) $value,
                unit: null,
                symbol: null,
                group: $group
            );
        };

        $add('RoHS', $product['labelRoHS'] ?? null);          // "Ja"
        $add('RoHS date', $product['dateRoHS'] ?? null);      // ISO string
        $add('SVHC', $product['SVHC'] ?? null);               // bool
        $add('Hazardous good', $product['hazardousGood'] ?? null);       // bool
        $add('Hazardous materials', $product['hazardousMaterials'] ?? null); // bool

        // Optional, oft nützlich:
        $add('Country of origin', $product['countryOfOrigin'] ?? null);
        $add('Customs code', $product['articleCustomsCode'] ?? null);

        return $params;
    }
}
