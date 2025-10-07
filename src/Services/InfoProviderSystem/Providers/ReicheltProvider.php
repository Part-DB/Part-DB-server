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

use App\Services\InfoProviderSystem\DTOs\FileDTO;
use App\Services\InfoProviderSystem\DTOs\ParameterDTO;
use App\Services\InfoProviderSystem\DTOs\PartDetailDTO;
use App\Services\InfoProviderSystem\DTOs\PriceDTO;
use App\Services\InfoProviderSystem\DTOs\PurchaseInfoDTO;
use App\Services\InfoProviderSystem\DTOs\SearchResultDTO;
use App\Settings\InfoProviderSystem\ReicheltSettings;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class ReicheltProvider implements InfoProviderInterface
{

    public const DISTRIBUTOR_NAME = "Reichelt";

    public function __construct(private readonly HttpClientInterface $client,
        private readonly ReicheltSettings $settings,
    )
    {
    }

    public function getProviderInfo(): array
    {
        return [
            'name' => 'Reichelt',
            'description' => 'Webscraping from reichelt.com to get part information',
            'url' => 'https://www.reichelt.com/',
            'disabled_help' => 'Enable provider in provider settings.',
            'settings_class' => ReicheltSettings::class,
        ];
    }

    public function getProviderKey(): string
    {
        return 'reichelt';
    }

    public function isActive(): bool
    {
        return $this->settings->enabled;
    }

    public function searchByKeyword(string $keyword): array
    {
        $response = $this->client->request('GET', sprintf($this->getBaseURL() . '/shop/search/%s', $keyword));
        $html = $response->getContent();

        //Parse the HTML and return the results
        $dom = new Crawler($html);
        //Iterate over all div.al_gallery_article elements
        $results = [];
        $dom->filter('div.al_gallery_article')->each(function (Crawler $element) use (&$results) {

            //Extract product id from data-product attribute
            $artId = json_decode($element->attr('data-product'), true, 2, JSON_THROW_ON_ERROR)['artid'];

            $productID = $element->filter('meta[itemprop="productID"]')->attr('content');
            $name = $element->filter('meta[itemprop="name"]')->attr('content');
            $sku = $element->filter('meta[itemprop="sku"]')->attr('content');

            //Try to extract a picture URL:
            $pictureURL = $element->filter("div.al_artlogo img")->attr('src');

            $results[] = new SearchResultDTO(
                provider_key: $this->getProviderKey(),
                provider_id: $artId,
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
        //Check that the ID is a number
        if (!is_numeric($id)) {
            throw new \InvalidArgumentException("Invalid ID");
        }

        //Use this endpoint to resolve the artID to a product page
        $response = $this->client->request('GET',
            sprintf(
                'https://www.reichelt.com/?ACTION=514&id=74&article=%s&LANGUAGE=%s&CCOUNTRY=%s',
                $id,
                strtoupper($this->settings->language),
                strtoupper($this->settings->country)
            )
        );
        $json = $response->toArray();

        //Retrieve the product page from the response
        $productPage = $this->getBaseURL() . '/shop/product' .  $json[0]['article_path'];


        $response = $this->client->request('GET', $productPage, [
            'query' => [
                'CCTYPE' => $this->settings->includeVAT ? 'private' : 'business',
                'currency' => $this->settings->currency,
            ],
        ]);
        $html = $response->getContent();
        $dom = new Crawler($html);

        //Extract the product notes
        $notes = $dom->filter('p[itemprop="description"]')->html();

        //Extract datasheets
        $datasheets = [];
        $dom->filter('div.articleDatasheet a')->each(function (Crawler $element) use (&$datasheets) {
            $datasheets[] = new FileDTO($element->attr('href'), $element->filter('span')->text());
        });

        //Determine price for one unit
        $priceString = $dom->filter('meta[itemprop="price"]')->attr('content');
        $currency = $dom->filter('meta[itemprop="priceCurrency"]')->attr('content', 'EUR');

        //Create purchase info
        $purchaseInfo = new PurchaseInfoDTO(
            distributor_name: self::DISTRIBUTOR_NAME,
            order_number: $json[0]['article_artnr'],
            prices: array_merge(
                [new PriceDTO(1.0, $priceString, $currency, $this->settings->includeVAT)]
            , $this->parseBatchPrices($dom, $currency)),
            product_url: $productPage
        );

        //Create part object
        return new PartDetailDTO(
            provider_key: $this->getProviderKey(),
            provider_id: $id,
            name: $json[0]['article_artnr'],
            description: $json[0]['article_besch'],
            category: $this->parseCategory($dom),
            manufacturer: $json[0]['manufacturer_name'],
            mpn: $this->parseMPN($dom),
            preview_image_url: $json[0]['article_picture'],
            provider_url: $productPage,
            notes: $notes,
            datasheets: $datasheets,
            parameters: $this->parseParameters($dom),
            vendor_infos: [$purchaseInfo]
        );

    }

    private function parseMPN(Crawler $dom): string
    {
        //Find the small element directly after meta[itemprop="url"] element
        $element = $dom->filter('meta[itemprop="url"] + small');
        //If the text contains GTIN text, take the small element afterwards
        if (str_contains($element->text(), 'GTIN')) {
            $element = $dom->filter('meta[itemprop="url"] + small + small');
        }

        //The MPN is contained in the span inside the element
        return $element->filter('span')->text();
    }

    private function parseBatchPrices(Crawler $dom, string $currency): array
    {
        //Iterate over each a.inline-block element in div.discountValue
        $prices = [];
        $dom->filter('div.discountValue a.inline-block')->each(function (Crawler $element) use (&$prices, $currency) {
            //The minimum amount is the number in the span.block element
            $minAmountText = $element->filter('span.block')->text();

            //Extract a integer from the text
            $matches = [];
            if (!preg_match('/\d+/', $minAmountText, $matches)) {
                return;
            }

            $minAmount = (int) $matches[0];

            //The price is the text of the p.productPrice element
            $priceString = $element->filter('p.productPrice')->text();
            //Replace comma with dot
            $priceString = str_replace(',', '.', $priceString);
            //Strip any non-numeric characters
            $priceString = preg_replace('/[^0-9.]/', '', $priceString);

            $prices[] = new PriceDTO($minAmount, $priceString, $currency, $this->settings->includeVAT);
        });

        return $prices;
    }


    private function parseCategory(Crawler $dom): string
    {
        // Look for ol.breadcrumb and iterate over the li elements
        $category = '';
        $dom->filter('ol.breadcrumb li.triangle-left')->each(function (Crawler $element) use (&$category) {
            //Do not include the .breadcrumb-showmore element
            if ($element->attr('id') === 'breadcrumb-showmore') {
                return;
            }

            $category .= $element->text() . ' -> ';
        });
        //Remove the trailing ' -> '
        $category = substr($category, 0, -4);

        return $category;
    }

    /**
     * @param  Crawler  $dom
     * @return ParameterDTO[]
     */
    private function parseParameters(Crawler $dom): array
    {
        $parameters = [];
        //Iterate over each ul.articleTechnicalData which contains the specifications of each group
        $dom->filter('ul.articleTechnicalData')->each(function (Crawler $groupElement) use (&$parameters) {
            $groupName = $groupElement->filter('li.articleTechnicalHeadline')->text();

            //Iterate over each second li in ul.articleAttribute, which contains the specifications
            $groupElement->filter('ul.articleAttribute li:nth-child(2n)')->each(function (Crawler $specElement) use (&$parameters, $groupName) {
                $parameters[] = ParameterDTO::parseValueIncludingUnit(
                    name: $specElement->previousAll()->text(),
                    value: $specElement->text(),
                    group: $groupName
                );
            });
        });

        return $parameters;
    }

    private function getBaseURL(): string
    {
        //Without the trailing slash
        return 'https://www.reichelt.com/' . strtolower($this->settings->country) . '/' . strtolower($this->settings->language);
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
