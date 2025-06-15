<?php
/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2023 Jan Böhmer (https://github.com/jbtronics)
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

/*
* This file provide an interface with the Mouser API V2 (also compatible with the V1)
*
* Copyright (C) 2023 Pasquale D'Orsi (https://github.com/pdo59)
*
* TODO: Obtain an API keys with an US Mouser user (currency $) and test the result of prices
*
*/

declare(strict_types=1);


namespace App\Services\InfoProviderSystem\Providers;

use App\Entity\Parts\ManufacturingStatus;
use App\Services\InfoProviderSystem\DTOs\FileDTO;
use App\Services\InfoProviderSystem\DTOs\PartDetailDTO;
use App\Services\InfoProviderSystem\DTOs\PriceDTO;
use App\Services\InfoProviderSystem\DTOs\PurchaseInfoDTO;
use App\Settings\InfoProviderSystem\MouserSettings;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;


class MouserProvider implements InfoProviderInterface
{

    private const ENDPOINT_URL = 'https://api.mouser.com/api/v2/search';

    public const DISTRIBUTOR_NAME = 'Mouser';

    public function __construct(
        private readonly HttpClientInterface $mouserClient,
        private readonly MouserSettings $settings,
    ) {
    }

    public function getProviderInfo(): array
    {
        return [
            'name' => 'Mouser',
            'description' => 'This provider uses the Mouser API to search for parts.',
            'url' => 'https://www.mouser.com/',
            'disabled_help' => 'Configure the API key in the PROVIDER_MOUSER_KEY environment variable to enable.'
        ];
    }

    public function getProviderKey(): string
    {
        return 'mouser';
    }

    public function isActive(): bool
    {
        return $this->settings->apiKey !== '' && $this->settings->apiKey !== null;
    }

    public function searchByKeyword(string $keyword): array
    {
        /*
        SearchByKeywordRequest description:
        Search parts by keyword and return a maximum of 50 parts.

        keyword*	string
        Used for keyword part search.

        records	integer($int32)
        Used to specify how many records the method should return.

        startingRecord	integer($int32)
        Indicates where in the total recordset the return set should begin.
        From the startingRecord, the number of records specified will be returned up to the end of the recordset.
        This is useful for paging through the complete recordset of parts matching keyword.


        searchOptions	string
        Optional.
        If not provided, the default is None.
        Refers to options supported by the search engine.
        Only one value at a time is supported.
        Available options: None | Rohs | InStock | RohsAndInStock - can use string representations or integer IDs: 1[None] | 2[Rohs] | 4[InStock] | 8[RohsAndInStock].

        searchWithYourSignUpLanguage	string
        Optional.
        If not provided, the default is false.
        Used when searching for keywords in the language specified when you signed up for Search API.
        Can use string representation: true.
            {
            "SearchByKeywordRequest": {
                "keyword": "BC557",
                "records": 0,
                "startingRecord": 0,
                "searchOptions": "",
                "searchWithYourSignUpLanguage": ""
                }
            }
    */

        $response = $this->mouserClient->request('POST', self::ENDPOINT_URL."/keyword", [
            'query' => [
                'apiKey' => $this->settings->apiKey
            ],
            'json' => [
                'SearchByKeywordRequest' => [
                    'keyword' => $keyword,
                    'records' => $this->settings->searchLimit, //self::NUMBER_OF_RESULTS,
                    'startingRecord' => 0,
                    'searchOptions' => $this->settings->searchOption->value,
                    'searchWithYourSignUpLanguage' => $this->settings->searchWithSignUpLanguage ? 'true' : 'false',
                ]
            ],
        ]);

        return $this->responseToDTOArray($response);
    }

    public function getDetails(string $id): PartDetailDTO
    {
        /*
            SearchByPartRequest description:
            Search parts by part number and return a maximum of 50 parts.

            mouserPartNumber	string
            Used to search parts by the specific Mouser part number with a maximum input of 10 part numbers, separated by a pipe symbol for the search.
            Each part number must be a minimum of 3 characters and a maximum of 40 characters. For example: 494-JANTX2N2222A|610-2N2222-TL|637-2N2222A

            partSearchOptions	string
            Optional.
            If not provided, the default is None. Refers to options supported by the search engine. Only one value at a time is supported.
            The following values are valid: None | Exact - can use string representations or integer IDs: 1[None] | 2[Exact]

            {
                "SearchByPartRequest": {
                "mouserPartNumber": "string",
                "partSearchOptions": "string"
                }
            }
        */

        $response = $this->mouserClient->request('POST', self::ENDPOINT_URL."/partnumber", [
            'query' => [
                'apiKey' => $this->settings->apiKey,
            ],
            'json' => [
                'SearchByPartRequest' => [
                    'mouserPartNumber' => $id,
                    'partSearchOptions' => 2
                ]
            ],
        ]);
        $tmp = $this->responseToDTOArray($response);

        //Ensure that we have exactly one result
        if (count($tmp) === 0) {
            throw new \RuntimeException('No part found with ID '.$id);
        }

        //Manually filter out the part with the correct ID
        $tmp = array_filter($tmp, fn(PartDetailDTO $part) => $part->provider_id === $id);
        if (count($tmp) === 0) {
            throw new \RuntimeException('No part found with ID '.$id);
        }
        if (count($tmp) > 1) {
            throw new \RuntimeException('Multiple parts found with ID '.$id);
        }

        return reset($tmp);
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


    /**
     * @param  ResponseInterface  $response
     * @return PartDetailDTO[]
     */
    private function responseToDTOArray(ResponseInterface $response): array
    {
        $arr = $response->toArray();

        if (isset($arr['SearchResults'])) {
            $products = $arr['SearchResults']['Parts'] ?? [];
        } else {
            throw new \RuntimeException('Unknown response format: ' .json_encode($arr, JSON_THROW_ON_ERROR));
        }

        $result = [];
        foreach ($products as $product) {

            //Check if we have a valid product number. We assume that a product number, must have at least 4 characters
            //Otherwise filter it out
            if (strlen($product['MouserPartNumber']) < 4) {
                continue;
            }

            //Check if we have a mass field available
            $mass = null;
            if (isset($product['UnitWeightKg']['UnitWeight'])) {
                $mass = (float) $product['UnitWeightKg']['UnitWeight'];
                //The mass is given in kg, we want it in g
                $mass *= 1000;
            }


            $result[] = new PartDetailDTO(
                provider_key: $this->getProviderKey(),
                provider_id: $product['MouserPartNumber'],
                name: $product['ManufacturerPartNumber'],
                description: $product['Description'],
                category: $product['Category'],
                manufacturer: $product['Manufacturer'],
                mpn: $product['ManufacturerPartNumber'],
                preview_image_url: $product['ImagePath'],
                manufacturing_status: $this->releaseStatusCodeToManufacturingStatus(
                    $product['LifecycleStatus'] ?? null,
                    (int) ($product['AvailabilityInStock'] ?? 0)
                ),
                provider_url: $product['ProductDetailUrl'],
                datasheets: $this->parseDataSheets($product['DataSheetUrl'] ?? null,
                    $product['MouserPartNumber'] ?? null),
                vendor_infos: $this->pricingToDTOs($product['PriceBreaks'] ?? [], $product['MouserPartNumber'],
                    $product['ProductDetailUrl']),
                mass: $mass,
            );
        }
        return $result;
    }


    private function parseDataSheets(?string $sheetUrl, ?string $sheetName): ?array
    {
        if ($sheetUrl === null || $sheetUrl === '' || $sheetUrl === '0') {
            return null;
        }
        $result = [];
        $result[] = new FileDTO(url: $sheetUrl, name: $sheetName);
        return $result;
    }

    /*
    * Mouser API price is a string in the form "n[.,]nnn[.,] currency"
    * then this convert it to a number
    * Austria has a format like "€ 2,10"
    */
    private function priceStrToFloat($val): float
    {
        //Remove any character that is not a number, dot or comma (like currency symbols)
        $val = preg_replace('/[^0-9.,]/', '', $val);

        //Trim the string
        $val = trim($val);

        //Convert commas to dots
        $val = str_replace(",", ".", $val);
        //Remove any dot that is not the last one (to avoid problems with thousands separators)
        $val = preg_replace('/\.(?=.*\.)/', '', $val);
        return (float)$val;
    }

    /**
     * Converts the pricing (StandardPricing field) from the Mouser API to an array of PurchaseInfoDTOs
     * @param  array  $price_breaks
     * @param  string  $order_number
     * @param  string  $product_url
     * @return PurchaseInfoDTO[]
     */
    private function pricingToDTOs(array $price_breaks, string $order_number, string $product_url): array
    {
        $prices = [];

        foreach ($price_breaks as $price_break) {
            $number = $this->priceStrToFloat($price_break['Price']);
            $prices[] = new PriceDTO(
                minimum_discount_amount: $price_break['Quantity'],
                price: (string)$number,
                currency_iso_code: $price_break['Currency']
            );
        }

        return [
            new PurchaseInfoDTO(distributor_name: self::DISTRIBUTOR_NAME, order_number: $order_number, prices: $prices,
                product_url: $product_url)
        ];
    }


    /* Converts the product status from the MOUSER API to the manufacturing status used in Part-DB:
        Factory Special Order - Ordine speciale in fabbrica
        Not Recommended for New Designs - Non raccomandato per nuovi progetti
        New Product - Nuovo prodotto
        End of Life - Fine vita
        -vuoto-  - Attivo

        TODO: Probably need to review the values of field Lifecyclestatus
    */
    /**
     * Converts the lifecycle status from the Mouser API to a ManufacturingStatus
     * @param  string|null  $productStatus The lifecycle status from the Mouser API
     * @param  int  $availableInStock The number of parts available in stock
     * @return ManufacturingStatus|null
     */
    private function releaseStatusCodeToManufacturingStatus(?string $productStatus, int $availableInStock = 0): ?ManufacturingStatus
    {
        $tmp = match ($productStatus) {
            null => null,
            "New Product" => ManufacturingStatus::ANNOUNCED,
            "Not Recommended for New Designs" => ManufacturingStatus::NRFND,
            "Factory Special Order", "Obsolete" => ManufacturingStatus::DISCONTINUED,
            "End of Life" => ManufacturingStatus::EOL,
            default => ManufacturingStatus::ACTIVE,
        };

        //If the part would be assumed to be announced, check if it is in stock, then it is active
        if ($tmp === ManufacturingStatus::ANNOUNCED && $availableInStock > 0) {
            $tmp = ManufacturingStatus::ACTIVE;
        }

        return $tmp;
    }
}