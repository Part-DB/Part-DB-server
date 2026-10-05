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
use App\Settings\InfoProviderSystem\SparkFunSettings;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Retrieves product information from sparkfun.com.
 * The shop is a Magento store, whose public GraphQL endpoint supplies the structured data (name, prices, images, etc.).
 * The documents and the feature list are only shown in tabs of the product page, so they are read from there.
 *
 * All requests to the shop (GraphQL and product pages) are spaced by a configurable delay, and after the shop has
 * refused a request, no requests are sent at all for some time.
 */
class SparkFunProvider implements InfoProviderInterface, URLHandlerInfoProviderInterface
{
    public const PROVIDER_KEY = 'sparkfun';

    public const BASE_URL = 'https://www.sparkfun.com';
    public const GRAPHQL_URL = self::BASE_URL . '/graphql';

    public const DISTRIBUTOR_NAME = 'SparkFun';

    /** @var int The maximum number of results which are returned by a keyword search */
    private const SEARCH_LIMIT = 20;

    /** @var float The shop gives the weight of the products in pounds */
    private const GRAMS_PER_POUND = 453.59237;

    /** @var int[] The HTTP status codes with which sparkfun.com tells us that it does not want our requests (anymore) */
    private const REFUSED_STATUS_CODES = [403, 429, 503];
    /** @var int The time (in seconds) no requests are sent to sparkfun.com, after it has refused one */
    private const PAUSE_DURATION = 3600;

    private const CACHE_KEY_PAUSED = 'sparkfun_paused';
    private const CACHE_KEY_NEXT_REQUEST = 'sparkfun_next_request';

    private const SEARCH_FIELDS = <<<'GRAPHQL'
        sku name url_key url_suffix stock_status
        small_image { url }
        short_description { html }
        categories { name level breadcrumbs { category_name } }
        GRAPHQL;

    private const DETAIL_FIELDS = self::SEARCH_FIELDS . <<<'GRAPHQL'

        image { url }
        description { html }
        ... on PhysicalProductInterface { weight }
        price_range { minimum_price { final_price { value currency } } }
        price_tiers { quantity final_price { value currency } }
        media_gallery { __typename url label position disabled }
        GRAPHQL;

    public function __construct(private readonly HttpClientInterface $client,
        private readonly SparkFunSettings $settings,
        private readonly CacheItemPoolInterface $partInfoCache,
    )
    {
    }

