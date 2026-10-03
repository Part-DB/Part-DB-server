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

use App\Entity\Parts\ManufacturingStatus;
use App\Services\InfoProviderSystem\DTOs\FileDTO;
use App\Services\InfoProviderSystem\DTOs\ParameterDTO;
use App\Services\InfoProviderSystem\DTOs\PartDetailDTO;
use App\Services\InfoProviderSystem\DTOs\PriceDTO;
use App\Services\InfoProviderSystem\DTOs\ProviderInfoDTO;
use App\Services\InfoProviderSystem\DTOs\PurchaseInfoDTO;
use App\Services\InfoProviderSystem\DTOs\SearchResultDTO;
use App\Settings\InfoProviderSystem\AdafruitSettings;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Retrieves part infos from adafruit.com, using their public product API.
 *
 * The API has no search endpoint, it only offers the whole catalog as one large list (several MB). Therefore the
 * catalog is downloaded once, cached and searched locally. The details of a single product come from the product
 * endpoint of the API and (optionally) from the product page, which is the only place where the image gallery,
 * the technical details, the category and the learn guides are available.
 *
 * All requests to adafruit.com (catalog, product API and product pages) are spaced by a configurable delay, and after
 * the website has refused a request, no requests are sent at all for some time.
 */
class AdafruitProvider implements InfoProviderInterface, URLHandlerInfoProviderInterface
{
    public const PROVIDER_KEY = 'adafruit';

    public const BASE_URL = 'https://www.adafruit.com';
    public const CATALOG_API_URL = self::BASE_URL . '/api/products';
    public const PRODUCT_API_URL = self::BASE_URL . '/api/product/';
    public const PRODUCT_PAGE_URL = self::BASE_URL . '/product/';

    public const DISTRIBUTOR_NAME = 'Adafruit';
    /** @var string Adafruit only publishes prices in US dollar */
    public const CURRENCY = 'USD';

    /** @var int The maximum number of results returned by a keyword search */
    public const MAX_SEARCH_RESULTS = 50;

    private const CATALOG_CACHE_KEY = 'adafruit_catalog_v1';
    private const CATALOG_CACHE_TTL = 3600 * 24; //1 day

    /** @var int[] The HTTP status codes with which adafruit.com tells us that it does not want our requests (anymore) */
    private const REFUSED_STATUS_CODES = [403, 429, 503];
    /** @var int The time (in seconds) no requests are sent to adafruit.com, after it has refused one */
    private const PAUSE_DURATION = 3600;

    private const CACHE_KEY_PAUSED = 'adafruit_paused';
    private const CACHE_KEY_NEXT_REQUEST = 'adafruit_next_request';

    /** @var string[] The fields of a catalog entry, which are kept in the cache */
    private const CATALOG_FIELDS = ['product_id', 'product_name', 'product_model', 'product_mpn', 'product_manufacturer',
        'product_image', 'product_stock', 'discontinue_status', 'products_coming_soon'];

    /** @var string Links to files with these extensions are offered as documents */
    private const DOCUMENT_EXTENSION_REGEX = '/\.(pdf|zip|step|stp|stl|dxf|brd|sch|fzpz|fzz)(\?.*)?$/i';
    /** @var string Links whose text contains one of these words are offered as documents */
    private const DOCUMENT_TEXT_REGEX = '/\b(datasheet|data sheet|schematic|manual|user guide|app(lication)? note|specification|spec sheet|cad files?|fritzing|eagle)/i';

    public function __construct(private readonly HttpClientInterface $client,
        private readonly AdafruitSettings $settings,
        private readonly CacheItemPoolInterface $partInfoCache,
    )
    {
    }

