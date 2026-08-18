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

namespace App\Tests\Services\InfoProviderSystem\Providers;

use App\Entity\Parts\ManufacturingStatus;
use App\Services\InfoProviderSystem\DTOs\FileDTO;
use App\Services\InfoProviderSystem\DTOs\PartDetailDTO;
use App\Services\InfoProviderSystem\DTOs\ProviderInfoDTO;
use App\Services\InfoProviderSystem\DTOs\PurchaseInfoDTO;
use App\Services\InfoProviderSystem\DTOs\SearchResultDTO;
use App\Services\InfoProviderSystem\Providers\ProviderCapabilities;
use App\Services\InfoProviderSystem\Providers\TMEClient;
use App\Services\InfoProviderSystem\Providers\TMEProvider;
use App\Settings\InfoProviderSystem\TMESettings;
use App\Tests\SettingsTestHelper;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class TMEProviderTest extends TestCase
{
    private TMESettings $settings;
    private TMEProvider $provider;
    private MockHttpClient $httpClient;

    protected function setUp(): void
    {
        $this->httpClient = new MockHttpClient();
        $this->settings = SettingsTestHelper::createSettingsDummy(TMESettings::class);
        $this->settings->apiToken = 'test_token_000000000000000000000000000000000000000000000000';
        $this->settings->apiSecret = 'test_secret_00000000';
        $this->settings->currency = 'EUR';
        $this->settings->language = 'en';
        $this->settings->country = 'DE';
        $this->provider = new TMEProvider(new TMEClient($this->httpClient, $this->settings, new ArrayAdapter()), $this->settings, $this->httpClient);
    }

    private function newProvider(): TMEProvider
    {
        return new TMEProvider(new TMEClient($this->httpClient, $this->settings, new ArrayAdapter()), $this->settings, $this->httpClient);
    }

    // --- Mock response helpers ---

    /** OAuth2 token response – always the first reply in a sequence that triggers an API call */
    private function mockTokenResponse(): MockResponse
    {
        return new MockResponse(json_encode([
            'access_token'  => 'mock_access_token',
            'token_type'    => 'Bearer',
            'expires_in'    => 300,
            'refresh_token' => 'mock_refresh_token',
        ]));
    }

    private function mockSearchResults(array $products): MockResponse
    {
        return new MockResponse(json_encode([
            'status' => 'OK',
            'data'   => [
                'products'   => ['elements' => $products],
                'parameters' => [],
                'counters'   => [],
            ],
        ]));
    }

    private function mockProductsList(array $products): MockResponse
    {
        return new MockResponse(json_encode([
            'status' => 'OK',
            'data'   => ['elements' => $products],
        ]));
    }

    private function mockFilesList(array $elements): MockResponse
    {
        return new MockResponse(json_encode([
            'status' => 'OK',
            'data'   => ['elements' => $elements],
        ]));
    }

    private function mockParametersList(array $elements): MockResponse
    {
        return new MockResponse(json_encode([
            'status' => 'OK',
            'data'   => ['elements' => $elements],
        ]));
    }

    private function mockPrices(string $currency, string $priceType, array $elements): MockResponse
    {
        return new MockResponse(json_encode([
            'status' => 'OK',
            'data'   => ['elements' => $elements],
        ]));
    }

    // --- Mock data (v2 field names) ---

    private function smd0603Product(): array
    {
        return [
            'symbol'               => 'SMD0603-5K1-1%',
            'ean'                  => '978020137962',
            'category'             => ['name' => 'SMD resistors'],
            'manufacturer_symbols' => ['0603SAF5101T5E', 'another_symbol'],
            'manufacturer'         => ['name' => 'ROYALOHM'],
            'description'          => 'Resistor: thick film; SMD; 0603; 5.1kΩ; 0.1W; ±1%; 50V; -55÷155°C',
            'assets'               => [
                'primary_photo' => ['prime' => '//ce8dc832c.cloudimg.io/v7/_cdn_/E9/C2/B0/00/0/732318_1.jpg'],
            ],
            'weight'               => ['value' => 0.021, 'unit' => 'g'],
            'product_status'       => [],
        ];
    }

    private function smd0603SearchResults(): MockResponse
    {
        return $this->mockSearchResults([$this->smd0603Product()]);
    }

    private function smd0603Products(): MockResponse
    {
        return $this->mockProductsList([$this->smd0603Product()]);
    }

    private function smd0603Files(): MockResponse
    {
        return $this->mockFilesList([[
            'symbol'    => 'SMD0603-5K1-1%',
            'documents' => [
                'elements' => [
                    ['url' => '//www.tme.eu/Document/b315665a56acbc42df513c99b390ad98/ROYALOHM-THICKFILM.pdf', 'type' => 'DTE', 'file_name' => 'ROYALOHM-THICKFILM.pdf'],
                    ['url' => '//www.tme.eu/Document/c283990e907c122bb808207d1578ac7f/POWER_RATING-DTE.pdf', 'type' => 'DTE', 'file_name' => 'POWER_RATING-DTE.pdf'],
                    // Firmware document, must be filtered out as it is not a valid document type
                    ['url' => '//www.tme.eu/Document/some_firmware.bin', 'type' => 'SFT', 'file_name' => 'firmware.bin'],
                ],
            ],
            'assets' => [
                'additional' => ['elements' => []],
            ],
        ]]);
    }

    private function smd0603Parameters(): MockResponse
    {
        return $this->mockParametersList([[
            'symbol'     => 'SMD0603-5K1-1%',
            'parameters' => [
                'elements' => [
                    ['id' => 34,  'name' => 'Type of resistor',  'values' => [['value' => 'thick film']]],
                    ['id' => 35,  'name' => 'Case - mm',         'values' => [['value' => '1608']]],
                    ['id' => 38,  'name' => 'Resistance',        'values' => [['value' => '5.1kΩ']]],
                    ['id' => 39,  'name' => 'Tolerance',         'values' => [['value' => '±1%']]],
                    ['id' => 120, 'name' => 'Operating voltage', 'values' => [['value' => '50V']]],
                ],
            ],
        ]]);
    }

    private function smd0603Prices(): MockResponse
    {
        return $this->mockPrices('EUR', 'NET', [[
            'symbol' => 'SMD0603-5K1-1%',
            'prices' => [
                'currency' => 'EUR',
                'type'     => 'NET',
                'elements' => [
                    ['amount' => 100,  'price' => 0.01077],
                    ['amount' => 1000, 'price' => 0.00291],
                    ['amount' => 5000, 'price' => 0.00150],
                ],
            ],
        ]]);
    }

    private function etqp3mProduct(): array
    {
        return [
            'symbol'               => 'ETQP3M6R8KVP',
            'category'             => ['name' => 'Inductors'],
            'manufacturer_symbols' => ['ETQP3M6R8KVP'],
            'manufacturer'         => ['name' => 'PANASONIC'],
            'description'          => 'Inductor: wire; SMD; 6.8uH; 2.9A; R: 65.7mΩ; ±20%; ETQP3M; 5.5x5x3mm',
            'assets'               => [
                'primary_photo' => ['prime' => '//ce8dc832c.cloudimg.io/v7/_cdn_/9E/27/A0/00/0/684777_1.jpg'],
            ],
            'weight'               => ['value' => 0.44, 'unit' => 'g'],
            'product_status'       => [],
        ];
    }

    private function etqp3mProducts(): MockResponse
    {
        return $this->mockProductsList([$this->etqp3mProduct()]);
    }

    private function etqp3mFiles(): MockResponse
    {
        return $this->mockFilesList([[
            'symbol'    => 'ETQP3M6R8KVP',
            'documents' => [
                'elements' => [
                    ['url' => '//www.tme.eu/Document/50a845881f09d8a2248350946e11df38/AGL0000C63.pdf', 'type' => 'DTE', 'file_name' => 'AGL0000C63.pdf'],
                    ['url' => '//www.tme.eu/Document/8480690a42fa577214e35e33d3fc8d77/ETQP3M100KVN-LNK.txt', 'type' => 'KCH', 'file_name' => 'ETQP3M100KVN-LNK.txt'],
                ],
            ],
            'assets' => [
                'additional' => [
                    'elements' => [
                        // Only a low-res "prime" image available -> that one must be used
                        ['prime' => '//ce8dc832c.cloudimg.io/v7/_cdn_/additional1_prime.jpg'],
                        // Both available -> the high-resolution one must be preferred
                        ['prime' => '//ce8dc832c.cloudimg.io/v7/_cdn_/additional2_prime.jpg', 'high_resolution' => '//ce8dc832c.cloudimg.io/v7/_cdn_/additional2_high_res.jpg'],
                    ],
                ],
            ],
        ]]);
    }

    private function etqp3mParameters(): MockResponse
    {
        return $this->mockParametersList([[
            'symbol'     => 'ETQP3M6R8KVP',
            'parameters' => [
                'elements' => [
                    ['id' => 566, 'name' => 'Inductance',        'values' => [['value' => '6.8µH']]],
                    ['id' => 370, 'name' => 'Operating current', 'values' => [['value' => '2.9A']]],
                    ['id' => 39,  'name' => 'Tolerance',         'values' => [['value' => '±20%']]],
                ],
            ],
        ]]);
    }

    private function etqp3mPrices(): MockResponse
    {
        return $this->mockPrices('EUR', 'NET', [[
            'symbol' => 'ETQP3M6R8KVP',
            'prices' => [
                'currency' => 'EUR',
                'type'     => 'NET',
                'elements' => [
                    ['amount' => 1,  'price' => 0.589],
                    ['amount' => 5,  'price' => 0.429],
                    ['amount' => 10, 'price' => 0.399],
                ],
            ],
        ]]);
    }

    // --- Tests ---

    public function testGetProviderInfo(): void
    {
        $info = $this->provider->getProviderInfo();

        $this->assertInstanceOf(ProviderInfoDTO::class, $info);
        $this->assertSame('tme', $info->key);
        $this->assertEquals('TME', $info->name);
        $this->assertEquals('https://tme.eu/', $info->url);
    }

    public function testIsActiveWithCredentials(): void
    {
        $this->assertTrue($this->provider->isActive());
    }

    public function testIsActiveWithoutCredentials(): void
    {
        $this->settings->apiToken = null;
        $provider = $this->newProvider();
        $this->assertFalse($provider->isActive());
    }

    public function testGetCapabilities(): void
    {
        $capabilities = $this->provider->getProviderInfo()->capabilities;

        $this->assertIsArray($capabilities);
        $this->assertContains(ProviderCapabilities::BASIC, $capabilities);
        $this->assertContains(ProviderCapabilities::PICTURE, $capabilities);
        $this->assertContains(ProviderCapabilities::DATASHEET, $capabilities);
        $this->assertContains(ProviderCapabilities::PRICE, $capabilities);
        $this->assertContains(ProviderCapabilities::FOOTPRINT, $capabilities);
    }

    public function testGetHandledDomains(): void
    {
        $this->assertContains('tme.eu', $this->provider->getHandledDomains());
    }

    public function testGetIDFromURL(): void
    {
        $this->assertSame('fi321_se', $this->provider->getIDFromURL('https://www.tme.eu/de/details/fi321_se/kuhler/alutronic/'));
        $this->assertSame('smd0603-5k1-1%25', $this->provider->getIDFromURL('https://www.tme.eu/en/details/smd0603-5k1-1%25/smd-resistors/royalohm/0603saf5101t5e/'));
        $this->assertNull($this->provider->getIDFromURL('https://www.tme.eu/en/'));
    }

    public function testAccessTokenIsCachedAcrossClientInstances(): void
    {
        // A single ArrayAdapter shared between two independent TMEClient instances simulates
        // the token cache surviving across separate requests (each request builds a fresh client).
        $cache = new ArrayAdapter();

        // First client has to fetch a token before making its API call
        $client1 = new TMEClient($this->httpClient, $this->settings, $cache);
        $this->httpClient->setResponseFactory([
            $this->mockTokenResponse(),
            $this->smd0603SearchResults(),
        ]);
        $client1->makeRequest('products/search', ['phrase' => 'SMD0603-5K1-1%']);

        // Second client is a fresh instance, but shares the cache pool, so no token request should be made
        $client2 = new TMEClient($this->httpClient, $this->settings, $cache);
        $this->httpClient->setResponseFactory([
            $this->smd0603SearchResults(),
        ]);
        $response = $client2->makeRequest('products/search', ['phrase' => 'SMD0603-5K1-1%']);

        $this->assertSame('OK', $response->toArray()['status']);
    }

    public function testAccessTokenIsRefetchedAfterExpiryEvenWithSharedCache(): void
    {
        $cache = new ArrayAdapter();

        $client1 = new TMEClient($this->httpClient, $this->settings, $cache);
        $this->httpClient->setResponseFactory([
            new MockResponse(json_encode([
                'access_token'  => 'mock_access_token',
                'token_type'    => 'Bearer',
                'expires_in'    => -1, // already expired
                'refresh_token' => 'mock_refresh_token',
            ])),
            $this->smd0603SearchResults(),
        ]);
        $client1->makeRequest('products/search', ['phrase' => 'SMD0603-5K1-1%']);

        // The cached token is expired, and the refresh_token grant fails (no mock queued for it beyond
        // the token response below), so a fresh client_credentials token must be fetched.
        $client2 = new TMEClient($this->httpClient, $this->settings, $cache);
        $this->httpClient->setResponseFactory([
            new MockResponse('', ['http_code' => 400]), // refresh_token grant fails
            $this->mockTokenResponse(), // fallback client_credentials grant
            $this->smd0603SearchResults(),
        ]);
        $response = $client2->makeRequest('products/search', ['phrase' => 'SMD0603-5K1-1%']);

        $this->assertSame('OK', $response->toArray()['status']);
    }

    public function testSearchByKeyword(): void
    {
        // Request order: POST /auth/token, GET /products/search
        $this->httpClient->setResponseFactory([
            $this->mockTokenResponse(),
            $this->smd0603SearchResults(),
        ]);

        $results = $this->provider->searchByKeyword('SMD0603-5K1-1%');

        $this->assertIsArray($results);
        $this->assertCount(1, $results);
        $this->assertInstanceOf(SearchResultDTO::class, $results[0]);
        $this->assertSame('tme', $results[0]->provider_key);
        $this->assertSame('SMD0603-5K1-1%', $results[0]->provider_id);
        $this->assertSame('0603SAF5101T5E', $results[0]->name);
        $this->assertSame('ROYALOHM', $results[0]->manufacturer);
        $this->assertSame('SMD resistors', $results[0]->category);
        $this->assertSame('0603SAF5101T5E', $results[0]->mpn);
        $this->assertSame('978020137962', $results[0]->gtin);
        $this->assertSame('https://ce8dc832c.cloudimg.io/v7/_cdn_/E9/C2/B0/00/0/732318_1.jpg', $results[0]->preview_image_url);
        $this->assertSame(ManufacturingStatus::ACTIVE, $results[0]->manufacturing_status);
        $this->assertSame('https://www.tme.eu/de/en/details/SMD0603-5K1-1%/', $results[0]->provider_url);
    }

    /**
     * Products can be missing most optional fields (category, manufacturer, symbols, images, EAN, ...).
     * The provider must not crash on these and just return null for the corresponding DTO fields.
     */
    public function testSearchByKeywordWithNullableFields(): void
    {
        $this->httpClient->setResponseFactory([
            $this->mockTokenResponse(),
            $this->mockSearchResults([[
                'symbol'         => 'FAKE_PRODUCT',
                'description'    => 'High-Quality bleeding edge fake product',
                'product_status' => ['INVALID'],
            ]]),
        ]);

        $results = $this->provider->searchByKeyword('FAKE_PRODUCT');

        $this->assertCount(1, $results);
        $this->assertSame('FAKE_PRODUCT', $results[0]->provider_id);
        $this->assertSame('FAKE_PRODUCT', $results[0]->name);
        $this->assertSame('High-Quality bleeding edge fake product', $results[0]->description);
        $this->assertNull($results[0]->category);
        $this->assertNull($results[0]->manufacturer);
        $this->assertNull($results[0]->mpn);
        $this->assertNull($results[0]->preview_image_url);
        $this->assertNull($results[0]->gtin);
        $this->assertSame(ManufacturingStatus::DISCONTINUED, $results[0]->manufacturing_status);
    }

    public function testGetDetailsWithPercentInPartNumber(): void
    {
        // Request order: POST /auth/token, GET /products, GET /products/files,
        //                GET /products/parameters, GET /products/data
        $this->httpClient->setResponseFactory([
            $this->mockTokenResponse(),
            $this->smd0603Products(),
            $this->smd0603Files(),
            $this->smd0603Parameters(),
            $this->smd0603Prices(),
        ]);

        $result = $this->provider->getDetails('SMD0603-5K1-1%');

        $this->assertInstanceOf(PartDetailDTO::class, $result);
        $this->assertSame('SMD0603-5K1-1%', $result->provider_id);
        $this->assertSame('0603SAF5101T5E', $result->name);
        $this->assertSame('Resistor: thick film; SMD; 0603; 5.1kΩ; 0.1W; ±1%; 50V; -55÷155°C', $result->description);
        $this->assertSame('ROYALOHM', $result->manufacturer);
        $this->assertSame('0603SAF5101T5E', $result->mpn);
        $this->assertSame('SMD resistors', $result->category);
        $this->assertSame('978020137962', $result->gtin);
        $this->assertSame(ManufacturingStatus::ACTIVE, $result->manufacturing_status);
        $this->assertSame(0.021, $result->mass);
        $this->assertSame('1608', $result->footprint);
        $this->assertSame('https://www.tme.eu/de/en/details/SMD0603-5K1-1%/', $result->provider_url);

        // The firmware document (type SFT) must be filtered out, only the 2 DTE documents remain
        $this->assertCount(2, $result->datasheets);
        $this->assertInstanceOf(FileDTO::class, $result->datasheets[0]);
        $this->assertSame('https://www.tme.eu/Document/b315665a56acbc42df513c99b390ad98/ROYALOHM-THICKFILM.pdf', $result->datasheets[0]->url);
        $this->assertSame('ROYALOHM-THICKFILM.pdf', $result->datasheets[0]->name);
        $this->assertCount(0, $result->images);

        $this->assertCount(1, $result->vendor_infos);
        $vendorInfo = $result->vendor_infos[0];
        $this->assertInstanceOf(PurchaseInfoDTO::class, $vendorInfo);
        $this->assertSame('TME', $vendorInfo->distributor_name);
        $this->assertSame('SMD0603-5K1-1%', $vendorInfo->order_number);
        $this->assertSame('https://www.tme.eu/de/en/details/SMD0603-5K1-1%/', $vendorInfo->product_url);
        $this->assertCount(3, $vendorInfo->prices);
        $this->assertSame(100.0, $vendorInfo->prices[0]->minimum_discount_amount);
        $this->assertSame('0.01077', $vendorInfo->prices[0]->price);
        $this->assertSame('EUR', $vendorInfo->prices[0]->currency_iso_code);
        $this->assertFalse($vendorInfo->prices[0]->includes_tax);

        $this->assertCount(5, $result->parameters);
    }

    public function testGetDetailsForEtqp3m6r8kvp(): void
    {
        // Request order: POST /auth/token, GET /products, GET /products/files,
        //                GET /products/parameters, GET /products/data
        $this->httpClient->setResponseFactory([
            $this->mockTokenResponse(),
            $this->etqp3mProducts(),
            $this->etqp3mFiles(),
            $this->etqp3mParameters(),
            $this->etqp3mPrices(),
        ]);

        $result = $this->provider->getDetails('ETQP3M6R8KVP');

        $this->assertInstanceOf(PartDetailDTO::class, $result);
        $this->assertSame('ETQP3M6R8KVP', $result->provider_id);
        $this->assertSame('ETQP3M6R8KVP', $result->name);
        $this->assertSame('Inductor: wire; SMD; 6.8uH; 2.9A; R: 65.7mΩ; ±20%; ETQP3M; 5.5x5x3mm', $result->description);
        $this->assertSame('PANASONIC', $result->manufacturer);
        $this->assertSame('ETQP3M6R8KVP', $result->mpn);
        $this->assertSame('Inductors', $result->category);
        $this->assertNull($result->gtin);
        $this->assertSame(ManufacturingStatus::ACTIVE, $result->manufacturing_status);
        $this->assertSame(0.44, $result->mass);
        $this->assertNull($result->footprint);
        $this->assertSame('https://www.tme.eu/de/en/details/ETQP3M6R8KVP/', $result->provider_url);

        $this->assertCount(2, $result->datasheets);
        $this->assertSame('https://www.tme.eu/Document/50a845881f09d8a2248350946e11df38/AGL0000C63.pdf', $result->datasheets[0]->url);

        // The additional image with a high_resolution variant must prefer it over "prime"
        $this->assertCount(2, $result->images);
        $this->assertSame('https://ce8dc832c.cloudimg.io/v7/_cdn_/additional1_prime.jpg', $result->images[0]->url);
        $this->assertSame('https://ce8dc832c.cloudimg.io/v7/_cdn_/additional2_high_res.jpg', $result->images[1]->url);

        $this->assertCount(1, $result->vendor_infos);
        $vendorInfo = $result->vendor_infos[0];
        $this->assertSame('TME', $vendorInfo->distributor_name);
        $this->assertSame('ETQP3M6R8KVP', $vendorInfo->order_number);
        $this->assertSame('https://www.tme.eu/de/en/details/ETQP3M6R8KVP/', $vendorInfo->product_url);
        $this->assertCount(3, $vendorInfo->prices);
        $this->assertSame(1.0, $vendorInfo->prices[0]->minimum_discount_amount);
        $this->assertSame('0.589', $vendorInfo->prices[0]->price);
        $this->assertSame('EUR', $vendorInfo->prices[0]->currency_iso_code);
        $this->assertFalse($vendorInfo->prices[0]->includes_tax);

        $this->assertCount(3, $result->parameters);
    }

    private function footprintTestFixture(): array
    {
        return [
            $this->mockTokenResponse(),
            $this->mockProductsList([[
                'symbol'         => 'FAKE_PRODUCT',
                'description'    => 'Really nice fake product',
                'product_status' => [],
            ]]),
            $this->mockFilesList([[
                'symbol'    => 'FAKE_PRODUCT',
                'documents' => ['elements' => []],
                'assets'    => ['additional' => ['elements' => []]],
            ]]),
            $this->mockParametersList([[
                'symbol'     => 'FAKE_PRODUCT',
                'parameters' => [
                    'elements' => [
                        ['id' => 2932, 'name' => 'Case - inch', 'values' => [['value' => 'footprint_imperial']]],
                        ['id' => 2931, 'name' => 'Case - mm',   'values' => [['value' => 'footprint_metric']]],
                    ],
                ],
            ]]),
            $this->mockPrices('EUR', 'NET', [[
                'symbol' => 'FAKE_PRODUCT',
                'prices' => ['currency' => 'EUR', 'type' => 'NET', 'elements' => []],
            ]]),
        ];
    }

    public function testGetDetailsPrefersImperialFootprintWhenConfigured(): void
    {
        $this->httpClient->setResponseFactory($this->footprintTestFixture());
        $this->settings->preferMetricFootprint = false;

        $result = $this->provider->getDetails('FAKE_PRODUCT');

        $this->assertSame('footprint_imperial', $result->footprint);
    }

    public function testGetDetailsPrefersMetricFootprintWhenConfigured(): void
    {
        $this->httpClient->setResponseFactory($this->footprintTestFixture());
        $this->settings->preferMetricFootprint = true;

        $result = $this->provider->getDetails('FAKE_PRODUCT');

        $this->assertSame('footprint_metric', $result->footprint);
    }

    public function testProductStatusArrayToManufacturingStatus(): void
    {
        $method = (new \ReflectionClass($this->provider))->getMethod('productStatusArrayToManufacturingStatus');

        $this->assertSame(ManufacturingStatus::ACTIVE, $method->invoke($this->provider, []));
        $this->assertSame(ManufacturingStatus::DISCONTINUED, $method->invoke($this->provider, ['INVALID']));
        $this->assertSame(ManufacturingStatus::DISCONTINUED, $method->invoke($this->provider, ['PRODUCT_BLOCKED']));
        $this->assertSame(ManufacturingStatus::DISCONTINUED, $method->invoke($this->provider, ['NOT_IN_OFFER']));
        $this->assertSame(ManufacturingStatus::EOL, $method->invoke($this->provider, ['AVAILABLE_WHILE_STOCKS_LAST']));
    }

    public function testNormalizeURLEncodesBarePctSign(): void
    {
        $method = (new \ReflectionClass($this->provider))->getMethod('normalizeURL');

        $this->assertSame(
            'https://www.tme.eu/en/details/smd0603-5k1-1%25/smd-resistors/royalohm/0603saf5101t5e/',
            $method->invoke($this->provider, '//www.tme.eu/en/details/smd0603-5k1-1%/smd-resistors/royalohm/0603saf5101t5e/')
        );
        $this->assertSame(
            'https://www.tme.eu/en/details/smd0603-5k1-1%25/smd-resistors/royalohm/0603saf5101t5e/',
            $method->invoke($this->provider, '//www.tme.eu/en/details/smd0603-5k1-1%25/smd-resistors/royalohm/0603saf5101t5e/')
        );
        $this->assertSame(
            'https://www.tme.eu/en/details/etqp3m6r8kvp/inductors/panasonic/',
            $method->invoke($this->provider, '//www.tme.eu/en/details/etqp3m6r8kvp/inductors/panasonic/')
        );
        $this->assertSame('https://example.com/path', $method->invoke($this->provider, 'https://example.com/path'));
    }
}
