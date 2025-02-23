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

use App\Services\InfoProviderSystem\DTOs\PartDetailDTO;
use App\Services\InfoProviderSystem\DTOs\SearchResultDTO;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class AliexpressProvider implements InfoProviderInterface
{

    public function __construct(private readonly HttpClientInterface $client)
    {

    }

    public function getProviderInfo(): array
    {
        return [
            'name' => 'Aliexpress',
            'description' => 'Webscrapping from reichelt.com to get part information',
            'url' => 'https://aliexpress.com/',
            'disabled_help' => 'Set PROVIDER_REICHELT_ENABLED env to 1'
        ];
    }

    public function getProviderKey(): string
    {
        return "aliexpress";
    }

    public function isActive(): bool
    {
        return true;
    }

    public function getBaseURL(): string
    {
        //Without the trailing slash
        return 'https://de.aliexpress.com';
    }

    public function searchByKeyword(string $keyword): array
    {
        $response = $this->client->request('GET', $this->getBaseURL() . '/wholesale', [
            'query' => [
                'SearchText' => $keyword,
                'CatId' => 0,
                'd' => 'y',
                ]
            ]
        );

        $content = $response->getContent();
        $dom = new Crawler($content);

        $results = [];

        //Iterate over each div.search-item-card-wrapper-gallery
        $dom->filter('div.search-item-card-wrapper-gallery')->each(function (Crawler $node) use (&$results) {

            $productURL = $this->cleanProductURL($node->filter("a")->first()->attr('href'));
            $productID = $this->extractProductID($productURL);

            //Skip results where we cannot extract a product ID
            if ($productID === null) {
                return;
            }

            $results[] = new SearchResultDTO(
                provider_key: $this->getProviderKey(),
                provider_id: $productID,
                name: $node->filter("div[title]")->attr('title'),
                description: "",
                preview_image_url: $node->filter("img")->first()->attr('src'),
                provider_url: $productURL
            );
        });

        return $results;
    }

    private function cleanProductURL(string $url): string
    {
        //Strip the query string
        return explode('?', $url)[0];
    }

    private function extractProductID(string $url): ?string
    {
        //We want the numeric id from the url before the .html
        $matches = [];
        preg_match('/\/(\d+)\.html/', $url, $matches);

        return $matches[1] ?? null;
    }

    public function getDetails(string $id): PartDetailDTO
    {
        //Ensure that $id is numeric
        if (!is_numeric($id)) {
            throw new \InvalidArgumentException("The id must be numeric");
        }

        $product_page = $this->getBaseURL() . "/item/{$id}.html";
        $response = $this->client->request('GET', $product_page );

        $content = $response->getContent();
        $dom = new Crawler($content);

        return new PartDetailDTO(
            provider_key: $this->getProviderKey(),
            provider_id: $id,
            name: $dom->filter('h1[data-pl="product-title"]')->text(),
            description: "",
            provider_url: $product_page,
            notes: $dom->filter('div[data-pl="product-description"]')->html(),
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