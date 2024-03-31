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
use App\Services\OAuth\OAuthTokenManager;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class DigikeyProvider implements InfoProviderInterface
{

    private const OAUTH_APP_NAME = 'ip_digikey_oauth';

    //Sandbox:'https://sandbox-api.digikey.com'; (you need to change it in knpu/oauth2-client-bundle config too)
    private const BASE_URI = 'https://api.digikey.com';

    private const VENDOR_NAME = 'DigiKey';

    private readonly HttpClientInterface $digikeyClient;


    public function __construct(HttpClientInterface $httpClient, private readonly OAuthTokenManager $authTokenManager,
        private readonly string $currency, private readonly string $clientId,
        private readonly string $language, private readonly string $country)
    {
        //Create the HTTP client with some default options
        $this->digikeyClient = $httpClient->withOptions([
            "base_uri" => self::BASE_URI,
            "headers" => [
                "X-DIGIKEY-Client-Id" => $clientId,
                "X-DIGIKEY-Locale-Site" => $this->country,
                "X-DIGIKEY-Locale-Language" => $this->language,
                "X-DIGIKEY-Locale-Currency" => $this->currency,
                "X-DIGIKEY-Customer-Id" => 0,
            ]
        ]);
    }

    public function getProviderInfo(): array
    {
        return [
            'name' => 'DigiKey',
            'description' => 'This provider uses the DigiKey API to search for parts.',
            'url' => 'https://www.digikey.com/',
            'oauth_app_name' => self::OAUTH_APP_NAME,
            'disabled_help' => 'Set the PROVIDER_DIGIKEY_CLIENT_ID and PROVIDER_DIGIKEY_SECRET env option and connect OAuth to enable.'
        ];
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

    public function getProviderKey(): string
    {
        return 'digikey';
    }

    public function isActive(): bool
    {
        //The client ID has to be set and a token has to be available (user clicked connect)
        return !empty($this->clientId) && $this->authTokenManager->hasToken(self::OAUTH_APP_NAME);
    }

    public function searchByKeyword(string $keyword): array
    {
        $request = [
            'Keywords' => $keyword,
            'RecordCount' => 50,
            'RecordStartPosition' => 0,
            'ExcludeMarketPlaceProducts' => 'true',
        ];

        $response = $this->digikeyClient->request('POST', '/Search/v3/Products/Keyword', [
            'json' => $request,
            'auth_bearer' => $this->authTokenManager->getAlwaysValidTokenString(self::OAUTH_APP_NAME)
        ]);

        $response_array = $response->toArray();


        $result = [];
        $products = $response_array['Products'];
        foreach ($products as $product) {
            $result[] = new SearchResultDTO(
                provider_key: $this->getProviderKey(),
                provider_id: $product['DigiKeyPartNumber'],
                name: $product['ManufacturerPartNumber'],
                description: $product['DetailedDescription'] ?? $product['ProductDescription'],
                category: $this->getCategoryString($product),
                manufacturer: $product['Manufacturer']['Value'] ?? null,
                mpn: $product['ManufacturerPartNumber'],
                preview_image_url: $product['PrimaryPhoto'] ?? null,
                manufacturing_status: $this->productStatusToManufacturingStatus($product['ProductStatus']),
                provider_url: $product['ProductUrl'],
            );
        }

        return $result;
    }

    public function getDetails(string $id): PartDetailDTO
    {
        $response = $this->digikeyClient->request('GET', '/Search/v3/Products/' . urlencode($id), [
            'auth_bearer' => $this->authTokenManager->getAlwaysValidTokenString(self::OAUTH_APP_NAME)
        ]);

        $product = $response->toArray();

        $footprint = null;
        $parameters = $this->parametersToDTOs($product['Parameters'] ?? [], $footprint);
        $media = $this->mediaToDTOs($product['MediaLinks']);

        return new PartDetailDTO(
            provider_key: $this->getProviderKey(),
            provider_id: $product['DigiKeyPartNumber'],
            name: $product['ManufacturerPartNumber'],
            description: $product['DetailedDescription'] ?? $product['ProductDescription'],
            category: $this->getCategoryString($product),
            manufacturer: $product['Manufacturer']['Value'] ?? null,
            mpn: $product['ManufacturerPartNumber'],
            preview_image_url: $product['PrimaryPhoto'] ?? null,
            manufacturing_status: $this->productStatusToManufacturingStatus($product['ProductStatus']),
            provider_url: $product['ProductUrl'],
            footprint: $footprint,
            datasheets: $media['datasheets'],
            images: $media['images'],
            parameters: $parameters,
            vendor_infos: $this->pricingToDTOs($product['StandardPricing'] ?? [], $product['DigiKeyPartNumber'], $product['ProductUrl']),
        );
    }

    /**
     * Converts the product status from the Digikey API to the manufacturing status used in Part-DB
     * @param  string|null  $dk_status
     * @return ManufacturingStatus|null
     */
    private function productStatusToManufacturingStatus(?string $dk_status): ?ManufacturingStatus
    {
        return match ($dk_status) {
            null => null,
            'Active' => ManufacturingStatus::ACTIVE,
            'Obsolete' => ManufacturingStatus::DISCONTINUED,
            'Discontinued at Digi-Key', 'Last Time Buy' => ManufacturingStatus::EOL,
            'Not For New Designs' => ManufacturingStatus::NRFND,
            'Preliminary' => ManufacturingStatus::ANNOUNCED,
            default => ManufacturingStatus::NOT_SET,
        };
    }

    private function getCategoryString(array $product): string
    {
        $category = $product['Category']['Value'];
        $sub_category = $product['Family']['Value'];

        //Replace the  ' - ' category separator with ' -> '
        $sub_category = str_replace(' - ', ' -> ', $sub_category);

        return $category . ' -> ' . $sub_category;
    }

    /**
     * This function converts the "Parameters" part of the Digikey API response to an array of ParameterDTOs
     * @param  array  $parameters
     * @param string|null  $footprint_name You can pass a variable by reference, where the name of the footprint will be stored
     * @return ParameterDTO[]
     */
    private function parametersToDTOs(array $parameters, string|null &$footprint_name = null): array
    {
        $results = [];

        $footprint_name = null;

        foreach ($parameters as $parameter) {
            if ($parameter['ParameterId'] === 1291) { //Meaning "Manufacturer given footprint"
                $footprint_name = $parameter['Value'];
            }

            if (in_array(trim($parameter['Value']), array('', '-'), true)) {
                continue;
            }

            $results[] = ParameterDTO::parseValueIncludingUnit($parameter['Parameter'], $parameter['Value']);
        }

        return $results;
    }

    /**
     * Converts the pricing (StandardPricing field) from the Digikey API to an array of PurchaseInfoDTOs
     * @param  array  $price_breaks
     * @param  string  $order_number
     * @param  string  $product_url
     * @return PurchaseInfoDTO[]
     */
    private function pricingToDTOs(array $price_breaks, string $order_number, string $product_url): array
    {
        $prices = [];

        foreach ($price_breaks as $price_break) {
            $prices[] = new PriceDTO(minimum_discount_amount:  $price_break['BreakQuantity'], price: (string) $price_break['UnitPrice'], currency_iso_code: $this->currency);
        }

        return [
            new PurchaseInfoDTO(distributor_name: self::VENDOR_NAME, order_number: $order_number, prices: $prices, product_url: $product_url)
        ];
    }

    /**
     * @param  array  $media_links
     * @return FileDTO[][]
     * @phpstan-return array<string, FileDTO[]>
     */
    private function mediaToDTOs(array $media_links): array
    {
        $datasheets = [];
        $images = [];

        foreach ($media_links as $media_link) {
            $file = new FileDTO(url: $media_link['Url'], name: $media_link['Title']);

            switch ($media_link['MediaType']) {
                case 'Datasheets':
                    $datasheets[] = $file;
                    break;
                case 'Product Photos':
                    $images[] = $file;
                    break;
            }
        }

        return [
            'datasheets' => $datasheets,
            'images' => $images,
        ];
    }

}
