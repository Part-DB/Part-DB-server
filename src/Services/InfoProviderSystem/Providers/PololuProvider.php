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
use App\Settings\InfoProviderSystem\PololuSettings;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Retrieves part information from pololu.com by scraping the product pages.
 *
 * The provider id is the Pololu item number, which is also part of the product URL (/product/<item number>).
 * The specifications and the downloadable files are on separate tabs of a product (/product/<n>/specs and
 * /product/<n>/resources), which are only requested if the product page says that they are not empty.
 */
class PololuProvider implements InfoProviderInterface, URLHandlerInfoProviderInterface
{
    use ThrottledRequestTrait;

    public const PROVIDER_KEY = 'pololu';

    public const DISTRIBUTOR_NAME = 'Pololu';

    private const BASE_URL = 'https://www.pololu.com';

    /** @var string All prices on pololu.com are in US dollars */
    private const CURRENCY = 'USD';

    /** @var string Links outside the file downloads list are only used, if they point to a file with one of these extensions */
    private const FILE_EXTENSIONS_REGEX = '/\.(pdf|zip|step|stp|igs|iges|stl|dxf|dwg|sldprt|easm|eprt|brd|sch|hex|bin)$/i';

    public function __construct(private readonly HttpClientInterface $client,
        private readonly PololuSettings $settings,
        private readonly CacheItemPoolInterface $partInfoCache,
    )
    {
    }

