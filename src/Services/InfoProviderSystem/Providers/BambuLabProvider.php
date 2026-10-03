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

use App\Entity\Parts\ManufacturingStatus;
use App\Services\InfoProviderSystem\DTOs\FileDTO;
use App\Services\InfoProviderSystem\DTOs\ParameterDTO;
use App\Services\InfoProviderSystem\DTOs\PartDetailDTO;
use App\Services\InfoProviderSystem\DTOs\PriceDTO;
use App\Services\InfoProviderSystem\DTOs\ProviderInfoDTO;
use App\Services\InfoProviderSystem\DTOs\PurchaseInfoDTO;
use App\Services\InfoProviderSystem\DTOs\SearchResultDTO;
use App\Settings\InfoProviderSystem\BambuLabSettings;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * This provider retrieves the products (filaments, printer parts, accessories) of the Bambu Lab store, using the
 * (unofficial) JSON API of the store website.
 *
 * A product of the store (e.g. "PETG Translucent") normally has multiple variants (e.g. the colors and whether it comes
 * with a spool), which have their own images, prices and (for filaments) codes. The ID used by this provider is
 * therefore either just the URL slug of the product ("petg-translucent"), or the slug followed by the ID of the
 * variant ("petg-translucent/42235108098184"), which corresponds to the ?id= parameter of the store URLs.
 */
class BambuLabProvider implements InfoProviderInterface, URLHandlerInfoProviderInterface
{
    public const PROVIDER_KEY = 'bambulab';
    public const DISTRIBUTOR_NAME = 'Bambu Lab';

    private const SEARCH_ENDPOINT = '/mall-goods/product/globalSearchV2';
    private const PRODUCT_ENDPOINT = '/mall-goods/product/queryById';

    /** The maximum number of results requested from the search endpoint */
    private const SEARCH_LIMIT = 24;
    /** A search only retrieves the details (to list the matching variants) of this many products at the top of the results */
    private const MAX_VARIANT_LOOKUPS = 3;
    /** The time (in seconds) the product data is cached */
    private const CACHE_TTL = 3600 * 24;

    public function __construct(
        private readonly HttpClientInterface $client,
        private readonly CacheItemPoolInterface $partInfoCache,
        private readonly BambuLabSettings $settings,
    ) {
    }

    public function getProviderInfo(): ProviderInfoDTO
    {
        return new ProviderInfoDTO(
            key: self::PROVIDER_KEY,
            name: 'Bambu Lab',
            description: 'This provider uses the (unofficial) API of the Bambu Lab store to search for filaments, printer parts and accessories.',
            url: 'https://store.bambulab.com/',
            disabledHelp: 'Enable the provider in provider settings',
            settingsClass: BambuLabSettings::class,
            capabilities: [
                ProviderCapabilities::BASIC,
                ProviderCapabilities::PICTURE,
                ProviderCapabilities::PRICE,
                ProviderCapabilities::DATASHEET,
                ProviderCapabilities::PARAMETERS,
            ],
        );
    }

    public function isActive(): bool
    {
        return $this->settings->enabled;
    }

    public function searchByKeyword(string $keyword, array $options = []): array
    {
        $keyword = trim($keyword);
        if ($keyword === '') {
            return [];
        }

        $no_cache = (bool) ($options[self::OPTION_NO_CACHE] ?? false);

        $data = $this->apiRequest('POST', self::SEARCH_ENDPOINT, [
            'json' => [
                'content' => $keyword,
                'current' => 1,
                'size' => self::SEARCH_LIMIT,
            ],
        ]);

        $results = [];

        foreach (array_values($data['page']['records'] ?? []) as $position => $record) {
            if (!is_array($record) || empty($record['seoCode'])) {
                continue;
            }

            //If the search has matched a certain variant of the product (e.g. because the keyword was a filament code),
            //then list the matching variants instead of the whole product. That requires the details of the product,
            //so it is only done for the best matches, to not flood the API with requests.
            if (!empty($record['highlightProductSkuId']) && $position < self::MAX_VARIANT_LOOKUPS) {
                try {
                    $product = $this->getProduct((string) $record['seoCode'], $no_cache);
                    $skus = $this->findMatchingSkus($product, $keyword, (string) $record['highlightProductSkuId']);
                } catch (\Exception) {
                    //Fall back to the info of the search result
                    $skus = [];
                }

                if ($skus !== []) {
                    foreach ($skus as $sku) {
                        $results[] = $this->productToDTO($product, $sku);
                    }
                    continue;
                }
            }

            $results[] = new SearchResultDTO(
                provider_key: self::PROVIDER_KEY,
                provider_id: (string) $record['seoCode'],
                name: (string) ($record['name'] ?? $record['seoCode']),
                description: '',
                manufacturer: self::DISTRIBUTOR_NAME,
                preview_image_url: $record['mediaFiles'][0] ?? null,
                manufacturing_status: ManufacturingStatus::ACTIVE,
                provider_url: $this->getProductUrl((string) $record['seoCode']),
            );
        }

        return $results;
    }