    public function getProviderInfo(): ProviderInfoDTO
    {
        return new ProviderInfoDTO(
            key: self::PROVIDER_KEY,
            name: 'Adafruit',
            description: 'Retrieves part information from adafruit.com using their public product API',
            url: 'https://www.adafruit.com/',
            disabledHelp: 'Enable the provider in provider settings',
            settingsClass: AdafruitSettings::class,
            capabilities: [
                ProviderCapabilities::BASIC,
                ProviderCapabilities::PICTURE,
                ProviderCapabilities::DATASHEET,
                ProviderCapabilities::PRICE,
                ProviderCapabilities::PARAMETERS,
                ProviderCapabilities::STOCK_LEVEL,
            ],
        );
    }

    public function isActive(): bool
    {
        return $this->settings->enabled;
    }

    /**
     * Sends a request to adafruit.com. Every request of this provider (to the API and to the product pages) has to use
     * this method: It spaces the requests, and once the shop has refused a request, it does not send any further
     * requests for some time.
     */
    private function request(string $method, string $url, array $options = []): ResponseInterface
    {
        $paused = $this->partInfoCache->getItem(self::CACHE_KEY_PAUSED);
        if ($paused->isHit() && is_array($paused->get()) && ($paused->get()['until'] ?? 0) > time()) {
            throw new \RuntimeException(sprintf(
                'The Adafruit provider is paused until %s, because adafruit.com refused a request (%s). No requests are sent to it until then.',
                date('Y-m-d H:i:s T', (int) $paused->get()['until']), $paused->get()['reason'] ?? 'unknown reason'
            ));
        }

        $this->waitForNextRequest();

        $response = $this->client->request($method, $url, $options);

        $status = $response->getStatusCode();
        if (in_array($status, self::REFUSED_STATUS_CODES, true)) {
            $until = time() + self::PAUSE_DURATION;

            $paused->set(['until' => $until, 'reason' => 'HTTP status ' . $status]);
            $paused->expiresAfter(self::PAUSE_DURATION);
            $this->partInfoCache->save($paused);

            throw new \RuntimeException(sprintf(
                'adafruit.com refused the request (HTTP status %d). The Adafruit provider is paused until %s, no requests are sent to it until then.',
                $status, date('Y-m-d H:i:s T', $until)
            ));
        }

        return $response;
    }

    /**
     * Sleeps until the configured delay has passed since the last request to adafruit.com. The time of the next
     * allowed request is stored in the cache, so the delay is kept between different lookups and PHP processes too.
     */
    private function waitForNextRequest(): void
    {
        $delay = $this->settings->requestDelay;
        if ($delay <= 0) {
            return;
        }

        $item = $this->partInfoCache->getItem(self::CACHE_KEY_NEXT_REQUEST);
        $now = microtime(true);
        $slot = $item->isHit() ? max($now, (float) $item->get()) : $now;

        //Reserve the time after our slot before sleeping, so that parallel requests queue up behind us
        $item->set($slot + $delay);
        $item->expiresAfter((int) ceil($slot - $now) + $delay);
        $this->partInfoCache->save($item);

        if ($slot > $now) {
            usleep((int) (($slot - $now) * 1_000_000));
        }
    }

    /**
     * Returns the product catalog as array of (reduced) catalog entries, indexed by the product ID.
     * The catalog is one large response, so it is cached and only downloaded again after the cache expired.
     * @param  bool  $forceRefresh If true the catalog is downloaded even if it is cached
     * @return array<string, array<string, mixed>>
     */
    private function getCatalog(bool $forceRefresh = false): array
    {
        $item = $this->partInfoCache->getItem(self::CATALOG_CACHE_KEY);
        if (!$forceRefresh && $item->isHit()) {
            return $item->get();
        }

        $response = $this->request('GET', self::CATALOG_API_URL, [
            'timeout' => 60,
        ]);

        $catalog = [];
        foreach ($response->toArray() as $product) {
            if (!is_array($product) || !isset($product['product_id'])) {
                continue;
            }

            //Only keep the fields required for searching, to keep the cache entry small
            $catalog[(string) $product['product_id']] = array_intersect_key($product, array_flip(self::CATALOG_FIELDS));
        }

        if ($catalog === []) {
            throw new \RuntimeException('The Adafruit product catalog was empty!');
        }

        $item->set($catalog);
        $item->expiresAfter(self::CATALOG_CACHE_TTL);
        $this->partInfoCache->save($item);

        return $catalog;
    }