    public function getProviderInfo(): ProviderInfoDTO
    {
        return new ProviderInfoDTO(
            key: self::PROVIDER_KEY,
            name: 'Pololu',
            description: 'Webscraping from pololu.com to get part information',
            url: 'https://www.pololu.com/',
            disabledHelp: 'Enable the provider in provider settings',
            settingsClass: PololuSettings::class,
            capabilities: [
                ProviderCapabilities::BASIC,
                ProviderCapabilities::PICTURE,
                ProviderCapabilities::PRICE,
                ProviderCapabilities::DATASHEET,
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
     * Requests the given page of pololu.com (throttled, see ThrottledRequestTrait).
     * @param  string  $path The path of the page, starting with a slash
     * @param  array<string, string>  $query
     */
    private function request(string $path, array $query = []): ResponseInterface
    {
        return $this->throttledRequest('GET', self::BASE_URL . $path, [
            'query' => $query,
        ]);
    }

    private function getRequestDelay(): int
    {
        return $this->settings->requestDelay;
    }

    public function searchByKeyword(string $keyword, array $options = []): array
    {
        $response = $this->request('/search', [
            'query' => $keyword,
            '[search_type]' => 'products',
        ]);

        $content = $response->getContent();

        //If we were redirected to a product page, then just return the single item
        if (preg_match('#/product/(\d+)#', (string) $response->getInfo('url'), $matches)) {
            return [$this->parseProductPage($content, $matches[1])];
        }

        $dom = new Crawler($content);

        $results = [];

        //Each result is a dt (picture and title) followed by a dd (text excerpt)
        $dom->filter('dl#search > dt')->each(function (Crawler $node) use (&$results) {
            $link = $node->filter('a[href*="/product/"]')->last();
            if ($link->count() === 0 || !preg_match('#/product/(\d+)#', $link->attr('href') ?? '', $matches)) {
                return;
            }
            $id = $matches[1];

            $image = $node->filter('img');
            $excerpt = $node->nextAll()->first();

            $results[] = new SearchResultDTO(
                provider_key: self::PROVIDER_KEY,
                provider_id: $id,
                //The title has the format "Pololu item 2130: DRV8833 Dual Motor Driver Carrier"
                name: preg_replace('/^Pololu item\s*#?\s*\d+:\s*/', '', $link->text()),
                description: $excerpt->count() > 0 && $excerpt->nodeName() === 'dd' ? $excerpt->text() : '',
                preview_image_url: $image->count() > 0 ? $image->attr('src') : null,
                provider_url: $this->getProductURL($id),
            );
        });

        return $results;
    }

    public function getDetails(string $id, array $options = []): PartDetailDTO
    {
        //Ensure that $id is an item number
        if (!ctype_digit($id)) {
            throw new \InvalidArgumentException("The id must be a Pololu item number!");
        }

        $content = $this->request('/product/' . $id)->getContent();
        $dom = new Crawler($content);

        //The specifications and the files are on their own pages. The tabs of an empty page are not linked.
        $specs = null;
        if ($dom->filter('table.tabs a[href$="/specs"]')->count() > 0) {
            $specs = $this->request('/product/' . $id . '/specs')->getContent();
        }

        $resources = null;
        if ($dom->filter('table.tabs a[href$="/resources"]')->count() > 0) {
            $resources = $this->request('/product/' . $id . '/resources')->getContent();
        }

        return $this->parseProductPage($content, $id, $specs, $resources);
    }

    private function getProductURL(string $id): string
    {
        return self::BASE_URL . '/product/' . $id;
    }

    /**
     * @param  string  $content The HTML of the product page
     * @param  string  $id The Pololu item number
     * @param  string|null  $specs The HTML of the specs tab of the product, if available
     * @param  string|null  $resources The HTML of the resources tab of the product, if available
     */
    private function parseProductPage(string $content, string $id, ?string $specs = null, ?string $resources = null): PartDetailDTO
    {
        $dom = new Crawler($content);

        $productPageUrl = $this->getProductURL($id);

        $stock = $dom->filter('[data-available-stock]');

        $purchaseInfo = new PurchaseInfoDTO(
            distributor_name: self::DISTRIBUTOR_NAME,
            order_number: $id,
            prices: $this->parsePrices($dom),
            product_url: $productPageUrl,
            available_amount: $stock->count() > 0 && is_numeric($stock->attr('data-available-stock')) ? (float) $stock->attr('data-available-stock') : null,
        );

        $brand = $this->parseOrderFormValue($dom, 'Brand');
        //Pololu sells also products of other brands, for which the item number is no manufacturer part number
        $isPololuProduct = $brand === null || strcasecmp($brand, 'Pololu') === 0;

        $images = $this->parseImages($dom);
        $parameters = $specs !== null ? $this->parseParameters(new Crawler($specs)) : [];

        return new PartDetailDTO(
            provider_key: self::PROVIDER_KEY,
            provider_id: $id,
            name: $dom->filter('h1#page_title')->text(),
            description: $dom->filter('div#short_description')->text(''),
            category: $this->parseCategory($dom),
            manufacturer: $brand ?? 'Pololu',
            mpn: $isPololuProduct ? $id : null,
            preview_image_url: $images !== [] ? $images[0]->url : null,
            manufacturing_status: $this->mapStatus($this->parseOrderFormValue($dom, 'Status')),
            provider_url: $productPageUrl,
            notes: $this->parseNotes($dom),
            datasheets: $resources !== null ? $this->parseFiles(new Crawler($resources)) : [],
            images: $images,
            parameters: $parameters,
            vendor_infos: [$purchaseInfo],
            mass: $this->parseMass($parameters),
            manufacturer_product_url: $isPololuProduct ? $productPageUrl : null,
        );
    }

    /**
     * Returns the value of a labeled field (like "Brand:" or "Status:") of the order form.
     */
    private function parseOrderFormValue(Crawler $dom, string $label): ?string
    {
        $value = null;

        $dom->filter('span.label')->each(function (Crawler $node) use (&$value, $label) {
            if ($value !== null || rtrim($this->cleanText($node->text()), ':') !== $label) {
                return;
            }

            $valueNode = $node->nextAll()->filter('span.value')->first();
            if ($valueNode->count() > 0) {
                $value = $this->cleanText($valueNode->text());
            }
        });

        return $value === '' ? null : $value;
    }

    /**
     * Normalizes the whitespaces (including non-breaking spaces) of the given text
     */
    private function cleanText(string $text): string
    {
        return trim(preg_replace('/[\s\x{00A0}]+/u', ' ', $text) ?? $text);
    }

    private function mapStatus(?string $status): ManufacturingStatus
    {
        if ($status === null) {
            return ManufacturingStatus::NOT_SET;
        }

        $status = strtolower($status);

        return match (true) {
            str_contains($status, 'discontinued'), str_contains($status, 'obsolete') => ManufacturingStatus::DISCONTINUED,
            str_contains($status, 'end of life'), str_contains($status, 'last time buy') => ManufacturingStatus::EOL,
            str_contains($status, 'not recommended') => ManufacturingStatus::NRFND,
            str_contains($status, 'active') => ManufacturingStatus::ACTIVE,
            default => ManufacturingStatus::NOT_SET,
        };
    }

    private function parseCategory(Crawler $dom): ?string
    {
        $categories = $dom->filter('#breadcrumbs a')->each(fn(Crawler $node) => $this->cleanText($node->text()));

        return $categories === [] ? null : implode(' -> ', $categories);
    }

    /**
     * @return PriceDTO[]
     */
    private function parsePrices(Crawler $dom): array
    {
        $prices = [];

        //Each row of the table consists of the minimum order amount and the unit price
        $dom->filter('table.pricing tr')->each(function (Crawler $row) use (&$prices) {
            $cells = $row->filter('td');
            if ($cells->count() < 2) {
                return;
            }

            $amount = str_replace(',', '', $this->cleanText($cells->eq(0)->text()));
            //If the product is on sale, the cell contains the regular price followed by the reduced one
            if (!is_numeric($amount) || !preg_match_all('/\d+(?:\.\d+)?/', str_replace(',', '', $cells->eq(1)->text()), $matches)) {
                return;
            }

            $prices[] = new PriceDTO(
                minimum_discount_amount: (float) $amount,
                price: end($matches[0]),
                currency_iso_code: self::CURRENCY,
                includes_tax: false,
            );
        });

        return $prices;
    }

    /**
     * @return FileDTO[]
     */
    private function parseImages(Crawler $dom): array
    {
        $images = [];

        //The main picture carries the list of all pictures of the product for the gallery
        $main = $dom->filter('#main_picture_img');
        if ($main->count() > 0) {
            $gallery = json_decode($main->attr('data-gallery-pictures') ?? '', true);
            foreach (is_array($gallery) ? $gallery : [] as $picture) {
                $url = $picture['url_full'] ?? $picture['url_medium'] ?? null;
                if (!is_string($url) || isset($images[$url])) {
                    continue;
                }

                $caption = $this->cleanText(html_entity_decode(strip_tags((string) ($picture['caption'] ?? '')), ENT_QUOTES | ENT_HTML5));
                $images[$url] = new FileDTO($url, $caption !== '' ? $caption : null);
            }
        }

        if ($images === []) {
            $ogImage = $dom->filter('meta[property="og:image"]');
            if ($ogImage->count() > 0 && $ogImage->attr('content')) {
                return [new FileDTO($ogImage->attr('content'))];
            }
        }

        return array_values($images);
    }

    private function parseNotes(Crawler $dom): string
    {
        $tabPage = $dom->filter('div.tab_page');
        if ($tabPage->count() === 0) {
            return '';
        }

        /** @var \DOMElement $root */
        $root = $tabPage->getNode(0)->cloneNode(true);

        //Remove the pictures (they are returned as images), scripts and embedded videos
        $remove = [];
        foreach (['table', 'script', 'style', 'iframe', 'img'] as $tag) {
            foreach ($root->getElementsByTagName($tag) as $element) {
                if ($tag !== 'table' || str_contains($element->getAttribute('class'), 'picture_with_caption')) {
                    $remove[] = $element;
                }
            }
        }
        foreach ($remove as $element) {
            $element->parentNode?->removeChild($element);
        }

        //Make the links absolute, so they work outside of pololu.com
        foreach ($root->getElementsByTagName('a') as $link) {
            $link->removeAttribute('onclick');
            if ($link->hasAttribute('href')) {
                $link->setAttribute('href', $this->absoluteURL($link->getAttribute('href')));
            }
        }

        $html = '';
        foreach ($root->childNodes as $child) {
            $html .= $root->ownerDocument->saveHTML($child);
        }

        //The removed pictures leave empty paragraphs behind
        return trim(preg_replace('#<p>\s*</p>#', '', $html) ?? $html);
    }

    private function absoluteURL(string $url): string
    {
        if (str_starts_with($url, '//')) {
            return 'https:' . $url;
        }
        if (str_starts_with($url, '/')) {
            return self::BASE_URL . $url;
        }

        return $url;
    }

    /**
     * Parses the specs tab of a product: Each table of specifications is preceded by a heading, which is used as group.
     * @return ParameterDTO[]
     */
    private function parseParameters(Crawler $dom): array
    {
        $parameters = [];
        $group = null;

        $dom->filter('div.tab_page')->children()->each(function (Crawler $node) use (&$parameters, &$group) {
            if ($node->nodeName() === 'h2') {
                $group = $this->cleanText($node->text());
                return;
            }

            if ($node->nodeName() !== 'table' || !str_contains($node->attr('class') ?? '', 'specifications')) {
                return;
            }

            $node->filter('tr')->each(function (Crawler $row) use (&$parameters, $group) {
                $header = $row->filter('th');
                $cell = $row->filter('td');
                if ($header->count() === 0 || $cell->count() === 0) {
                    return;
                }

                $name = rtrim($this->cleanText($header->text()), ':');
                $value = $this->textWithoutFootnotes($cell);
                if ($name === '' || $value === '') {
                    return;
                }

                //Yes/no specifications are named like a question ("Reverse voltage protection?") and answered with Y or N
                if (str_ends_with($name, '?')) {
                    $value = match ($value) {
                        'Y' => 'Yes',
                        'N' => 'No',
                        default => $value,
                    };
                }

                //Only split values like "2.7 V" into number and unit, but not markings like "0J3762"
                if (preg_match('/^-?\d+(?:\.\d+)?\s*[^\d\s]*$/u', $value)) {
                    $parameters[] = ParameterDTO::parseValueIncludingUnit(name: $name, value: $value, group: $group);
                } else {
                    $parameters[] = new ParameterDTO(name: $name, value_text: $value, group: $group);
                }
            });
        });

        return $parameters;
    }

    /**
     * Returns the text of the given node without the footnote references (like the "1" in "1.0 g¹")
     */
    private function textWithoutFootnotes(Crawler $node): string
    {
        /** @var \DOMElement $element */
        $element = $node->getNode(0)->cloneNode(true);

        foreach (iterator_to_array($element->getElementsByTagName('sup')) as $footnote) {
            $footnote->parentNode?->removeChild($footnote);
        }

        return $this->cleanText($element->textContent);
    }

    /**
     * Returns the weight in grams, if the specifications contain it
     * @param  ParameterDTO[]  $parameters
     */
    private function parseMass(array $parameters): ?float
    {
        foreach ($parameters as $parameter) {
            if (strcasecmp($parameter->name, 'Weight') !== 0 || $parameter->value_typ === null) {
                continue;
            }

            return match ($parameter->unit) {
                'g' => $parameter->value_typ,
                'kg' => $parameter->value_typ * 1000,
                'mg' => $parameter->value_typ / 1000,
                'oz' => round($parameter->value_typ * 28.3495, 2),
                'lb', 'lbs' => round($parameter->value_typ * 453.592, 2),
                default => null,
            };
        }

        return null;
    }

    /**
     * Parses the resources tab of a product. All file downloads (datasheets, dimension diagrams, 3D models, ...) are
     * returned, of the other resources (recommended links, guides, ...) only the ones which link directly to a file.
     * @return FileDTO[]
     */
    private function parseFiles(Crawler $dom): array
    {
        $files = [];

        $dom->filter('ul.resources')->each(function (Crawler $list) use (&$files) {
            $isFileList = str_contains($list->attr('class') ?? '', 'attached_files');

            $list->filter('li.resource .content_title a[href]')->each(function (Crawler $link) use (&$files, $isFileList) {
                $url = $this->absoluteURL($link->attr('href') ?? '');
                if (!str_starts_with($url, 'http') || isset($files[$url])) {
                    return;
                }

                if (!$isFileList && !preg_match(self::FILE_EXTENSIONS_REGEX, (string) parse_url($url, PHP_URL_PATH))) {
                    return;
                }

                $files[$url] = new FileDTO($url, $this->cleanText($link->text()));
            });
        });

        return array_values($files);
    }

    public function getHandledDomains(): array
    {
        return ['pololu.com'];
    }

    public function getIDFromURL(string $url): ?string
    {
        //URL like: https://www.pololu.com/product/2130 or https://www.pololu.com/product/2130/resources
        $matches = [];
        if (preg_match('#/product/(\d+)(?:[/?\#]|$)#', $url, $matches)) {
            return $matches[1];
        }

        return null;
    }
}