    public function getDetails(string $id, array $options = []): PartDetailDTO
    {
        [$seoCode, $skuId] = $this->splitId($id);

        $product = $this->getProduct($seoCode, (bool) ($options[self::OPTION_NO_CACHE] ?? false));
        $skus = $this->getSkus($product);

        $sku = null;
        if ($skuId !== null) {
            foreach ($skus as $tmp) {
                if ((string) ($tmp['id'] ?? '') === $skuId) {
                    $sku = $tmp;
                    break;
                }
            }
            if ($sku === null) {
                throw new \RuntimeException(sprintf('The Bambu Lab product "%s" has no variant with the ID %s!', $seoCode, $skuId));
            }
        } elseif (count($skus) === 1) {
            //If there is just one variant, then the product is that variant
            $sku = $skus[0];
        }

        return $this->productToDTO($product, $sku, true);
    }

    public function getHandledDomains(): array
    {
        return ['bambulab.com'];
    }

    public function getIDFromURL(string $url): ?string
    {
        //URLs look like https://us.store.bambulab.com/products/petg-translucent?id=42235108098184
        //There might be a locale in front of the path (like /en/products/...) and the variant ID is optional
        $host = parse_url($url, PHP_URL_HOST);
        if (!is_string($host) || !str_ends_with(strtolower($host), 'store.bambulab.com')) {
            return null;
        }

        $path = parse_url($url, PHP_URL_PATH);
        if (!is_string($path) || !preg_match('#/products/([^/?\#]+)#', $path, $matches)) {
            return null;
        }
        $id = rawurldecode($matches[1]);

        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        if (isset($query['id']) && is_string($query['id']) && ctype_digit($query['id'])) {
            $id .= '/' . $query['id'];
        }

        return $id;
    }

    /**
     * Splits the given provider ID into the URL slug of the product and the (optional) ID of the variant.
     * @return array{0: string, 1: string|null}
     */
    private function splitId(string $id): array
    {
        $parts = explode('/', trim($id), 2);
        $seoCode = trim($parts[0]);
        $skuId = isset($parts[1]) && trim($parts[1]) !== '' ? trim($parts[1]) : null;

        if (!preg_match('/^[\w.~%-]+$/u', $seoCode) || ($skuId !== null && !ctype_digit($skuId))) {
            throw new \InvalidArgumentException('The given ID is not a valid Bambu Lab product ID!');
        }

        return [$seoCode, $skuId];
    }

    /**
     * Performs a request against the API of the configured store and returns the data part of the response.
     */
    private function apiRequest(string $method, string $endpoint, array $options = []): array
    {
        $region = $this->settings->region;

        $options['headers'] = [
            'Accept' => 'application/json',
            'Bbl-Locale' => $region->getLocale(),
            'X-BBL-STORE-REGION' => $region->value,
        ];

        $response = $this->client->request($method, $region->getApiBaseUrl() . $endpoint, $options)->toArray();

        //The API always answers with HTTP 200 and signals errors via the code field (1 means success)
        if ((int) ($response['code'] ?? 0) !== 1) {
            throw new \RuntimeException('The Bambu Lab store API returned an error: ' . ($response['message'] ?? 'unknown error'));
        }

        return is_array($response['data'] ?? null) ? $response['data'] : [];
    }

    /**
     * Returns the data of the product with the given URL slug.
     */
    private function getProduct(string $seoCode, bool $no_cache = false): array
    {
        $item = $this->partInfoCache->getItem('bambulab_product_' . $this->settings->region->value . '_' . md5($seoCode));
        if (!$no_cache && $item->isHit() && is_array($item->get())) {
            return $item->get();
        }

        $product = $this->apiRequest('GET', self::PRODUCT_ENDPOINT, [
            'query' => [
                'seoCode' => $seoCode,
            ],
        ]);

        if (empty($product['seoCode'])) {
            throw new \RuntimeException('Could not find the Bambu Lab product: ' . $seoCode);
        }

        $item->set($product);
        $item->expiresAfter(self::CACHE_TTL);
        $this->partInfoCache->save($item);

        return $product;
    }

