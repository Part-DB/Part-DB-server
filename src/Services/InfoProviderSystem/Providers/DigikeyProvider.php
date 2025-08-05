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

    /**
     * A list of parameter IDs, that are always assumed as text only and will never be converted to a numerical value.
     * This allows to fix issues like #682, where the "Supplier Device Package" was parsed as a numerical value.
     */
    private const TEXT_ONLY_PARAMETERS = [
        1291, //Supplier Device Package
        39246, //Package / Case
    ];

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
        return $this->clientId !== '' && $this->authTokenManager->hasToken(self::OAUTH_APP_NAME);
    }

    public function searchByKeyword(string $keyword): array
    {
        $request = [
            'Keywords' => $keyword,
            'Limit' => 50,
            'Offset' => 0,
            'FilterOptionsRequest' => [
                'MarketPlaceFilter' => 'ExcludeMarketPlace',
            ],
        ];

        //$response = $this->digikeyClient->request('POST', '/Search/v3/Products/Keyword', [
        $response = $this->digikeyClient->request('POST', '/products/v4/search/keyword', [
            'json' => $request,
            'auth_bearer' => $this->authTokenManager->getAlwaysValidTokenString(self::OAUTH_APP_NAME)
        ]);

        $response_array = $response->toArray();


        $result = [];
        $products = $response_array['Products'];
        foreach ($products as $product) {
            foreach ($product['ProductVariations'] as $variation) {
                $result[] = new SearchResultDTO(
                    provider_key: $this->getProviderKey(),
                    provider_id: $variation['DigiKeyProductNumber'],
                    name: $product['ManufacturerProductNumber'],
                    description: $product['Description']['DetailedDescription'] ?? $product['Description']['ProductDescription'],
                    category: $this->getCategoryString($product),
                    manufacturer: $product['Manufacturer']['Name'] ?? null,
                    mpn: $product['ManufacturerProductNumber'],
                    preview_image_url: $product['PhotoUrl'] ?? null,
                    manufacturing_status: $this->productStatusToManufacturingStatus($product['ProductStatus']['Id']),
                    provider_url: $product['ProductUrl'],
                    footprint: $variation['PackageType']['Name'], //Use the footprint field, to show the user the package type (Tape & Reel, etc., as digikey has many different package types)
                );
            }
        }

        return $result;
    }

    public function getDetails(string $id): PartDetailDTO
    {
        $response = $this->digikeyClient->request('GET', '/products/v4/search/' . urlencode($id) . '/productdetails', [
            'auth_bearer' => $this->authTokenManager->getAlwaysValidTokenString(self::OAUTH_APP_NAME)
        ]);

        $response_array = $response->toArray();
        $product = $response_array['Product'];

        $footprint = null;
        $parameters = $this->parametersToDTOs($product['Parameters'] ?? [], $footprint);
        $media = $this->mediaToDTOs($id);

        // Get the price_breaks of the selected variation
        $price_breaks = [];
        foreach ($product['ProductVariations'] as $variation) {
            if ($variation['DigiKeyProductNumber'] == $id) {
                $price_breaks = $variation['StandardPricing'] ?? [];
                break;
            }
        }

        return new PartDetailDTO(
            provider_key: $this->getProviderKey(),
            provider_id: $id,
            name: $product['ManufacturerProductNumber'],
            description: $product['Description']['DetailedDescription'] ?? $product['Description']['ProductDescription'],
            category: $this->getCategoryString($product),
            manufacturer: $product['Manufacturer']['Name'] ?? null,
            mpn: $product['ManufacturerProductNumber'],
            preview_image_url: $product['PhotoUrl'] ?? null,
            manufacturing_status: $this->productStatusToManufacturingStatus($product['ProductStatus']['Id']),
            provider_url: $product['ProductUrl'],
            footprint: $footprint,
            datasheets: $media['datasheets'],
            images: $media['images'],
            parameters: $parameters,
            vendor_infos: $this->pricingToDTOs($price_breaks, $id, $product['ProductUrl']),
        );
    }

    /**
     * Converts the product status from the Digikey API to the manufacturing status used in Part-DB
     * @param  int|null  $dk_status
     * @return ManufacturingStatus|null
     */
    private function productStatusToManufacturingStatus(?int $dk_status): ?ManufacturingStatus
    {
        // The V4 can use strings to get the status, but if you have changed the PROVIDER_DIGIKEY_LANGUAGE it will not match.
        // Using the Id instead which should be fixed.
        //
        // The API is not well documented and the ID are not there yet, so were extracted using "trial and error".
        // The 'Preliminary' id was not found in several categories so I was unable to extract it. Disabled for now.
        return match ($dk_status) {
            null => null,
            0 => ManufacturingStatus::ACTIVE,
            1 => ManufacturingStatus::DISCONTINUED,
            2, 4 => ManufacturingStatus::EOL,
            7 => ManufacturingStatus::NRFND,
            //'Preliminary' => ManufacturingStatus::ANNOUNCED,
            default => ManufacturingStatus::NOT_SET,
        };
    }

    private function getCategoryString(array $product): string
    {
        $category = $product['Category']['Name'];
        $sub_category = current($product['Category']['ChildCategories']);

        if ($sub_category) {
            //Replace the  ' - ' category separator with ' -> '
            $category = $category . ' -> ' . str_replace(' - ', ' -> ', $sub_category["Name"]);
        }

        return $category;
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
                $footprint_name = $parameter['ValueText'];
            }

            if (in_array(trim((string) $parameter['ValueText']), ['', '-'], true)) {
                continue;
            }

            //If the parameter was marked as text only, then we do not try to parse it as a numerical value
            if (in_array($parameter['ParameterId'], self::TEXT_ONLY_PARAMETERS, true)) {
                $results[] = new ParameterDTO(name: $parameter['ParameterText'], value_text: $parameter['ValueText']);
            } else { //Otherwise try to parse it as a numerical value
                $results[] = ParameterDTO::parseValueIncludingUnit($parameter['ParameterText'], $parameter['ValueText']);
            }
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
     * @param  string  $id The Digikey product number, to get the media for
     * @return FileDTO[][]
     * @phpstan-return array<string, FileDTO[]>
     */
    private function mediaToDTOs(string $id): array
    {
        $datasheets = [];
        $images = [];

        $response = $this->digikeyClient->request('GET', '/products/v4/search/' . urlencode($id) . '/media', [
            'auth_bearer' => $this->authTokenManager->getAlwaysValidTokenString(self::OAUTH_APP_NAME)
        ]);

        $media_array = $response->toArray();

        foreach ($media_array['MediaLinks'] as $media_link) {
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
