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

class ReicheltProvider implements InfoProviderInterface
{

    private const SEARCH_ENDPOINT = "https://www.reichelt.com/index.html?ACTION=446&LA=0&nbc=1&q=%s";

    public function __construct(private readonly HttpClientInterface $client)
    {
    }

    public function getProviderInfo(): array
    {
        return [
            'name' => 'Reichelt',
            'description' => 'TODO',
            'url' => 'https://www.reichelt.de/',
            'disabled_help' => 'TODO'
        ];
    }

    public function getProviderKey(): string
    {
        return 'reichelt';
    }

    public function isActive(): bool
    {
        return true;
    }

    public function searchByKeyword(string $keyword): array
    {
        //Lowercase the keyword and urlencode it
        $keyword = urlencode($keyword);
        $response = $this->client->request('GET', sprintf(self::SEARCH_ENDPOINT, $keyword));
        $html = $response->getContent();

        //Parse the HTML and return the results
        $dom = new Crawler($html);
        //Iterate over all div.al_gallery_article elements
        $results = [];
        $dom->filter('div.al_gallery_article')->each(function (Crawler $element) use (&$results) {

            $productID = $element->filter('meta[itemprop="productID"]')->attr('content');
            $name = $element->filter('meta[itemprop="name"]')->attr('content');
            $sku = $element->filter('meta[itemprop="sku"]')->attr('content');

            //Try to extract a picture URL:
            $pictureURL = $element->filter("div.al_artlogo img")->attr('src');

            $results[] = new SearchResultDTO(
                provider_key: $this->getProviderKey(),
                provider_id: $productID,
                name: $productID,
                description: $name,
                category: null,
                manufacturer: $sku,
                preview_image_url: $pictureURL,
                provider_url: $element->filter('a.al_artinfo_link')->attr('href')
                );
        });

        return $results;
    }

    public function getDetails(string $id): PartDetailDTO
    {
        // TODO: Implement getDetails() method.
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