    /**
     * Returns the variants of the given product.
     * @return array<int, array<string, mixed>>
     */
    private function getSkus(array $product): array
    {
        return array_values(array_filter($product['productSkuList'] ?? [], is_array(...)));
    }

    /**
     * Returns the variants of the product which match the given search keyword (e.g. the filament code).
     * If no variant matches by its name, the variant highlighted by the search is returned.
     * @return array<int, array<string, mixed>>
     */
    private function findMatchingSkus(array $product, string $keyword, string $highlightedSkuId): array
    {
        $matching = [];
        $highlighted = [];

        foreach ($this->getSkus($product) as $sku) {
            if (mb_stripos($this->getVariantName($sku), $keyword) !== false) {
                $matching[] = $sku;
            }
            if ((string) ($sku['id'] ?? '') === $highlightedSkuId) {
                $highlighted[] = $sku;
            }
        }

        return $matching !== [] ? $matching : $highlighted;
    }

    /**
     * Returns the name of the given variant, built from its properties (e.g. "Clear (32101) / Refill / 1 kg").
     */
    private function getVariantName(array $sku): string
    {
        $values = [];
        foreach ($sku['productSkuPropertyList'] ?? [] as $property) {
            $value = trim((string) ($property['propertyValue'] ?? ''));
            if ($value !== '') {
                $values[] = $value;
            }
        }

        return implode(' / ', $values);
    }

    /**
     * Returns the code of the given variant (for filaments the 5-digit code which is given in brackets behind the
     * color name, e.g. "Clear (32101)"), or null if it has none.
     */
    private function getVariantCode(array $sku): ?string
    {
        if (!empty($sku['skuCode'])) {
            return (string) $sku['skuCode'];
        }

        foreach ($sku['productSkuPropertyList'] ?? [] as $property) {
            if (preg_match('/\(([A-Z]?\d{4,6})\)\s*$/', (string) ($property['propertyValue'] ?? ''), $matches)) {
                return $matches[1];
            }
        }

        return null;
    }

    private function getProductUrl(string $seoCode, ?string $skuId = null): string
    {
        $url = 'https://' . $this->settings->region->getDomain() . '/products/' . rawurlencode($seoCode);
        if ($skuId !== null && $skuId !== '') {
            $url .= '?id=' . rawurlencode($skuId);
        }

        return $url;
    }

    /**
     * Converts the given product (and optionally one of its variants) to a DTO.
     * @param  array  $product The product data as returned by the API
     * @param  array|null  $sku The variant of the product the DTO should describe, or null to describe the whole product
     * @param  bool  $with_page If true, the product page is retrieved too, to find the documents and specifications
     * which are only listed there
     */
    private function productToDTO(array $product, ?array $sku = null, bool $with_page = false): PartDetailDTO
    {
        $seoCode = (string) $product['seoCode'];
        $skuId = $sku !== null && !empty($sku['id']) ? (string) $sku['id'] : null;
        //Only a product with multiple variants needs the variant in its name, ID and URL
        $is_variant = $skuId !== null && count($this->getSkus($product)) > 1;

        $name = trim((string) ($product['name'] ?? $seoCode));
        $variantName = $sku !== null ? $this->getVariantName($sku) : '';
        if ($is_variant && $variantName !== '') {
            $name .= ' - ' . $variantName;
        }

        $url = $this->getProductUrl($seoCode, $is_variant ? $skuId : null);
        $code = $sku !== null ? $this->getVariantCode($sku) : null;

        //Images: The one of the variant first, then the ones of the product
        $imageUrls = [];
        if ($sku !== null && !empty($sku['mediaFile']['url'])) {
            $imageUrls[] = (string) $sku['mediaFile']['url'];
        }
        foreach ($product['mediaFiles'] ?? [] as $media) {
            if (!empty($media['url'])) {
                $imageUrls[] = (string) $media['url'];
            }
        }
        $imageUrls = array_values(array_unique($imageUrls));

        $page = $with_page ? $this->getProductPage($url) : null;

        return new PartDetailDTO(
            provider_key: self::PROVIDER_KEY,
            provider_id: $is_variant ? $seoCode . '/' . $skuId : $seoCode,
            name: $name,
            description: $this->getDescription($product),
            category: ($product['isFilament'] ?? false) ? 'Filament' : null,
            manufacturer: self::DISTRIBUTOR_NAME,
            mpn: $code,
            preview_image_url: $imageUrls[0] ?? null,
            manufacturing_status: ManufacturingStatus::ACTIVE,
            provider_url: $url,
            notes: $this->getNotes($product),
            datasheets: $this->getDocuments($product, $page),
            images: array_map(static fn(string $imageUrl) => new FileDTO($imageUrl), $imageUrls),
            parameters: $this->getParameters($product, $sku, $page),
            vendor_infos: $this->getVendorInfos($product, $sku, $url, $code ?? $skuId ?? $seoCode),
            manufacturer_product_url: $url,
        );
    }

