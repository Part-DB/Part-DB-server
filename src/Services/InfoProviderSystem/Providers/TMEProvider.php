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
use App\Services\InfoProviderSystem\DTOs\ProviderInfoDTO;
use App\Services\InfoProviderSystem\DTOs\PurchaseInfoDTO;
use App\Services\InfoProviderSystem\DTOs\SearchResultDTO;
use App\Settings\InfoProviderSystem\TMESettings;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class TMEProvider implements InfoProviderInterface, URLHandlerInfoProviderInterface
{
    public const PROVIDER_KEY = 'tme';

    private const VENDOR_NAME = 'TME';

    private const VALID_DOCUMENT_TYPES = ['INS', 'DTE', 'KCH', 'GWA', 'INB', 'PRE'];

    public function __construct(private readonly TMEClient $tmeClient, private readonly TMESettings $settings,
        private readonly HttpClientInterface $httpClient)
    {
    }

    public function getProviderInfo(): ProviderInfoDTO
    {
        return new ProviderInfoDTO(
            key: self::PROVIDER_KEY,
            name: 'TME',
            description: 'This provider uses the API of TME (Transfer Multipart).',
            url: 'https://tme.eu/',
            disabledHelp: 'Configure the API Token and secret in provider settings to use this provider.',
            settingsClass: TMESettings::class,
            capabilities: [
                ProviderCapabilities::BASIC,
                ProviderCapabilities::FOOTPRINT,
                ProviderCapabilities::PICTURE,
                ProviderCapabilities::DATASHEET,
                ProviderCapabilities::PRICE,
                ProviderCapabilities::PARAMETERS
            ],
        );
    }

    public function isActive(): bool
    {
        return $this->tmeClient->isUsable();
    }

    /**
     * Converts a product id to a product URL
     * @param  string  $productId
     * @return string
     */
    private function productIDToProductURL(string $productId): string
    {
        return 'https://www.tme.eu/' . strtolower($this->settings->country) . '/' . strtolower($this->settings->language) . '/details/' . $productId . '/';
    }

    public function searchByKeyword(string $keyword, array $options = []): array
    {
        $response = $this->tmeClient->makeRequest('products/search', [
            'country' => $this->settings->country,
            'phrase' => $keyword,
            'sort' => [
                'property' => 'ACCURACY',
                'direction' => 'desc',
            ],
            'scope' => ['products'],
        ]);

        $data = $response->toArray()['data'];

        $result = [];

        foreach ($data['products']['elements'] as $product) {
            $result[] = new SearchResultDTO(
                provider_key: self::PROVIDER_KEY,
                provider_id: $product['symbol'],
                name: $product['manufacturer_symbols'][0] ?? $product['symbol'],
                description: $product['description'],
                category: $product['category']['name'],
                manufacturer: $product['manufacturer']['name'] ?? null,
                mpn: $product['manufacturer_symbols'][0] ?? null,
                preview_image_url: $this->normalizeURL($product['assets']['primary_photo']['prime'] ?? null),
                manufacturing_status: $this->productStatusArrayToManufacturingStatus($product['product_status'] ?? []),
                provider_url: $this->productIDToProductURL($product['symbol']),
                gtin: $product['ean'] ?? null
            );
        }

        return $result;
    }

    public function getDetails(string $id, array $options = []): PartDetailDTO
    {
        $response = $this->tmeClient->makeRequest('products', [
            'country' => $this->settings->country,
            'symbols' => [$id],
        ]);

        $product = $response->toArray()['data']['elements'][0];

        $productInfoPage = $this->productIDToProductURL($product['symbol']);

        $files = $this->getFiles($id);

        $footprint = null;

        $parameters = $this->getParameters($id, $footprint);

        return new PartDetailDTO(
            provider_key: self::PROVIDER_KEY,
            provider_id: $product['symbol'],
            name: $product['manufacturer_symbols'][0] ?? $product['symbol'],
            description: $product['description'],
            category: $product['category']['name'],
            manufacturer: $product['manufacturer']['name'] ?? null,
            mpn: $product['manufacturer_symbols'][0] ?? null,
            preview_image_url: $this->normalizeURL($product['assets']['primary_photo']['prime'] ?? null),
            manufacturing_status: $this->productStatusArrayToManufacturingStatus($product['product_status'] ?? []),
            provider_url: $this->productIDToProductURL($product['symbol']),
            footprint: $footprint,
            gtin: $product['ean'] ?? null,
            datasheets: $files['datasheets'],
            images: $files['images'],
            parameters: $parameters,
            vendor_infos: [$this->getVendorInfo($id, $productInfoPage)],
            mass: ($product['weight']['unit'] ?? null) === 'g' ? ($product['weight']['value'] ?? null) : null,
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
        $response = $this->tmeClient->makeRequest('products/files', [
            'country' => $this->settings->country,
            'symbols' => [$id],
        ]);

        $element = $response->toArray()['data']['elements'][0];

        $datasheets = [];
        foreach ($element['documents']['elements'] ?? [] as $document) {
            if (in_array($document['type'], self::VALID_DOCUMENT_TYPES, true)) {
                $datasheets[] = new FileDTO(
                    url: $this->normalizeURL($document['url']),
                    name: $document['file_name'] ?? null,
                );
            }
        }

        $images = [];
        foreach ($element['assets']['additional']['elements'] ?? [] as $photo) {
            $images[] = new FileDTO(
                url: $this->normalizeURL($image['high_resolution'] ?? $photo['prime']),
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
        $response = $this->tmeClient->makeRequest('products/data', [
            'country' => $this->settings->country,
            'currency' => $this->settings->currency,
            'scope' => ['prices'],
            'symbols' => [$id],
        ]);

        $product = $response->toArray()['data']['elements'][0];
        $priceData = $product['prices'];
        $currency = $priceData['currency'];
        $include_tax = strtoupper($priceData['type'] ?? '') === 'GROSS';


        $vendor_order_number = $product['symbol'];
        $priceList = $priceData['elements'] ?? [];

        $prices = [];
        foreach ($priceList as $price) {
            $prices[] = new PriceDTO(
                minimum_discount_amount: $price['amount'],
                price: (string) $price['price'],
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
        $response = $this->tmeClient->makeRequest('products/parameters', [
            'country' => $this->settings->country,
            'symbols' => [$id],
        ]);

        $element = $response->toArray()['data']['elements'][0]['parameters'];

        $result = [];

        $footprint_name = null;

        foreach ($element['elements'] as $parameter) {
            //Check if the parameter is the case/footprint
            if ($parameter['id'] === 35) {
                $footprint_name = $parameter['values'][0]['value'];
            }

            //Skip related items parameter
            if ($parameter['id'] === 1605) {
                continue;
            }

            if (count($parameter['values']) > 1) {
                //Concatenate all values with a comma, if there are multiple values for the same parameter
                $value = implode(', ', array_map(fn($v) => $v['value'], $parameter['values']));
                $result[] = new ParameterDTO(
                    name: $parameter['name'],
                    value_text: $value,
                );
            } else if (count($parameter['values']) === 1) {
                $result[] = ParameterDTO::parseValueIncludingUnit($parameter['name'], $parameter['values'][0]['value']);

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

        if (in_array('NOT_IN_OFFER', $statusArray, true)) {
            return ManufacturingStatus::DISCONTINUED;
        }

        //By default we assume that the part is active
        return ManufacturingStatus::ACTIVE;
    }



    private function normalizeURL(?string $url): ?string
    {
        if ($url === null) {
            return null;
        }

        //If a URL starts with // we assume that it is a relative URL and we add the protocol
        if (str_starts_with($url, '//')) {
            $url = 'https:' . $url;
        }

        //Encode bare % signs that are not already part of a valid percent-encoded sequence
        //Fixes part numbers with % in them e.g. SMD0603-5K1-1%
        $url = preg_replace('/%(?![0-9A-Fa-f]{2})/', '%25', $url);

        return $url;
    }

    public function getHandledDomains(): array
    {
        return ['tme.eu'];
    }

    public function getIDFromURL(string $url): ?string
    {
        //Input: https://www.tme.eu/de/details/fi321_se/kuhler/alutronic/
        //The ID is the part after the details segment and before the next slash

        $matches = [];
        if (preg_match('#/details/([^/]+)/#', $url, $matches) === 1) {
            return $matches[1];
        }

        return null;
    }
}
