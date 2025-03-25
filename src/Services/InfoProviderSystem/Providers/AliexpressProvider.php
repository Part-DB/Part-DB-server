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
use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\WebDriverDimension;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Panther\Client;
use Symfony\Component\Panther\DomCrawler\Link;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class AliexpressProvider implements InfoProviderInterface
{

    private readonly string $chromiumDriverPath;

    public function __construct(private readonly HttpClientInterface $client,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir)
    {
        $this->chromiumDriverPath = $this->projectDir . '/drivers/chromedriver.exe';
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
        //Create panther client
        $chromeOptions = new ChromeOptions();
        //Disable W3C mode, to avoid issues with getting html() from elements. See https://github.com/symfony/panther/issues/478
        $chromeOptions->setExperimentalOption('w3c', false);

        $client = Client::createChromeClient( $this->chromiumDriverPath, options: ['capabilities' => [ChromeOptions::CAPABILITY =>  $chromeOptions]]);
        $client->manage()->deleteAllCookies();
        $client->manage()->window()->setSize(new WebDriverDimension(1920, 1080));


        $client->request('GET', $product_page );

        //Dismiss cookie consent
        $dom = $client->waitFor('div.global-gdpr-wrap button.btn-accept');
        $dom->filter('div.global-gdpr-wrap button.btn-accept')->first()->click();

        $dom = $client->waitFor('h1[data-pl="product-title"]');
        $name = $dom->filter('h1[data-pl="product-title"]')->text();


        //Click on the description button
        $dom->filter('a[href="#nav-description"]')->first()->click();
        //$client->clickLink('Übersicht');

        $dom = $client->waitFor('#product-description');
        $description = $dom->filter('#product-description')->html();

        //Remove any script tags. This is just to prevent any weird output in the notes field, this is not really a security measure
        $description = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', "", $description);


        return new PartDetailDTO(
            provider_key: $this->getProviderKey(),
            provider_id: $id,
            name: $name,
            description: "",
            provider_url: $product_page,
            notes: $description,
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