    /**
     * Returns a short description of the product.
     */
    private function getDescription(array $product): string
    {
        $text = $this->htmlToText((string) ($product['subTitle'] ?? ''));
        if ($text === '') {
            $text = $this->htmlToText((string) ($product['seoMetaDescription'] ?? ''));
        }

        //The meta description can be quite long, so only use the first sentence of it then
        if (mb_strlen($text) > 200 && preg_match('/^(.{30,}?[.!?])\s/su', $text, $matches)) {
            $text = $matches[1];
        }

        return $text;
    }

    private function htmlToText(string $html): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5)));
    }

    /**
     * Returns the description and the features of the product as HTML.
     */
    private function getNotes(array $product): ?string
    {
        $notes = [];

        $description = $this->htmlToText((string) ($product['seoMetaDescription'] ?? ''));
        if ($description !== '') {
            $notes[] = '<p>' . htmlspecialchars($description) . '</p>';
        }

        foreach ($this->getFeatureModules($product) as [$title, $content]) {
            //Remove the layout related attributes and elements of the store website
            $content = (string) preg_replace('/\s(?:style|class|data-[\w-]+)="[^"]*"/i', '', $content);
            $content = trim(strip_tags($content, '<div><p><br><ul><ol><li><b><strong><i><em><u><a><table><thead><tbody><tr><th><td><h3><h4><h5>'));
            //The links are relative to the product page
            $content = (string) preg_replace('#href="(?:\.\./)+#i', 'href="https://' . $this->settings->region->getDomain() . '/', $content);
            if ($content === '') {
                continue;
            }

            $notes[] = ($title !== '' ? '<h4>' . htmlspecialchars($title) . '</h4>' : '') . $content;
        }

        return $notes === [] ? null : implode("\n", $notes);
    }

    /**
     * Returns the feature texts of the product, as a list of their titles and their HTML contents.
     * @return array<int, array{0: string, 1: string}>
     */
    private function getFeatureModules(array $product): array
    {
        $modules = [];

        if (trim((string) ($product['productFeatures'] ?? '')) !== '') {
            $modules[] = [trim((string) ($product['productFeaturesTitle'] ?? '')), (string) $product['productFeatures']];
        }

        foreach ($product['productFeaturesModuleList'] ?? [] as $module) {
            $content = (string) ($module['content'] ?? '');
            //The first module is normally the same as the product features
            if (trim($content) !== '' && !in_array($content, array_column($modules, 1), true)) {
                $modules[] = [trim((string) ($module['title'] ?? '')), $content];
            }
        }

        return $modules;
    }

    /**
     * @return ParameterDTO[]
     */
    private function getParameters(array $product, ?array $sku, ?Crawler $page = null): array
    {
        $parameters = [];

        if ($sku !== null) {
            //The properties of the variant (e.g. color, type and size)
            foreach ($sku['productSkuPropertyList'] ?? [] as $property) {
                $name = trim((string) ($property['propertyKey'] ?? ''));
                $value = trim((string) ($property['propertyValue'] ?? ''));
                if ($name !== '' && $value !== '') {
                    $parameters[$name] = ParameterDTO::parseValueIncludingUnit($name, $value);
                }
            }
        } else {
            //The available options of the product
            foreach ($product['productPropertyList'] ?? [] as $property) {
                $name = trim((string) ($property['propertyKey'] ?? ''));
                $values = array_filter(array_map(
                    static fn($value) => trim((string) ($value['value'] ?? '')),
                    $property['productPropertyValueList'] ?? []
                ));
                if ($name !== '' && $values !== []) {
                    $parameters[$name] = new ParameterDTO(name: $name, value_text: implode(', ', $values));
                }
            }
        }

        //The feature lists contain entries like "Diameter: 1.75mm +/- 0.03mm"
        foreach ($this->getFeatureModules($product) as [, $content]) {
            (new Crawler('<html><body>' . $content . '</body></html>'))->filter('li')->each(function (Crawler $node) use (&$parameters) {
                if (preg_match('/^([^:：]{2,50})[:：]\s*(\S.*)$/su', trim($node->text()), $matches)) {
                    $name = trim($matches[1]);
                    $parameters[$name] ??= ParameterDTO::parseValueIncludingUnit($name, trim($matches[2]));
                }
            });
        }

        //The specification tables of the product page consist of name/value pairs and have no header, in contrast to
        //the comparison and compatibility tables.
        $page?->filter('table')->each(function (Crawler $table) use (&$parameters) {
            if ($table->filter('thead, th')->count() > 0) {
                return;
            }

            $table->filter('tr')->each(function (Crawler $row) use (&$parameters) {
                $cells = $row->filter('td')->each(static fn(Crawler $cell) => trim($cell->text()));
                if (count($cells) % 2 !== 0) {
                    return;
                }

                foreach (array_chunk($cells, 2) as [$name, $value]) {
                    $name = rtrim($name, ':： ');
                    if ($name !== '' && $value !== '' && mb_strlen($name) <= 100) {
                        $parameters[$name] ??= ParameterDTO::parseValueIncludingUnit($name, $value);
                    }
                }
            });
        });

        return array_values($parameters);
    }

    /**
     * @return PurchaseInfoDTO[]
     */
    private function getVendorInfos(array $product, ?array $sku, string $url, string $orderNumber): array
    {
        //If no variant is given, use the lowest price of all variants
        $price = null;
        foreach ($sku !== null ? [$sku] : $this->getSkus($product) as $tmp) {
            $tmpPrice = $tmp['discountPrice'] ?? $tmp['price'] ?? null;
            if (is_numeric($tmpPrice) && ($price === null || (float) $tmpPrice < $price)) {
                $price = (float) $tmpPrice;
            }
        }

        $region = $this->settings->region;

        $prices = [];
        if ($price !== null) {
            $prices[] = new PriceDTO(
                minimum_discount_amount: 1,
                price: number_format($price, 2, '.', ''),
                currency_iso_code: $region->getCurrency(),
                includes_tax: $region->pricesIncludeVAT(),
            );
        }

        return [
            new PurchaseInfoDTO(
                distributor_name: self::DISTRIBUTOR_NAME,
                order_number: $orderNumber,
                prices: $prices,
                product_url: $url,
                prices_include_vat: $region->pricesIncludeVAT(),
            ),
        ];
    }

    /**
     * Retrieves the product page of the store website. Many documents and the specifications are not part of the
     * API response, but only contained in the description on the product page.
     * As this info is optional, null is returned if the page could not be retrieved.
     */
    private function getProductPage(string $url): ?Crawler
    {
        try {
            $html = $this->client->request('GET', $url, [
                'headers' => [
                    'Accept' => 'text/html',
                ],
            ])->getContent();
        } catch (\Exception) {
            return null;
        }

        return new Crawler($html);
    }

    /**
     * Returns the documents (technical data sheets, safety data sheets, manuals, ...) of the product.
     * @param  array  $product
     * @param  Crawler|null  $page If given, the documents linked on the product page are returned too
     * @return FileDTO[]
     */
    private function getDocuments(array $product, ?Crawler $page = null): array
    {
        $documents = [];

        foreach ($product['productExtraInfoVO']['productFileList'] ?? [] as $file) {
            if (!empty($file['url'])) {
                $documents[(string) $file['url']] = new FileDTO((string) $file['url'], ((string) ($file['title'] ?? '')) ?: null);
            }
        }

        //E.g. the data sheets of the filaments are only linked in the download section of the product page
        $page?->filter('a[href]')->each(function (Crawler $node) use (&$documents) {
            $href = (string) $node->attr('href');
            if (preg_match('#^https://[^/]+/.+\.pdf(\?.*)?$#i', $href)) {
                $documents[$href] ??= new FileDTO($href, trim($node->text()) ?: null);
            }
        });

        return array_values($documents);
    }
}
