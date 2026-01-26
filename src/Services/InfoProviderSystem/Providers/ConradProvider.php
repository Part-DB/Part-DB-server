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
use App\Settings\InfoProviderSystem\ConradSettings;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class ConradProvider implements InfoProviderInterface
{

    private const ENDPOINT_URL = 'https://api.conrad.com';

    public const DISTRIBUTOR_NAME = "Conrad";

    public function __construct(private readonly HttpClientInterface $client,
        private readonly ConradSettings $settings,
    )
    {
    }

    public function getProviderInfo(): array
    {
        return [
            'name' => 'Conrad',
            'description' => 'This provider uses the Conrad API to search for parts.',
            'url' => 'https://www.conrad.com/',
            'disabled_help' => 'Configure the API key in the provider settings to enable.',
            'settings_class' => ConradSettings::class,
        ];
    }

    public function getProviderKey(): string
    {
        return 'conrad';
    }

    public function isActive(): bool
    {
        return $this->settings->apiKey !== '' && $this->settings->apiKey !== null;
    }

    public function searchByKeyword(string $keyword, bool $b2b = false): array
    {
        $salesOrg = strtolower($this->settings->country);
        if ($salesOrg == 'gb' || $salesOrg == 'us') $salesOrg = "com";
        $lang = $this->settings->language;
        $btx = $b2b ? "b2b" : "b2c";

        $response = $this->makeAPICall("/search/1/v3/facetSearch/$salesOrg/$lang/$btx", [], [
            'sort' => [["field"=>"_score","order"=>"desc"]],
            'from' => 0,
            'size' => 50,
            'query' => $keyword,
        ]);

        $products = $response['hits'] ?? [];

        $productIds = array_map(fn($p) => $p['productId'], $products);
        $details = $this->getMultiDetails($productIds, false);
        $urls = [];
        foreach ($details as $item) {
            $urls[$item->provider_id] = $item->provider_url;
        }

        $sanitize = fn($str) => preg_replace("/[\u{2122}\u{00ae}]/", "", $str);

        if (is_array($products) && !empty($products)) {
            return array_map(fn($p) =>
                new SearchResultDTO(
                    provider_key: $this->getProviderKey(),
                    provider_id: $p['productId'],
                    name: $p['manufacturerId'],
                    description: $p['title'],
                    category: null,
                    manufacturer: $sanitize($p['brand']['name']),
                    preview_image_url: $p['image'],
                    provider_url: $urls[$p['productId']] ?? null
                    )
              , $products);
        }
        else if (!$b2b) {
            return $this->searchByKeyword($keyword, true);

        }
        return [];
    }
    public function getDetails(string $id): PartDetailDTO
    {
        $products = $this->getMultiDetails([$id]);
        if (is_array($products) && !empty($products)) {
            return $products[0];
        }
        throw new \RuntimeException('No part found with ID ' . $id);
    }
    private function getMultiDetails(array $ids, bool $queryPrices = true): array
    {
        $ep = $this->getLocalEndpoint();
        $response = $this->makeAPICall("/product/1/service/$ep/productdetails", [
            'language' => $this->settings->language,
        ], [
            'productIDs' => $ids,
        ]);

        $products = $response['productDetailPages'] ?? [];
        //Create part object
        if (is_array($products) && !empty($products)) {
            return array_map(function($p) use ($queryPrices) {
                $info = $p['productShortInformation'] ?? [];
                $domain = $this->getDomain();
                $lang = $this->settings->language;
                $productPage = "https://www.$domain/$lang/p/".$info['slug'].'.html';
                $datasheets = array_filter($p['productMedia']['manuals'] ?? [], fn($q) => $q['type']=="da");
                $datasheets = array_map(fn($q) => new FileDTO($q['fullUrl'], $q['title']), $datasheets);
                $purchaseInfo = $queryPrices ? [$this->queryPrices($p['shortProductNumber'], $productPage)] : [];

                $sanitize = fn($str) => preg_replace("/[\u{2122}\u{00ae}]/", "", $str);

                return new PartDetailDTO(
                    provider_key: $this->getProviderKey(),
                    provider_id: $p['shortProductNumber'],
                    name: $info['manufacturer']['name'] ?? $p['shortProductNumber'],
                    description: $info['shortDescription'],
                    category: $info['articleGroupName'],
                    manufacturer: $sanitize($p['brand']['displayName']),
                    mpn: $info['manufacturer']['id'],
                    preview_image_url: $info['mainImage']['imageUrl'],
                    provider_url: $productPage,
                    notes: $p['productFullInformation']['description'],
                    datasheets: $datasheets,
                    parameters: $this->parseParameters($p['productFullInformation']['technicalAttributes'] ?? []),
                    vendor_infos: $purchaseInfo
                );
            }, $products);
        }
        return [];
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

    private function makeAPICall(string $endpoint, array $queryParams = [], array $jsonParams = []): array
    {
        try {
            $response = $this->client->request('POST', self::ENDPOINT_URL . $endpoint, [
                'headers' => ['Accept' => 'application/json',
                              'Content-Type' => 'application/json;charset=UTF-8'],
                'query' => array_merge($queryParams, [
                    'apikey' => $this->settings->apiKey
                ]),
                'json' => $jsonParams,
            ]);

            return $response->toArray();
        } catch (\Exception $e) {
            throw new \RuntimeException("Conrad API request failed: " .
                "Endpoint: " . $endpoint . " " .
                "QueryParams: " . json_encode($queryParams, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . " " .
                "JsonParams: " . json_encode($jsonParams, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . " " .
                "Exception message: " . $e->getMessage());
        }
    }

    /**
     * @param  Crawler  $dom
     * @return ParameterDTO[]
     */
    private function parseParameters(array $attr): array
    {
        return array_map(function ($p) {
            if (count($p['values']) == 1) {
                if (array_key_exists('unit', $p['values'][0])) {
                    return ParameterDTO::parseValueField(
                        name: $p['attributeName'],
                        value: $p['values'][0]['value'],
                        unit: $p['values'][0]['unit']['name'],
                    );
                } else {
                    return ParameterDTO::parseValueIncludingUnit(
                        name: $p['attributeName'],
                        value: $p['values'][0]['value'],
                    );
                }
            }
            else if (count($p['values']) == 2) {
                $value  = $p['values'][0]['value'] ?? null;
                $value2 = $p['values'][1]['value'] ?? null;
                $unit   = $p['values'][0]['unit']['name'] ?? '';
                $unit2  = $p['values'][1]['unit']['name'] ?? '';
                if ($unit === $unit2 && is_numeric($value) && is_numeric($value2)) {
                    if (array_key_exists('unit', $p['values'][0])) {
                        return new ParameterDTO(
                            name: $p['attributeName'],
                            value_min: $value  == null ? null : (float)$value,
                            value_max: $value2 == null ? null : (float)$value2,
                            unit: $unit,
                        );
                    } else {
                        return new ParameterDTO(
                            name: $p['attributeName'],
                            value_min: $value  == null ? null : (float)$value,
                            value_max: $value2 == null ? null : (float)$value2,
                        );
                    }
                }
            }

            // fallback implementation
            $values = implode(", ", array_map(fn($q) =>
                    array_key_exists('unit', $q) ?  $q['value']." ". $q['unit'] : $q['value']
                , $p['values']));
            return ParameterDTO::parseValueIncludingUnit(
                name: $p['attributeName'],
                value: $values,
            );
        }, $attr);
    }

    private function queryPrices(string $productId, ?string $productPage = null): PurchaseInfoDTO
    {
        $ep = $this->getLocalEndpoint();
        $response = $this->makeAPICall("/price-availability/4/$ep/facade", [
            'overrideCalculationSchema' => $this->settings->includeVAT ? 'GROSS' : 'NET'
        ], [
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
        ]);

        $priceInfo = $response['priceAndAvailabilityFacadeResponse']['priceAndAvailability']['price'] ?? [];
        $price = $priceInfo['price'] ?? 0;
        $currency = $priceInfo['currency'] ?? "EUR";
        $includesVat = $priceInfo['isGrossAmount'] == "true" ?? true;

        return new PurchaseInfoDTO(
            distributor_name: self::DISTRIBUTOR_NAME,
            order_number: $productId,
            prices: array_merge(
                [new PriceDTO(1.0, strval($price), $currency, $includesVat)]
            , $this->parseBatchPrices($priceInfo, $currency, $includesVat)),
            product_url: $productPage
        );
    }

    private function parseBatchPrices(array $priceInfo, string $currency, bool $includesVat): array
    {
        $priceScale = array_filter($priceInfo['priceScale'] ?? [], fn($p) => $p['scaleFrom'] != 1);
        return array_map(fn($p) =>
            new PriceDTO($p['scaleFrom'], strval($p['pricePerUnit']), $currency, $includesVat)
          , $priceScale);
    }

    private function getLocalEndpoint(): string
    {
        switch ($this->settings->country) {
            case "DE":
                return "CQ_DE_B2C";
            case "CH":
                return "CQ_CH_B2C";
            case "NL":
                return "CQ_NL_B2C";
            case "AT":
                return "CQ_AT_B2C";
            case "HU":
                return "CQ_HU_B2C";
            case "FR":
                return "HP_FR_B2B";
            case "IT":
                return "HP_IT_B2B";
            case "PL":
                return "HP_PL_B2B";
            case "CZ":
                return "HP_CZ_B2B";
            case "BE":
                return "HP_BE_B2B";
            case "DK":
                return "HP_DK_B2B";
            case "HR":
                return "HP_HR_B2B";
            case "SE":
                return "HP_SE_B2B";
            case "SK":
                return "HP_SK_B2B";
            case "SI":
                return "HP_SI_B2B";
            case "GB": // fall through
            case "US":
            default:
                return "HP_COM_B2B";
        }
    }

    private function getDomain(): string
    {
        switch ($this->settings->country) {
            case "DK":
                return "conradelektronik.dk";
            case "DE": // fall through
            case "CH":
            case "NL":
            case "AT":
            case "HU":
            case "FR":
            case "IT":
            case "PL":
            case "CZ":
            case "BE":
            case "HR":
            case "SE":
            case "SK":
            case "SI":
                return "conrad.".strtolower($this->settings->country);
            case "GB": // fall through
            case "US":
            default:
                return "conrad.com";
        }
    }
}