    public function searchByKeyword(string $keyword, array $options = []): array
    {
        $keyword = trim($keyword);
        if ($keyword === '') {
            return [];
        }

        $catalog = $this->getCatalog((bool) ($options[self::OPTION_NO_CACHE] ?? false));

        //A product ID (like "4062", "#4062", "PID 4062" or "ADA4062") or a product URL identifies the product directly
        $pid = $this->getIDFromURL($keyword);
        if ($pid === null && preg_match('/^(?:ADA|PID)?[\s:#-]*(\d+)$/i', $keyword, $matches)) {
            $pid = ltrim($matches[1], '0');
        }

        $normalizedKeyword = $this->normalize($keyword);
        $tokens = $normalizedKeyword === '' ? [] : explode(' ', $normalizedKeyword);

        $scores = [];
        foreach ($catalog as $id => $product) {
            $id = (string) $id;
            $score = 0;

            if ($id === $pid) {
                $score += 1000;
            }

            $name = $this->normalize((string) ($product['product_name'] ?? ''));
            $haystack = $name . ' ' . $this->normalize(($product['product_model'] ?? '') . ' '
                . ($product['product_mpn'] ?? '') . ' ' . ($product['product_manufacturer'] ?? ''));

            //All words of the keyword must occur
            $allFound = $tokens !== [];
            foreach ($tokens as $token) {
                if (!str_contains($haystack, $token)) {
                    $allFound = false;
                    break;
                }
            }

            if ($allFound) {
                $score += 10;
                //Many product names start with "Adafruit", which should not be required to type
                $shortName = str_starts_with($name, 'adafruit ') ? substr($name, 9) : $name;
                if ($name === $normalizedKeyword || $shortName === $normalizedKeyword) {
                    $score += 100;
                } elseif (str_starts_with($name, $normalizedKeyword) || str_starts_with($shortName, $normalizedKeyword)) {
                    $score += 50;
                } elseif (str_contains($name, $normalizedKeyword)) {
                    $score += 25;
                }
            }

            if ($score === 0) {
                continue;
            }

            //Prefer products which are still available
            if (($product['discontinue_status'] ?? 'None') !== 'Discontinued') {
                $score += 5;
            }

            $scores[$id] = $score;
        }

        //Best matches first, for equal scores the newer products (higher ID) first
        uksort($scores, static fn($a, $b) => [$scores[$b], (int) $b] <=> [$scores[$a], (int) $a]);

        $results = [];
        foreach (array_slice(array_keys($scores), 0, self::MAX_SEARCH_RESULTS) as $id) {
            $id = (string) $id;
            $product = $catalog[$id];
            $results[] = new SearchResultDTO(
                provider_key: self::PROVIDER_KEY,
                provider_id: $id,
                name: (string) $product['product_name'],
                description: trim((string) ($product['product_model'] ?? '')),
                manufacturer: $this->getManufacturer($product),
                mpn: $this->emptyToNull($product['product_mpn'] ?? null),
                preview_image_url: $this->emptyToNull($product['product_image'] ?? null),
                manufacturing_status: $this->getManufacturingStatus($product),
                provider_url: self::PRODUCT_PAGE_URL . $id,
            );
        }

        return $results;
    }

