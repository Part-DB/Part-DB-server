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

use App\Services\InfoProviderSystem\DTOs\FileDTO;
use App\Services\InfoProviderSystem\DTOs\ParameterDTO;
use App\Services\InfoProviderSystem\DTOs\PartDetailDTO;
use App\Services\InfoProviderSystem\DTOs\PriceDTO;
use App\Services\InfoProviderSystem\DTOs\PurchaseInfoDTO;
use App\Services\InfoProviderSystem\DTOs\SearchResultDTO;
use App\Settings\InfoProviderSystem\ConradSettings;
use App\Settings\InfoProviderSystem\ConradShopIDs;
use Symfony\Contracts\HttpClient\HttpClientInterface;

readonly class ConradProvider implements InfoProviderInterface, URLHandlerInfoProviderInterface
{

    private const SEARCH_ENDPOINT = '/search/1/v3/facetSearch';
    public const DISTRIBUTOR_NAME = 'Conrad';

    private HttpClientInterface $httpClient;

    public function __construct( HttpClientInterface $httpClient, private ConradSettings $settings)
    {
        //We want everything in JSON
        $this->httpClient = $httpClient->withOptions([
            'headers' => [
                'Accept' => 'application/json',
            ],
        ]);
    }

    public function getProviderInfo(): array
    {
        return [
            'name' => 'Conrad',
            'description' => 'Retrieves part information from conrad.de',
            'url' => 'https://www.conrad.de/',
            'disabled_help' => 'Set API key in settings',
            'settings_class' => ConradSettings::class,
        ];
    }

    public function getProviderKey(): string
    {
        return 'conrad';
    }

    public function isActive(): bool
    {
        return !empty($this->settings->apiKey);
    }

    private function getProductUrl(string $productId): string
    {
        return 'https://' . $this->settings->shopID->getDomain() . '/' . $this->settings->shopID->getLanguage() . '/p/' . $productId;
    }

    private function getFootprintFromTechnicalDetails(array $technicalDetails): ?string
    {
        foreach ($technicalDetails as $detail) {
            if ($detail['name'] === 'ATT_LOV_HOUSING_SEMICONDUCTORS') {
                return $detail['values'][0] ?? null;
            }
        }

        return null;
    }

    public function searchByKeyword(string $keyword): array
    {
        $url = $this->settings->shopID->getAPIRoot() . self::SEARCH_ENDPOINT . '/'
            . $this->settings->shopID->getDomainEnd() . '/' . $this->settings->shopID->getLanguage()
            . '/' . $this->settings->shopID->getCustomerType();

        $response = $this->httpClient->request('POST', $url, [
            'query' => [
                'apikey' => $this->settings->apiKey,
            ],
            'json' => [
                'query' => $keyword,
                'size' => 50,
                'sort' => [["field"=>"_score","order"=>"desc"]],
            ],
        ]);

        $out = [];
        $results = $response->toArray();

        foreach($results['hits'] as $result) {

            $out[] = new SearchResultDTO(
                provider_key: $this->getProviderKey(),
                provider_id: $result['productId'],
                name: $result['manufacturerId'] ?? $result['productId'],
                description: $result['title'] ?? '',
                manufacturer: $result['brand']['name'] ?? null,
                mpn: $result['manufacturerId'] ??  null,
                preview_image_url: $result['image'] ?? null,
                provider_url: $this->getProductUrl($result['productId']),
                footprint: $this->getFootprintFromTechnicalDetails($result['technicalDetails'] ?? []),
            );
        }

        return $out;
    }

    private function getFootprintFromTechnicalAttributes(array $technicalDetails): ?string
    {
        foreach ($technicalDetails as $detail) {
            if ($detail['attributeID'] === 'ATT.LOV.HOUSING_SEMICONDUCTORS') {
                return $detail['values'][0]['value'] ?? null;
            }
        }

        return null;
    }

    /**
     * @param  array  $technicalAttributes
     * @return array<ParameterDTO>
     */
    private function technicalAttributesToParameters(array $technicalAttributes): array
    {
        return array_map(static function (array $p) {
            if (count($p['values']) === 1) { //Single value attribute
                if (array_key_exists('unit', $p['values'][0])) {
                    return ParameterDTO::parseValueField( //With unit
                        name: $p['attributeName'],
                        value: $p['values'][0]['value'],
                        unit: $p['values'][0]['unit']['name'],
                    );
                }

                return ParameterDTO::parseValueIncludingUnit(
                    name: $p['attributeName'],
                    value: $p['values'][0]['value'],
                );
            }

            if (count($p['values']) === 2) { //Multi value attribute (e.g. min/max)
                $value  = $p['values'][0]['value'] ?? null;
                $value2 = $p['values'][1]['value'] ?? null;
                $unit   = $p['values'][0]['unit']['name'] ?? '';
                $unit2  = $p['values'][1]['unit']['name'] ?? '';
                if ($unit === $unit2 && is_numeric($value) && is_numeric($value2)) {
                    if (array_key_exists('unit', $p['values'][0])) { //With unit
                        return new ParameterDTO(
                            name: $p['attributeName'],
                            value_min: (float)$value,
                            value_max:  (float)$value2,
                            unit: $unit,
                        );
                    }

                    return new ParameterDTO(
                        name: $p['attributeName'],
                        value_min: (float)$value,
                        value_max: (float)$value2,
                    );
                }
            }

            // fallback implementation
            $values = implode(", ", array_map(fn($q) =>
            array_key_exists('unit', $q) ?  $q['value']." ". ($q['unit']['name'] ?? $q['unit']) : $q['value']
                , $p['values']));
            return ParameterDTO::parseValueIncludingUnit(
                name: $p['attributeName'],
                value: $values,
            );
        }, $technicalAttributes);
    }

    /**
     * @param  array  $productMedia
     * @return array<FileDTO>
     */
    public function productMediaToDatasheets(array $productMedia): array
    {
        $files = [];
        foreach ($productMedia['manuals'] as $manual) {
            //Filter out unwanted languages
            if (!empty($this->settings->attachmentLanguageFilter) && !in_array($manual['language'], $this->settings->attachmentLanguageFilter, true)) {
                continue;
            }

            $files[] = new FileDTO($manual['fullUrl'], $manual['title'] . ' (' . $manual['language'] . ')');
        }

        return $files;
    }


    /**
     * Queries prices for a given product ID. It makes a POST request to the Conrad API
     * @param  string  $productId
     * @return PurchaseInfoDTO
     */
    private function queryPrices(string $productId): PurchaseInfoDTO
    {
        $priceQueryURL = $this->settings->shopID->getAPIRoot() . '/price-availability/4/'
            . $this->settings->shopID->getShopID() . '/facade';

        $response = $this->httpClient->request('POST', $priceQueryURL, [
            'query' => [
                'apikey' => $this->settings->apiKey,
                'overrideCalculationSchema' => $this->settings->includeVAT ? 'GROSS' : 'NET'
            ],
            'json' => [
                'ns:inputArticleItemList' => [
                    "#namespaces" => [
                        "ns" => "http://www.conrad.de/ccp/basit/service/article/priceandavailabilityservice/api"
                    ],
                    'articles' => [
                        [
                            "articleID" => $productId,
                            "calculatePrice" => true,
                            "checkAvailability" => true,
                        ],
                    ]
                ]
            ]
        ]);

        $result = $response->toArray();

        $priceInfo = $result['priceAndAvailabilityFacadeResponse']['priceAndAvailability']['price'] ?? [];
        $price = $priceInfo['price'] ?? "0.0";
        $currency = $priceInfo['currency'] ?? "EUR";
        $includesVat = !$priceInfo['isGrossAmount'] || $priceInfo['isGrossAmount'] === "true";
        $minOrderAmount = $result['priceAndAvailabilityFacadeResponse']['priceAndAvailability']['availabilityStatus']['minimumOrderQuantity'] ?? 1;

        $prices = [];
        foreach ($priceInfo['priceScale'] ?? [] as $priceScale) {
            $prices[] = new PriceDTO(
                minimum_discount_amount: max($priceScale['scaleFrom'], $minOrderAmount),
                price: (string)$priceScale['pricePerUnit'],
                currency_iso_code: $currency,
                includes_tax: $includesVat
            );
        }
        if (empty($prices)) { //Fallback if no price scales are defined
            $prices[] = new PriceDTO(
                minimum_discount_amount: $minOrderAmount,
                price: (string)$price,
                currency_iso_code: $currency,
                includes_tax: $includesVat
            );
        }

        return new PurchaseInfoDTO(
            distributor_name: self::DISTRIBUTOR_NAME,
            order_number: $productId,
            prices: $prices,
            product_url: $this->getProductUrl($productId)
        );
    }

    public function getDetails(string $id): PartDetailDTO
    {
        $productInfoURL = $this->settings->shopID->getAPIRoot() . '/product/1/service/' . $this->settings->shopID->getShopID()
            . '/product/' . $id;

        $response = $this->httpClient->request('GET', $productInfoURL, [
            'query' => [
                'apikey' => $this->settings->apiKey,
            ]
        ]);

        $data = $response->toArray();

        return new PartDetailDTO(
            provider_key: $this->getProviderKey(),
            provider_id: $data['shortProductNumber'],
            name: $data['productFullInformation']['manufacturer']['name'] ?? $data['productFullInformation']['manufacturer']['id']  ?? $data['shortProductNumber'],
            description: $data['productShortInformation']['title'] ?? '',
            category: $data['productShortInformation']['articleGroupName'] ?? null,
            manufacturer: $data['brand']['displayName'] !== null ? preg_replace("/[\u{2122}\u{00ae}]/", "", $data['brand']['displayName']) : null, //Replace ™ and ® symbols
            mpn: $data['productFullInformation']['manufacturer']['id'] ?? null,
            preview_image_url: $data['productShortInformation']['mainImage']['imageUrl'] ?? null,
            provider_url: $this->getProductUrl($data['shortProductNumber']),
            footprint: $this->getFootprintFromTechnicalAttributes($data['productFullInformation']['technicalAttributes'] ?? []),
            notes: $data['productFullInformation']['description'] ?? null,
            datasheets: $this->productMediaToDatasheets($data['productMedia'] ?? []),
            parameters: $this->technicalAttributesToParameters($data['productFullInformation']['technicalAttributes'] ?? []),
            vendor_infos: [$this->queryPrices($data['shortProductNumber'])]
        );
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

    public function getHandledDomains(): array
    {
        $domains = [];
        foreach (ConradShopIDs::cases() as $shopID) {
            $domains[] = $shopID->getDomain();
        }
        return array_unique($domains);
    }

    public function getIDFromURL(string $url): ?string
    {
        //Input: https://www.conrad.de/de/p/apple-iphone-air-wolkenweiss-256-gb-eek-a-a-g-16-5-cm-6-5-zoll-3475299.html
        //The numbers before the optional .html are the product ID

        $matches = [];
        if (preg_match('/-(\d+)(\.html)?$/', $url, $matches) === 1) {
            return $matches[1];
        }

        return null;
    }
}