    public function getProviderInfo(): ProviderInfoDTO
    {
        return new ProviderInfoDTO(
            key: self::PROVIDER_KEY,
            name: 'SparkFun',
            description: 'Retrieves part information from sparkfun.com',
            url: 'https://www.sparkfun.com/',
            disabledHelp: 'Enable the provider in provider settings',
            settingsClass: SparkFunSettings::class,
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

    /**
     * Sends a request to sparkfun.com. Every request of this provider (to the API and to the product pages) has to use
     * this method: It spaces the requests, and once the shop has refused a request, it does not send any further
     * requests for some time.
     */
    private function request(string $method, string $url, array $options = []): ResponseInterface
    {
        $paused = $this->partInfoCache->getItem(self::CACHE_KEY_PAUSED);
        if ($paused->isHit() && is_array($paused->get()) && ($paused->get()['until'] ?? 0) > time()) {
            throw new \RuntimeException(sprintf(
                'The SparkFun provider is paused until %s, because sparkfun.com refused a request (%s). No requests are sent to it until then.',
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
                'sparkfun.com refused the request (HTTP status %d). The SparkFun provider is paused until %s, no requests are sent to it until then.',
                $status, date('Y-m-d H:i:s T', $until)
            ));
        }

        return $response;
    }

    /**
     * Sleeps until the configured delay has passed since the last request to sparkfun.com. The time of the next
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
     * Runs the given GraphQL query against the shop and returns the "data" part of the response.
     * @param  string  $query
     * @param  array<string, mixed>  $variables
     * @return array
     */
    private function queryGraphQL(string $query, array $variables): array
    {
        $response = $this->request('POST', self::GRAPHQL_URL, [
            'headers' => [
                'Accept' => 'application/json',
            ],
            'json' => [
                'query' => $query,
                'variables' => $variables,
            ],
        ]);

        $data = $response->toArray();

        if (!isset($data['data'])) {
            throw new \RuntimeException('SparkFun GraphQL request failed: ' . ($data['errors'][0]['message'] ?? 'unknown error'));
        }

        return $data['data'];
    }

    public function searchByKeyword(string $keyword, array $options = []): array
    {
        $keyword = trim($keyword);
        if ($keyword === '') {
            return [];
        }

        $data = $this->queryGraphQL(
            'query ($search: String!, $pageSize: Int!) { products(search: $search, pageSize: $pageSize) { items { ' . self::SEARCH_FIELDS . ' } } }',
            ['search' => $keyword, 'pageSize' => self::SEARCH_LIMIT]
        );

        $items = array_values(array_filter($data['products']['items'] ?? [], is_array(...)));

        //The search is fuzzy (a SKU search can list a neighbouring SKU first), so put a product with exactly the searched SKU on top
        usort($items, fn(array $a, array $b): int => $this->skuMatches($b['sku'], $keyword) <=> $this->skuMatches($a['sku'], $keyword));

        return array_map(fn(array $item): SearchResultDTO => new SearchResultDTO(
            provider_key: self::PROVIDER_KEY,
            provider_id: $item['sku'],
            name: $this->htmlToText($item['name']),
            description: $this->htmlToText($item['short_description']['html'] ?? ''),
            category: $this->getCategory($item),
            manufacturer: self::DISTRIBUTOR_NAME,
            mpn: $item['sku'],
            preview_image_url: $item['small_image']['url'] ?? null,
            manufacturing_status: $this->getManufacturingStatus($item),
            provider_url: $this->getProductURL($item),
        ), $items);
    }

    public function getDetails(string $id, array $options = []): PartDetailDTO
    {
        $id = trim($id);
        if ($id === '') {
            throw new \InvalidArgumentException('The id must not be empty!');
        }

        $product = $this->findProduct($id);
        if ($product === null) {
            throw new \RuntimeException("Could not find a SparkFun product for the id '$id'!");
        }

        $sku = $product['sku'];
        $productUrl = $this->getProductURL($product);
        $description = $product['description']['html'] ?? '';

        //The documents and features tab are not part of the GraphQL data, so we have to take them from the product page
        $page = $this->getProductPage($productUrl);
        $features = $page !== null ? $this->parseFeatures($page) : [];

        $datasheets = $this->parseDocuments(new Crawler('<html><body>' . $description . '</body></html>'), 'a', true);
        if ($page !== null) {
            //The documents of the product page are the better maintained ones, so list them first
            $datasheets = [...$this->parseDocuments($page, '#content-documentation a, a.document-item', false), ...$datasheets];
        }

        return new PartDetailDTO(
            provider_key: self::PROVIDER_KEY,
            provider_id: $sku,
            name: $this->htmlToText($product['name']),
            description: $this->htmlToText($product['short_description']['html'] ?? ''),
            category: $this->getCategory($product),
            manufacturer: self::DISTRIBUTOR_NAME,
            mpn: $sku,
            preview_image_url: $product['image']['url'] ?? $product['small_image']['url'] ?? null,
            manufacturing_status: $this->getManufacturingStatus($product),
            provider_url: $productUrl,
            notes: $this->buildNotes($description, $features),
            datasheets: $this->uniqueFiles($datasheets),
            images: $this->getImages($product),
            parameters: $this->featuresToParameters($features),
            vendor_infos: [new PurchaseInfoDTO(self::DISTRIBUTOR_NAME, $sku, $this->getPrices($product), $productUrl)],
            mass: isset($product['weight']) && $product['weight'] > 0 ? round($product['weight'] * self::GRAMS_PER_POUND, 2) : null,
        );
    }

    /**
     * Finds the product for the given ID, which can be a SKU (DEV-13975), only the number of a SKU (13975) or the
     * URL key of the product page (sparkfun-redboard-programmed-with-arduino).
     * @param  string  $id
     * @return array|null The product data, or null if no product was found
     */
    private function findProduct(string $id): ?array
    {
        //The number alone is not a valid SKU, so we have to use the search and only accept a product with this number
        if (ctype_digit($id)) {
            $data = $this->queryGraphQL(
                'query ($search: String!, $pageSize: Int!) { products(search: $search, pageSize: $pageSize) { items { ' . self::DETAIL_FIELDS . ' } } }',
                //The numbers in the SKUs are padded to five digits (PRT-08809)
                ['search' => str_pad($id, 5, '0', STR_PAD_LEFT), 'pageSize' => 5]
            );

            foreach ($data['products']['items'] ?? [] as $item) {
                if ($item !== null && $this->skuMatches($item['sku'], $id)) {
                    return $item;
                }
            }

            return null;
        }

        $data = $this->queryGraphQL(
            'query ($id: String!) {'
            . ' bySku: products(filter: {sku: {eq: $id}}) { items { ' . self::DETAIL_FIELDS . ' } }'
            . ' byUrlKey: products(filter: {url_key: {eq: $id}}) { items { ' . self::DETAIL_FIELDS . ' } }'
            . ' }',
            ['id' => $id]
        );

        return $data['bySku']['items'][0] ?? $data['byUrlKey']['items'][0] ?? null;
    }

    /**
     * Checks if the given SKU is the searched one. The search term can also be just the number of the SKU
     * (13975 or 8809 for DEV-13975 or PRT-08809).
     * @param  string  $sku
     * @param  string  $search
     * @return bool
     */
    private function skuMatches(string $sku, string $search): bool
    {
        if (strcasecmp($sku, $search) === 0) {
            return true;
        }

        return ctype_digit($search) && preg_match('/^[A-Za-z]+-0*' . ltrim($search, '0') . '$/', $sku) === 1;
    }

    private function getProductURL(array $product): string
    {
        return self::BASE_URL . '/' . $product['url_key'] . ($product['url_suffix'] ?? '.html');
    }

    private function getManufacturingStatus(array $product): ?ManufacturingStatus
    {
        //We can not tell whether a product is retired or just temporarily out of stock
        return ($product['stock_status'] ?? null) === 'IN_STOCK' ? ManufacturingStatus::ACTIVE : null;
    }

    /**
     * Returns the path of the deepest category the product is assigned to.
     * @param  array  $product
     * @return string|null
     */
    private function getCategory(array $product): ?string
    {
        $deepest = null;
        foreach ($product['categories'] ?? [] as $category) {
            if ($deepest === null || ($category['level'] ?? 0) > ($deepest['level'] ?? 0)) {
                $deepest = $category;
            }
        }

        if ($deepest === null) {
            return null;
        }

        $path = array_column($deepest['breadcrumbs'] ?? [], 'category_name');
        $path[] = $deepest['name'];

        return implode(' -> ', $path);
    }

    /**
     * @param  array  $product
     * @return PriceDTO[]
     */
    private function getPrices(array $product): array
    {
        $prices = [];

        $price = $product['price_range']['minimum_price']['final_price'] ?? null;
        if (isset($price['value'])) {
            $prices[] = new PriceDTO(1.0, (string) $price['value'], $price['currency'] ?? 'USD', includes_tax: false);
        }

        //Quantity discounts
        foreach ($product['price_tiers'] ?? [] as $tier) {
            if (!isset($tier['quantity'], $tier['final_price']['value'])) {
                continue;
            }

            $prices[] = new PriceDTO((float) $tier['quantity'], (string) $tier['final_price']['value'],
                $tier['final_price']['currency'] ?? 'USD', includes_tax: false);
        }

        return $prices;
    }

    /**
     * @param  array  $product
     * @return FileDTO[]
     */
    private function getImages(array $product): array
    {
        //The gallery contains also videos, where the URL is just the preview picture of the video
        $gallery = array_filter($product['media_gallery'] ?? [],
            static fn(array $media): bool => ($media['__typename'] ?? 'ProductImage') === 'ProductImage'
                && !($media['disabled'] ?? false) && !empty($media['url']));
        usort($gallery, static fn(array $a, array $b): int => ($a['position'] ?? 0) <=> ($b['position'] ?? 0));

        $images = array_map(fn(array $media): FileDTO => new FileDTO($media['url'],
            isset($media['label']) ? $this->htmlToText($media['label']) : null), $gallery);

        //Ensure that the main image is always part of the images
        if ($images === [] && isset($product['image']['url'])) {
            $images[] = new FileDTO($product['image']['url'], $this->htmlToText($product['name']));
        }

        return $this->uniqueFiles($images);
    }

    private function getProductPage(string $url): ?Crawler
    {
        try {
            return new Crawler($this->request('GET', $url)->getContent());
        } catch (HttpExceptionInterface|\RuntimeException) {
            //The page gives us only additional infos, so we can live without it (also if the shop refused the request)
            return null;
        }
    }

    /**
     * Returns the entries of the feature list of the product page
     * @param  Crawler  $page
     * @return string[]
     */
    private function parseFeatures(Crawler $page): array
    {
        $features = $page->filter('#content-features li')->each(static fn(Crawler $node): string => trim($node->text()));

        return array_values(array_filter($features, static fn(string $feature): bool => $feature !== ''));
    }

    /**
     * Extracts the documents (datasheets, schematics, guides, design files, etc.) linked by the given elements.
     * @param  Crawler  $dom
     * @param  string  $selector The CSS selector of the links to look at
     * @param  bool  $onlyDocuments If true, only links which look like a document are returned. This is required for the
     * description, which also links to other websites.
     * @return FileDTO[]
     */
    private function parseDocuments(Crawler $dom, string $selector, bool $onlyDocuments): array
    {
        $documents = [];

        $dom->filter($selector)->each(function (Crawler $node) use (&$documents, $onlyDocuments): void {
            $url = $this->normalizeURL($node->attr('href') ?? '');
            $name = trim($node->text());

            if ($url === null || $name === '') {
                return;
            }

            //Videos and the california proposition 65 warning are no documents
            if (preg_match('#youtube\.com|youtu\.be|vimeo\.com|sparkfun\.com/videos?\b|p65warnings\.ca\.gov#i', $url)) {
                return;
            }

            //Links to other pages of the shop (other products, buying guides, etc.) are no documents either
            if (strcasecmp((string) parse_url($url, PHP_URL_HOST), 'www.sparkfun.com') === 0 && !$this->isFile($url)) {
                return;
            }

            if ($onlyDocuments && !$this->looksLikeDocument($url, $name)) {
                return;
            }

            //Use the text of the list entry as name, if it gives more context: "Datasheet (BSS138)" instead of "Datasheet"
            $parent = $node->ancestors()->first();
            if ($parent->count() > 0 && $parent->nodeName() === 'li') {
                $parentText = trim($parent->text());
                if ($parent->filter('a')->count() === 1 && mb_strlen($parentText) <= 100) {
                    $name = $parentText;
                }
            }

            $documents[] = new FileDTO($url, $name);
        });

        return $documents;
    }

    private function isFile(string $url): bool
    {
        return preg_match('/\.(pdf|zip|step|stp|stl|dxf|brd|sch|fzz|kicad_pcb|kicad_sch)$/i', (string) parse_url($url, PHP_URL_PATH)) === 1;
    }

    private function looksLikeDocument(string $url, string $name): bool
    {
        if ($this->isFile($url)) {
            return true;
        }

        if (preg_match('#(^|\.)(github\.com|learn\.sparkfun\.com|docs\.sparkfun\.com)$#i', (string) parse_url($url, PHP_URL_HOST))) {
            return true;
        }

        return preg_match('/datasheet|data sheet|manual|guide|getting started|schematic|drawing|dimension|3d model|'
            . 'eagle|kicad|gerber|fritzing|specification|pinout|firmware|library|github|example code/i', $name) === 1;
    }

    /**
     * Makes the given link absolute. Returns null if it is not a http(s) link.
     * @param  string  $url
     * @return string|null
     */
    private function normalizeURL(string $url): ?string
    {
        $url = trim($url);

        if (str_starts_with($url, '//')) {
            $url = 'https:' . $url;
        } elseif (str_starts_with($url, '/')) {
            $url = self::BASE_URL . $url;
        }

        if (preg_match('#^https?://#i', $url) !== 1) {
            return null;
        }

        //All sparkfun hosts support HTTPS, but older descriptions still link to HTTP
        return preg_replace('#^http://([a-z0-9.-]+\.)?sparkfun\.com(?=/|$)#i', 'https://$1sparkfun.com', $url);
    }

    /**
     * Removes the files with a URL that already occurred earlier in the list
     * @param  FileDTO[]  $files
     * @return FileDTO[]
     */
    private function uniqueFiles(array $files): array
    {
        $unique = [];
        foreach ($files as $file) {
            $key = rtrim((string) preg_replace('#^https?://#i', '', $file->url), '/');
            $unique[$key] ??= $file;
        }

        return array_values($unique);
    }

    /**
     * Features like "Operating Voltage: 3.3V" can be used as parameters, the others are only put in the notes.
     * @param  string[]  $features
     * @return ParameterDTO[]
     */
    private function featuresToParameters(array $features): array
    {
        $parameters = [];
        foreach ($features as $feature) {
            if (preg_match('/^([^:]{2,50}):\s+(\S.{0,150})$/u', $feature, $matches) === 1) {
                $parameters[] = ParameterDTO::parseValueIncludingUnit(name: trim($matches[1]), value: trim($matches[2]));
            }
        }

        return $parameters;
    }

    /**
     * @param  string  $description The HTML of the product description
     * @param  string[]  $features
     * @return string
     */
    private function buildNotes(string $description, array $features): string
    {
        $notes = trim($description);

        if ($features !== []) {
            $notes .= '<p><strong>Features:</strong></p><ul>';
            foreach ($features as $feature) {
                $notes .= '<li>' . htmlspecialchars($feature) . '</li>';
            }
            $notes .= '</ul>';
        }

        return $notes;
    }

    /**
     * Converts the HTML to plain text. The shop also returns the product names with HTML entities.
     */
    private function htmlToText(string $html): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5)));
    }

    public function getHandledDomains(): array
    {
        return ['sparkfun.com'];
    }

    public function getIDFromURL(string $url): ?string
    {
        $path = (string) parse_url($url, PHP_URL_PATH);

        //Old style URL like https://www.sparkfun.com/products/13975
        if (preg_match('#^/products/(\d+)#', $path, $matches) === 1) {
            return $matches[1];
        }

        //New style URL like https://www.sparkfun.com/sparkfun-redboard-programmed-with-arduino.html
        if (preg_match('#^/([a-z0-9][a-z0-9_-]*)\.html$#i', $path, $matches) === 1) {
            return $matches[1];
        }

        return null;
    }
}