    public function getDetails(string $id, array $options = []): PartDetailDTO
    {
        //Ensure that $id is numeric
        if (!ctype_digit($id)) {
            throw new \InvalidArgumentException("The id must be a numeric Adafruit product ID!");
        }

        $product = $this->request('GET', self::PRODUCT_API_URL . $id)->toArray();
        if (!isset($product['product_id'], $product['product_name'])) {
            throw new \RuntimeException("Product with ID $id not found");
        }

        $id = (string) $product['product_id'];
        $productUrl = self::PRODUCT_PAGE_URL . $id;
        $descriptionHtml = trim((string) ($product['products_description'] ?? ''));

        $category = null;
        $images = [];
        $parameters = [];
        $datasheets = $this->parseDocumentLinks($descriptionHtml);

        if ($this->settings->fetchProductPage) {
            try {
                $page = new Crawler($this->request('GET', $productUrl)->getContent());

                $category = $this->parseCategory($page);
                $images = $this->parseImages($page);
                $parameters = $this->parseParameters($page);
                $datasheets = [
                    ...$this->parseLearnGuides($page),
                    ...$datasheets,
                    ...$this->parseDocumentLinks($page->filter('#tab-technical-details-content')->html('')),
                ];
            } catch (HttpExceptionInterface|\RuntimeException) {
                //The product page is only an addition, continue with the data of the API, if it is not available
                //(or the website refused the request)
            }
        }

        $previewImage = $this->emptyToNull($product['product_image'] ?? null);
        if ($images === [] && $previewImage !== null) {
            $images[] = new FileDTO($previewImage, $this->emptyToNull($product['product_image_alt'] ?? null));
        }

        $manufacturer = $this->getManufacturer($product);

        return new PartDetailDTO(
            provider_key: self::PROVIDER_KEY,
            provider_id: $id,
            name: (string) $product['product_name'],
            description: $this->getShortDescription($product, $descriptionHtml),
            category: $category,
            manufacturer: $manufacturer,
            mpn: $this->emptyToNull($product['product_mpn'] ?? null),
            preview_image_url: $previewImage,
            manufacturing_status: $this->getManufacturingStatus($product),
            provider_url: $productUrl,
            notes: $descriptionHtml,
            datasheets: $this->uniqueFiles($datasheets),
            images: $this->uniqueFiles($images),
            parameters: [...$parameters, ...$this->getCatalogParameters($product)],
            vendor_infos: [new PurchaseInfoDTO(
                distributor_name: self::DISTRIBUTOR_NAME,
                order_number: $id,
                prices: $this->getPrices($product),
                product_url: $productUrl,
                prices_include_vat: false,
                available_amount: $this->getAvailableAmount($product),
            )],
            manufacturer_product_url: $manufacturer === 'Adafruit' ? $productUrl : null,
        );
    }

    /**
     * Normalizes the given string for searching: lowercase and only letters and digits, separated by single spaces.
     */
    private function normalize(string $text): string
    {
        return trim(preg_replace('/[^\p{L}\p{N}]+/u', ' ', mb_strtolower($text)) ?? '');
    }

