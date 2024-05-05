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

declare(strict_types=1);


namespace App\Services\InfoProviderSystem\Providers;

use App\Entity\Parts\ManufacturingStatus;
use App\Services\InfoProviderSystem\DTOs\FileDTO;
use App\Services\InfoProviderSystem\DTOs\ParameterDTO;
use App\Services\InfoProviderSystem\DTOs\PartDetailDTO;
use App\Services\InfoProviderSystem\DTOs\PriceDTO;
use App\Services\InfoProviderSystem\DTOs\PurchaseInfoDTO;
use App\Services\InfoProviderSystem\DTOs\SearchResultDTO;
use App\Settings\InfoProviderSystem\TMESettings;

class TMEProvider implements InfoProviderInterface
{

    private const VENDOR_NAME = 'TME';

    public function __construct(private readonly TMEClient $tmeClient, private readonly TMESettings $settings)
    {

    }

    public function getProviderInfo(): array
    {
        return [
            'name' => 'TME',
            'description' => 'This provider uses the API of TME (Transfer Multipart).',
            'url' => 'https://tme.eu/',
            'disabled_help' => 'Configure the PROVIDER_TME_KEY and PROVIDER_TME_SECRET environment variables to use this provider.'
        ];
    }

    public function getProviderKey(): string
    {
        return 'tme';
    }

    public function isActive(): bool
    {
        return $this->tmeClient->isUsable();
    }

    public function searchByKeyword(string $keyword): array
    {
        $response = $this->tmeClient->makeRequest('Products/Search', [
            'Country' => $this->settings->country,
            'Language' => $this->settings->language,
            'SearchPlain' => $keyword,
        ]);

        $data = $response->toArray()['Data'];

        $result = [];

        foreach($data['ProductList'] as $product) {
            $result[] = new SearchResultDTO(
                provider_key: $this->getProviderKey(),
                provider_id: $product['Symbol'],
                name: !empty($product['OriginalSymbol']) ? $product['OriginalSymbol'] : $product['Symbol'],
                description: $product['Description'],
                category: $product['Category'],
                manufacturer: $product['Producer'],
                mpn: $product['OriginalSymbol'] ?? null,
                preview_image_url: $this->normalizeURL($product['Photo']),
                manufacturing_status: $this->productStatusArrayToManufacturingStatus($product['ProductStatusList']),
                provider_url: $this->normalizeURL($product['ProductInformationPage']),
            );
        }

        return $result;
    }

    public function getDetails(string $id): PartDetailDTO
    {
        $response = $this->tmeClient->makeRequest('Products/GetProducts', [
            'Country' => $this->settings->country,
            'Language' => $this->settings->language,
            'SymbolList' => [$id],
        ]);

        $product = $response->toArray()['Data']['ProductList'][0];

        //Add a explicit https:// to the url if it is missing
        $productInfoPage = $this->normalizeURL($product['ProductInformationPage']);

        $files = $this->getFiles($id);

        $footprint = null;

        $parameters = $this->getParameters($id, $footprint);

        return new PartDetailDTO(
            provider_key: $this->getProviderKey(),
            provider_id: $product['Symbol'],
            name:  !empty($product['OriginalSymbol']) ? $product['OriginalSymbol'] : $product['Symbol'],
            description: $product['Description'],
            category: $product['Category'],
            manufacturer: $product['Producer'],
            mpn: $product['OriginalSymbol'] ?? null,
            preview_image_url: $this->normalizeURL($product['Photo']),
            manufacturing_status: $this->productStatusArrayToManufacturingStatus($product['ProductStatusList']),
            provider_url: $productInfoPage,
            footprint: $footprint,
            datasheets: $files['datasheets'],
            images: $files['images'],
            parameters: $parameters,
            vendor_infos: [$this->getVendorInfo($id, $productInfoPage)],
            mass: $product['WeightUnit'] === 'g' ? $product['Weight'] : null,
        );
    }

