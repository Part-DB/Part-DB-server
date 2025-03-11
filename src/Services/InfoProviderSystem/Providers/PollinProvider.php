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
use App\Entity\Parts\Part;
use App\Services\InfoProviderSystem\DTOs\FileDTO;
use App\Services\InfoProviderSystem\DTOs\ParameterDTO;
use App\Services\InfoProviderSystem\DTOs\PartDetailDTO;
use App\Services\InfoProviderSystem\DTOs\PriceDTO;
use App\Services\InfoProviderSystem\DTOs\PurchaseInfoDTO;
use App\Services\InfoProviderSystem\DTOs\SearchResultDTO;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class PollinProvider implements InfoProviderInterface
{

    public function __construct(private readonly HttpClientInterface $client,
        #[Autowire(env: 'bool:PROVIDER_POLLIN_ENABLED')]
        private readonly bool $enabled = true,
    )
    {
    }

    public function getProviderInfo(): array
    {
        return [
            'name' => 'Pollin',
            'description' => 'Webscrapping from pollin.de to get part information',
            'url' => 'https://www.reichelt.de/',
            'disabled_help' => 'Set PROVIDER_POLLIN_ENABLED env to 1'
        ];
    }

    public function getProviderKey(): string
    {
        return 'pollin';
    }

    public function isActive(): bool
    {
        return $this->enabled;
    }

    public function searchByKeyword(string $keyword): array
    {
        $response = $this->client->request('GET', 'https://www.pollin.de/search', [
            'query' => [
                'search' => $keyword
            ]
        ]);

        $content = $response->getContent();

        //If the response has us redirected to the product page, then just return the single item
        if ($response->getInfo('redirect_count') > 0) {
            return [$this->parseProductPage($content)];
        }

        $dom = new Crawler($content);

        $results = [];

        //Iterate over each div.product-box
        $dom->filter('div.product-box')->each(function (Crawler $node) use (&$results) {
            $results[] = new SearchResultDTO(
                provider_key: $this->getProviderKey(),
                provider_id: $node->filter('meta[itemprop="productID"]')->attr('content'),
                name: $node->filter('a.product-name')->text(),
                description: '',
                preview_image_url: $node->filter('img.product-image')->attr('src'),
                manufacturing_status: $this->mapAvailability($node->filter('link[itemprop="availability"]')->attr('href')),
                provider_url: $node->filter('a.product-name')->attr('href')
            );
        });

        return $results;
    }

    private function mapAvailability(string $availabilityURI): ManufacturingStatus
    {
        return match( $availabilityURI) {
            'http://schema.org/InStock' => ManufacturingStatus::ACTIVE,
            'http://schema.org/OutOfStock' => ManufacturingStatus::DISCONTINUED,
            default => ManufacturingStatus::NOT_SET
        };
    }

    public function getDetails(string $id): PartDetailDTO
    {
        //Ensure that $id is numeric
        if (!is_numeric($id)) {
            throw new \InvalidArgumentException("The id must be numeric!");
        }

        $response = $this->client->request('GET', 'https://www.pollin.de/search', [
            'query' => [
                'search' => $id
            ]
        ]);

        //The response must have us redirected to the product page
        if ($response->getInfo('redirect_count') > 0) {
            throw new \RuntimeException("Could not resolve the product page for the given id!");
        }

        $content = $response->getContent();

        return $this->parseProductPage($content);
    }

    private function parseProductPage(string $content): PartDetailDTO
    {
        $dom = new Crawler($content);

        $productPageUrl = $dom->filter('meta[property="product:product_link"]')->attr('content');
        $orderId = trim($dom->filter('span[itemprop="sku"]')->text()); //Text is important here

        //Calculate the mass
        $massStr = $dom->filter('meta[itemprop="weight"]')->attr('content');
        //Remove the unit
        $massStr = str_replace('kg', '', $massStr);
        //Convert to float and convert to grams
        $mass = (float) $massStr * 1000;

        //Parse purchase info
        $purchaseInfo = new PurchaseInfoDTO('Pollin', $orderId, $this->parsePrices($dom), $productPageUrl);

        return new PartDetailDTO(
            provider_key: $this->getProviderKey(),
            provider_id: $orderId,
            name: trim($dom->filter('meta[property="og:title"]')->attr('content')),
            description: $dom->filter('meta[property="og:description"]')->attr('content'),
            category: $this->parseCategory($dom),
            manufacturer: $dom->filter('meta[property="product:brand"]')->count() > 0 ? $dom->filter('meta[property="product:brand"]')->attr('content') : null,
            preview_image_url: $dom->filter('meta[property="og:image"]')->attr('content'),
            manufacturing_status: $this->mapAvailability($dom->filter('link[itemprop="availability"]')->attr('href')),
            provider_url: $productPageUrl,
            notes: $this->parseNotes($dom),
            datasheets: $this->parseDatasheets($dom),
            parameters: $this->parseParameters($dom),
            vendor_infos: [$purchaseInfo],
            mass: $mass,
        );
    }

    private function parseDatasheets(Crawler $dom): array
    {
        //Iterate over each a element withing div.pol-product-detail-download-files
        $datasheets = [];
        $dom->filter('div.pol-product-detail-download-files a')->each(function (Crawler $node) use (&$datasheets) {
            $datasheets[] = new FileDTO($node->attr('href'), $node->text());
        });

        return $datasheets;
    }

    private function parseParameters(Crawler $dom): array
    {
        $parameters = [];

        //Iterate over each tr.properties-row inside table.product-detail-properties-table
        $dom->filter('table.product-detail-properties-table tr.properties-row')->each(function (Crawler $node) use (&$parameters) {
            $parameters[] = ParameterDTO::parseValueIncludingUnit(
                name: rtrim($node->filter('th.properties-label')->text(), ':'),
                value: trim($node->filter('td.properties-value')->text())
            );
        });

        return $parameters;
    }

    private function parseCategory(Crawler $dom): string
    {
        $category = '';

        //Iterate over each li.breadcrumb-item inside ol.breadcrumb
        $dom->filter('ol.breadcrumb li.breadcrumb-item')->each(function (Crawler $node) use (&$category) {
            //Skip if it has breadcrumb-item-home class
            if (str_contains($node->attr('class'), 'breadcrumb-item-home')) {
                return;
            }


            $category .= $node->text() . ' -> ';
        });

        //Remove the last ' -> '
        return substr($category, 0, -4);
    }

    private function parseNotes(Crawler $dom): string
    {
        //Concat product highlights and product description
        return $dom->filter('div.product-detail-top-features')->html() . '<br><br>' . $dom->filter('div.product-detail-description-text')->html();
    }

    private function parsePrices(Crawler $dom): array
    {
        //TODO: Properly handle multiple prices, for now we just look at the price for one piece

        //We assume the currency is always the same
        $currency = $dom->filter('meta[property="product:price:currency"]')->attr('content');

        //If there is meta[property=highPrice] then use this as the price
        if ($dom->filter('meta[itemprop="highPrice"]')->count() > 0) {
            $price = $dom->filter('meta[itemprop="highPrice"]')->attr('content');
        } else {
            $price = $dom->filter('meta[property="product:price:amount"]')->attr('content');
        }

        return [
            new PriceDTO(1.0, $price, $currency)
        ];
    }

    public function getCapabilities(): array
    {
        return [
            ProviderCapabilities::BASIC,
            ProviderCapabilities::PICTURE,
            ProviderCapabilities::DATASHEET,
            ProviderCapabilities::PRICE,
        ];
    }
}