    private function emptyToNull(mixed $value): ?string
    {
        if (!is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }

    /**
     * The catalog only names the manufacturer for a part of the products. The others are mostly generic products
     * sold under Adafruit's own product number, so Adafruit is used for them.
     */
    private function getManufacturer(array $product): string
    {
        return $this->emptyToNull($product['product_manufacturer'] ?? null) ?? 'Adafruit';
    }

    private function getManufacturingStatus(array $product): ManufacturingStatus
    {
        if ((string) ($product['products_coming_soon'] ?? '0') === '1') {
            return ManufacturingStatus::ANNOUNCED;
        }

        return match ($product['discontinue_status'] ?? null) {
            'None' => ManufacturingStatus::ACTIVE,
            //The product is sold until the remaining stock is gone
            'Pending' => ManufacturingStatus::EOL,
            'Discontinued' => ManufacturingStatus::DISCONTINUED,
            default => ManufacturingStatus::NOT_SET,
        };
    }

    /**
     * Returns the stock level, or null if it is unknown.
     * The API either gives the number of pieces, the text "in stock" (without a number) or a negative number as
     * marker for products which can not be ordered (out of stock, discontinued, ...).
     */
    private function getAvailableAmount(array $product): ?float
    {
        $stock = $product['product_stock'] ?? null;
        if (!is_numeric($stock)) {
            return null;
        }

        return max(0.0, (float) $stock);
    }

    /**
     * @return PriceDTO[]
     */
    private function getPrices(array $product): array
    {
        $prices = [];
        foreach ($product['discount_pricing'] ?? [] as $discount) {
            if (!is_array($discount) || !is_numeric($discount['discounted_price'] ?? null)) {
                continue;
            }

            $minQty = max(1.0, (float) ($discount['min_qty'] ?? 1));
            $prices[(string) $minQty] = new PriceDTO(
                minimum_discount_amount: $minQty,
                price: (string) $discount['discounted_price'],
                currency_iso_code: self::CURRENCY,
                includes_tax: false,
            );
        }

        //Products without quantity discounts only have a single price (which can be reduced by a sale)
        if ($prices === []) {
            $price = $product['product_sale_price'] ?? $product['product_price'] ?? null;
            if (is_numeric($price)) {
                $prices[] = new PriceDTO(
                    minimum_discount_amount: 1.0,
                    price: (string) $price,
                    currency_iso_code: self::CURRENCY,
                    includes_tax: false,
                );
            }
        }

        return array_values($prices);
    }

    /**
     * Returns a short one line description: The first sentence of the product description, with the model/variant
     * of the product in front, if the API gives one.
     */
    private function getShortDescription(array $product, string $descriptionHtml): string
    {
        $text = '';
        if ($descriptionHtml !== '') {
            //Only look at the first paragraph
            $firstParagraph = preg_split('/<\/p>|<br\s*\/?>\s*<br\s*\/?>/i', $descriptionHtml, 2)[0];
            $text = $this->htmlToText($firstParagraph);

            //Cut after the first sentence
            if (preg_match('/^(.{20,}?[.!?])\s/su', $text . ' ', $matches)) {
                $text = $matches[1];
            }
            if (mb_strlen($text) > 250) {
                $text = mb_substr($text, 0, 249) . '…';
            }
        }

        $model = trim((string) ($product['product_model'] ?? ''));
        if ($model === '') {
            return $text;
        }

        return $text === '' ? $model : $model . ' - ' . $text;
    }

    private function htmlToText(string $html): string
    {
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        //Collapse all whitespace (including non-breaking spaces)
        return trim(preg_replace('/[\s\x{00A0}]+/u', ' ', $text) ?? '');
    }

    /**
     * Parameters which the API gives for every product
     * @return ParameterDTO[]
     */
    private function getCatalogParameters(array $product): array
    {
        $parameters = [];

        $coo = $this->emptyToNull($product['products_coo'] ?? null);
        if ($coo !== null) {
            $parameters[] = new ParameterDTO(name: 'Country of origin', value_text: $coo);
        }

        $hts = $this->emptyToNull($product['products_hts'] ?? null);
        if ($hts !== null) {
            $parameters[] = new ParameterDTO(name: 'HTS code', value_text: $hts);
        }

        if (isset($product['products_rohs'])) {
            $parameters[] = new ParameterDTO(name: 'RoHS compliant', value_text: (string) $product['products_rohs'] === '1' ? 'Yes' : 'No');
        }

        return $parameters;
    }

    private function absoluteUrl(string $url): ?string
    {
        $url = trim($url);
        if (str_starts_with($url, '//')) {
            return 'https:' . $url;
        }
        if (str_starts_with($url, '/')) {
            return self::BASE_URL . $url;
        }
        if (preg_match('/^https?:\/\//i', $url)) {
            return $url;
        }

        return null;
    }

    /**
     * Removes files with an URL which occurred already
     * @param  FileDTO[]  $files
     * @return FileDTO[]
     */
    private function uniqueFiles(array $files): array
    {
        $unique = [];
        foreach ($files as $file) {
            $unique[$file->url] ??= $file;
        }

        return array_values($unique);
    }

    /**
     * Finds the links to documents (datasheets, schematics, CAD files, ...) in the given HTML fragment.
     * @return FileDTO[]
     */
    private function parseDocumentLinks(string $html): array
    {
        if (trim($html) === '') {
            return [];
        }

        $files = [];
        //The meta tag is required, as the crawler assumes ISO-8859-1 for fragments otherwise
        $dom = new Crawler('<html><head><meta charset="utf-8"></head><body>' . $html . '</body></html>');
        $dom->filter('a[href]')->each(function (Crawler $node) use (&$files) {
            $url = $this->absoluteUrl((string) $node->attr('href'));
            if ($url === null) {
                return;
            }

            $text = $this->htmlToText($node->html(''));
            $path = (string) parse_url($url, PHP_URL_PATH);
            if (preg_match(self::DOCUMENT_EXTENSION_REGEX, $path)) {
                $files[] = new FileDTO($url, $text !== '' ? $text : basename($path));
            } elseif ($text !== '' && mb_strlen($text) < 80 && preg_match(self::DOCUMENT_TEXT_REGEX, $text)) {
                $files[] = new FileDTO($url, $text);
            }
        });

        return $files;
    }

    /**
     * The learn guides are web pages (the documentation of most Adafruit products), not files which can be downloaded
     * @return FileDTO[]
     */
    private function parseLearnGuides(Crawler $page): array
    {
        $guides = [];
        $page->filter('.product-learn-guide-title a[href]')->each(function (Crawler $node) use (&$guides) {
            $url = $this->absoluteUrl((string) $node->attr('href'));
            if ($url === null) {
                return;
            }

            $title = preg_replace('/^Primary Guide:\s*/i', '', $this->htmlToText($node->html('')));
            $guides[] = new FileDTO($url, 'Learn guide: ' . $title, downloadable: false);
        });

        return $guides;
    }

    /**
     * @return FileDTO[]
     */
    private function parseImages(Crawler $page): array
    {
        $images = [];
        //For videos this gives the poster image
        $page->filter('.gallery-slides .gallery-slide img[src]')->each(function (Crawler $node) use (&$images) {
            //Slides with YouTube videos only have the thumbnail of the video, which is no picture of the product
            if ($node->closest('.gallery-slide-youtube') !== null) {
                return;
            }

            $url = $this->absoluteUrl((string) $node->attr('src'));
            if ($url !== null) {
                $images[] = new FileDTO($url, $this->emptyToNull($node->attr('alt')));
            }
        });

        return $images;
    }

    private function parseCategory(Crawler $page): ?string
    {
        $categories = [];
        $page->filter('nav.breadcrumbs a')->each(function (Crawler $node) use (&$categories) {
            $categories[] = $node->text();
        });

        return $categories === [] ? null : implode(' -> ', $categories);
    }

    /**
     * The technical details are free text. Take the list entries which look like "Name: value" as parameters.
     * @return ParameterDTO[]
     */
    private function parseParameters(Crawler $page): array
    {
        $parameters = [];
        $page->filter('#tab-technical-details-content li')->each(function (Crawler $node) use (&$parameters) {
            $text = $node->text();
            if (preg_match('/^([^:]{2,60}):\s+(\S.{0,250})$/su', $text, $matches)) {
                $parameters[] = ParameterDTO::parseValueIncludingUnit(name: trim($matches[1]), value: trim($matches[2]));
            }
        });

        return $parameters;
    }

    public function getHandledDomains(): array
    {
        return ['adafruit.com'];
    }

    public function getIDFromURL(string $url): ?string
    {
        //URLs like https://www.adafruit.com/product/4062 or the old form https://www.adafruit.com/products/4062
        $matches = [];
        if (preg_match('/adafruit\.com\/products?\/(\d+)/i', $url, $matches)) {
            return $matches[1];
        }

        return null;
    }
}