    /**
     * Fetches all files for a given product id
     * @param  string  $id
     * @return array<string, list<FileDTO>> An array with the keys 'datasheet'
     * @phpstan-return array{datasheets: list<FileDTO>, images: list<FileDTO>}
     */
    public function getFiles(string $id): array
    {
        $response = $this->tmeClient->makeRequest('Products/GetProductsFiles', [
            'Country' => $this->settings->country,
            'Language' => $this->settings->language,
            'SymbolList' => [$id],
        ]);

        $data = $response->toArray()['Data'];
        $files = $data['ProductList'][0]['Files'];

        //Extract datasheets
        $documentList = $files['DocumentList'];
        $datasheets = [];
        foreach($documentList as $document) {
            $datasheets[] = new FileDTO(
                url: $this->normalizeURL($document['DocumentUrl']),
            );
        }

        //Extract images
        $imageList = $files['AdditionalPhotoList'];
        $images = [];
        foreach($imageList as $image) {
            $images[] = new FileDTO(
                url: $this->normalizeURL($image['HighResolutionPhoto']),
            );
        }


        return [
            'datasheets' => $datasheets,
            'images' => $images,
        ];
    }

    /**
     * Fetches the vendor/purchase information for a given product id.
     * @param  string  $id
     * @param  string|null  $productURL
     * @return PurchaseInfoDTO
     */
    public function getVendorInfo(string $id, ?string $productURL = null): PurchaseInfoDTO
    {
        $response = $this->tmeClient->makeRequest('Products/GetPricesAndStocks', [
            'Country' => $this->settings->country,
            'Language' => $this->settings->language,
            'Currency' => $this->settings->currency,
            'GrossPrices' => $this->settings->grossPrices,
            'SymbolList' => [$id],
        ]);

        $data = $response->toArray()['Data'];
        $currency = $data['Currency'];
        $include_tax = $data['PriceType'] === 'GROSS';


        $product = $response->toArray()['Data']['ProductList'][0];
        $vendor_order_number = $product['Symbol'];
        $priceList = $product['PriceList'];

        $prices = [];
        foreach ($priceList as $price) {
            $prices[] = new PriceDTO(
                minimum_discount_amount: $price['Amount'],
                price: (string) $price['PriceValue'],
                currency_iso_code: $currency,
                includes_tax: $include_tax,
            );
        }

        return new PurchaseInfoDTO(
            distributor_name: self::VENDOR_NAME,
            order_number:  $vendor_order_number,
            prices:  $prices,
            product_url: $productURL,
        );
    }

    /**
     * Fetches the parameters of a product
     * @param  string  $id
     * @param string|null  $footprint_name You can pass a variable by reference, where the name of the footprint will be stored
     * @return ParameterDTO[]
     */
    public function getParameters(string $id, string|null &$footprint_name = null): array
    {
        $response = $this->tmeClient->makeRequest('Products/GetParameters', [
            'Country' => $this->settings->country,
            'Language' => $this->settings->language,
            'SymbolList' => [$id],
        ]);

        $data = $response->toArray()['Data']['ProductList'][0];

        $result = [];

        $footprint_name = null;

        foreach($data['ParameterList'] as $parameter) {
            $result[] = ParameterDTO::parseValueIncludingUnit($parameter['ParameterName'], $parameter['ParameterValue']);

            //Check if the parameter is the case/footprint
            if ($parameter['ParameterId'] === 35) {
                $footprint_name = $parameter['ParameterValue'];
            }
        }

        return $result;
    }

    /**
     * Convert the array of product statuses to a single manufacturing status
     * @param  array  $statusArray
     * @return ManufacturingStatus
     */
    private function productStatusArrayToManufacturingStatus(array $statusArray): ManufacturingStatus
    {
        if (in_array('AVAILABLE_WHILE_STOCKS_LAST', $statusArray, true)) {
            return ManufacturingStatus::EOL;
        }

        if (in_array('INVALID', $statusArray, true)) {
            return ManufacturingStatus::DISCONTINUED;
        }

        //By default we assume that the part is active
        return ManufacturingStatus::ACTIVE;
    }



    private function normalizeURL(string $url): string
    {
        //If a URL starts with // we assume that it is a relative URL and we add the protocol
        if (str_starts_with($url, '//')) {
            return 'https:' . $url;
        }

        return $url;
    }

    public function getCapabilities(): array
    {
        return [
            ProviderCapabilities::BASIC,
            ProviderCapabilities::FOOTPRINT,
            ProviderCapabilities::PICTURE,
            ProviderCapabilities::DATASHEET,
            ProviderCapabilities::PRICE,
        ];
    }
}