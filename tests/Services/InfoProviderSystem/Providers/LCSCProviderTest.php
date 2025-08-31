<?php
/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2023 Jan Böhmer (https://github.com/jbtronics)
 *  Copyright (C) 2024 Nexrem (https://github.com/meganukebmp)
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

use App\Services\InfoProviderSystem\DTOs\FileDTO;
use App\Services\InfoProviderSystem\DTOs\ParameterDTO;
use App\Services\InfoProviderSystem\DTOs\PartDetailDTO;
use App\Services\InfoProviderSystem\DTOs\PriceDTO;
use App\Services\InfoProviderSystem\DTOs\PurchaseInfoDTO;
use App\Services\InfoProviderSystem\Providers\LCSCProvider;
use App\Services\InfoProviderSystem\Providers\ProviderCapabilities;
use App\Settings\InfoProviderSystem\LCSCSettings;
use App\Tests\SettingsTestHelper;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class LCSCProviderTest extends TestCase
{
    private LCSCSettings $settings;
    private LCSCProvider $provider;
    private MockHttpClient $httpClient;

    protected function setUp(): void
    {
        $this->httpClient = new MockHttpClient();
        $this->settings = SettingsTestHelper::createSettingsDummy(LCSCSettings::class);
        $this->settings->currency = 'USD';
        $this->settings->enabled = true;
        $this->provider = new LCSCProvider($this->httpClient, $this->settings);
    }

    public function testGetProviderInfo(): void
    {
        $info = $this->provider->getProviderInfo();

        $this->assertIsArray($info);
        $this->assertArrayHasKey('name', $info);
        $this->assertArrayHasKey('description', $info);
        $this->assertArrayHasKey('url', $info);
        $this->assertArrayHasKey('disabled_help', $info);
        $this->assertEquals('LCSC', $info['name']);
        $this->assertEquals('https://www.lcsc.com/', $info['url']);
    }

    public function testGetProviderKey(): void
    {
        $this->assertEquals('lcsc', $this->provider->getProviderKey());
    }

    public function testIsActiveWhenEnabled(): void
    {
        //Ensure that the settings are enabled
        $this->settings->enabled = true;
        $this->assertTrue($this->provider->isActive());
    }

    public function testIsActiveWhenDisabled(): void
    {
        //Ensure that the settings are disabled
        $this->settings->enabled = false;
        $this->assertFalse($this->provider->isActive());
    }

    public function testGetCapabilities(): void
    {
        $capabilities = $this->provider->getCapabilities();

        $this->assertIsArray($capabilities);
        $this->assertContains(ProviderCapabilities::BASIC, $capabilities);
        $this->assertContains(ProviderCapabilities::PICTURE, $capabilities);
        $this->assertContains(ProviderCapabilities::DATASHEET, $capabilities);
        $this->assertContains(ProviderCapabilities::PRICE, $capabilities);
        $this->assertContains(ProviderCapabilities::FOOTPRINT, $capabilities);
    }

    public function testSearchByKeywordWithCCode(): void
    {
        $mockResponse = new MockResponse(json_encode([
            'result' => [
                'productCode' => 'C123456',
                'productModel' => 'Test Component',
                'productIntroEn' => 'Test description',
                'brandNameEn' => 'Test Manufacturer',
                'encapStandard' => '0603',
                'productImageUrl' => 'https://example.com/image.jpg',
                'productImages' => ['https://example.com/image1.jpg'],
                'productPriceList' => [
                    ['ladder' => 1, 'productPrice' => '0.10', 'currencySymbol' => 'US$']
                ],
                'paramVOList' => [
                    ['paramNameEn' => 'Resistance', 'paramValueEn' => '1kΩ']
                ],
                'pdfUrl' => 'https://example.com/datasheet.pdf',
                'weight' => 0.001
            ]
        ]));

        $this->httpClient->setResponseFactory([$mockResponse]);

        $results = $this->provider->searchByKeyword('C123456');

        $this->assertIsArray($results);
        $this->assertCount(1, $results);
        $this->assertInstanceOf(PartDetailDTO::class, $results[0]);
        $this->assertEquals('C123456', $results[0]->provider_id);
        $this->assertEquals('Test Component', $results[0]->name);
    }

    public function testSearchByKeywordWithRegularTerm(): void
    {
        $mockResponse = new MockResponse(json_encode([
            'result' => [
                'productSearchResultVO' => [
                    'productList' => [
                        [
                            'productCode' => 'C789012',
                            'productModel' => 'Regular Component',
                            'productIntroEn' => 'Regular description',
                            'brandNameEn' => 'Regular Manufacturer',
                            'encapStandard' => '0805',
                            'productImageUrl' => 'https://example.com/regular.jpg',
                            'productImages' => ['https://example.com/regular1.jpg'],
                            'productPriceList' => [
                                ['ladder' => 10, 'productPrice' => '0.08', 'currencySymbol' => '€']
                            ],
                            'paramVOList' => [],
                            'pdfUrl' => null,
                            'weight' => null
                        ]
                    ]
                ]
            ]
        ]));

        $this->httpClient->setResponseFactory([$mockResponse]);

        $results = $this->provider->searchByKeyword('resistor');

        $this->assertIsArray($results);
        $this->assertCount(1, $results);
        $this->assertInstanceOf(PartDetailDTO::class, $results[0]);
        $this->assertEquals('C789012', $results[0]->provider_id);
        $this->assertEquals('Regular Component', $results[0]->name);
    }

    public function testSearchByKeywordWithTipProduct(): void
    {
        $mockResponse = new MockResponse(json_encode([
            'result' => [
                'productSearchResultVO' => [
                    'productList' => []
                ],
                'tipProductDetailUrlVO' => [
                    'productCode' => 'C555555'
                ]
            ]
        ]));

        $detailResponse = new MockResponse(json_encode([
            'result' => [
                'productCode' => 'C555555',
                'productModel' => 'Tip Component',
                'productIntroEn' => 'Tip description',
                'brandNameEn' => 'Tip Manufacturer',
                'encapStandard' => '1206',
                'productImageUrl' => null,
                'productImages' => [],
                'productPriceList' => [],
                'paramVOList' => [],
                'pdfUrl' => null,
                'weight' => null
            ]
        ]));

        $this->httpClient->setResponseFactory([$mockResponse, $detailResponse]);

        $results = $this->provider->searchByKeyword('special');

        $this->assertIsArray($results);
        $this->assertCount(1, $results);
        $this->assertInstanceOf(PartDetailDTO::class, $results[0]);
        $this->assertEquals('C555555', $results[0]->provider_id);
        $this->assertEquals('Tip Component', $results[0]->name);
    }

    public function testSearchByKeywordsBatch(): void
    {
        $mockResponse1 = new MockResponse(json_encode([
            'result' => [
                'productCode' => 'C123456',
                'productModel' => 'Batch Component 1',
                'productIntroEn' => 'Batch description 1',
                'brandNameEn' => 'Batch Manufacturer',
                'encapStandard' => '0603',
                'productImageUrl' => null,
                'productImages' => [],
                'productPriceList' => [],
                'paramVOList' => [],
                'pdfUrl' => null,
                'weight' => null
            ]
        ]));

        $mockResponse2 = new MockResponse(json_encode([
            'result' => [
                'productSearchResultVO' => [
                    'productList' => [
                        [
                            'productCode' => 'C789012',
                            'productModel' => 'Batch Component 2',
                            'productIntroEn' => 'Batch description 2',
                            'brandNameEn' => 'Batch Manufacturer',
                            'encapStandard' => '0805',
                            'productImageUrl' => null,
                            'productImages' => [],
                            'productPriceList' => [],
                            'paramVOList' => [],
                            'pdfUrl' => null,
                            'weight' => null
                        ]
                    ]
                ]
            ]
        ]));

        $this->httpClient->setResponseFactory([$mockResponse1, $mockResponse2]);

        $results = $this->provider->searchByKeywordsBatch(['C123456', 'resistor']);

        $this->assertIsArray($results);
        $this->assertArrayHasKey('C123456', $results);
        $this->assertArrayHasKey('resistor', $results);
        $this->assertCount(1, $results['C123456']);
        $this->assertCount(1, $results['resistor']);
        $this->assertEquals('C123456', $results['C123456'][0]->provider_id);
        $this->assertEquals('C789012', $results['resistor'][0]->provider_id);
    }

    public function testGetDetails(): void
    {
        $mockResponse = new MockResponse(json_encode([
            'result' => [
                'productCode' => 'C123456',
                'productModel' => 'Detailed Component',
                'productIntroEn' => 'Detailed description',
                'brandNameEn' => 'Detailed Manufacturer',
                'encapStandard' => '0603',
                'productImageUrl' => 'https://example.com/detail.jpg',
                'productImages' => ['https://example.com/detail1.jpg'],
                'productPriceList' => [
                    ['ladder' => 1, 'productPrice' => '0.10', 'currencySymbol' => 'US$'],
                    ['ladder' => 10, 'productPrice' => '0.08', 'currencySymbol' => 'US$']
                ],
                'paramVOList' => [
                    ['paramNameEn' => 'Resistance', 'paramValueEn' => '1kΩ'],
                    ['paramNameEn' => 'Tolerance', 'paramValueEn' => '1%']
                ],
                'pdfUrl' => 'https://example.com/datasheet.pdf',
                'weight' => 0.001
            ]
        ]));

        $this->httpClient->setResponseFactory([$mockResponse]);

        $result = $this->provider->getDetails('C123456');

        $this->assertInstanceOf(PartDetailDTO::class, $result);
        $this->assertEquals('C123456', $result->provider_id);
        $this->assertEquals('Detailed Component', $result->name);
        $this->assertEquals('Detailed description', $result->description);
        $this->assertEquals('Detailed Manufacturer', $result->manufacturer);
        $this->assertEquals('0603', $result->footprint);
        $this->assertEquals('https://www.lcsc.com/product-detail/C123456.html', $result->provider_url);
        $this->assertCount(1, $result->images);
        $this->assertCount(2, $result->parameters);
        $this->assertCount(1, $result->vendor_infos);
        $this->assertEquals('0.001', $result->mass);
    }

    public function testGetDetailsWithNoResults(): void
    {
        $mockResponse = new MockResponse(json_encode([
            'result' => [
                'productSearchResultVO' => [
                    'productList' => []
                ]
            ]
        ]));

        $this->httpClient->setResponseFactory([$mockResponse]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No part found with ID INVALID');

        $this->provider->getDetails('INVALID');
    }

    public function testGetDetailsWithMultipleResults(): void
    {
        $mockResponse = new MockResponse(json_encode([
            'result' => [
                'productSearchResultVO' => [
                    'productList' => [
                        [
                            'productCode' => 'C123456',
                            'productModel' => 'Component 1',
                            'productIntroEn' => 'Description 1',
                            'brandNameEn' => 'Manufacturer 1',
                            'encapStandard' => '0603',
                            'productImageUrl' => null,
                            'productImages' => [],
                            'productPriceList' => [],
                            'paramVOList' => [],
                            'pdfUrl' => null,
                            'weight' => null
                        ],
                        [
                            'productCode' => 'C789012',
                            'productModel' => 'Component 2',
                            'productIntroEn' => 'Description 2',
                            'brandNameEn' => 'Manufacturer 2',
                            'encapStandard' => '0805',
                            'productImageUrl' => null,
                            'productImages' => [],
                            'productPriceList' => [],
                            'paramVOList' => [],
                            'pdfUrl' => null,
                            'weight' => null
                        ]
                    ]
                ]
            ]
        ]));

        $this->httpClient->setResponseFactory([$mockResponse]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Multiple parts found with ID ambiguous');

        $this->provider->getDetails('ambiguous');
    }

    public function testSanitizeFieldPrivateMethod(): void
    {
        $reflection = new \ReflectionClass($this->provider);
        $method = $reflection->getMethod('sanitizeField');
        $method->setAccessible(true);

        $this->assertNull($method->invokeArgs($this->provider, [null]));
        $this->assertEquals('Clean text', $method->invokeArgs($this->provider, ['Clean text']));
        $this->assertEquals('Text without tags', $method->invokeArgs($this->provider, ['<b>Text</b> without <i>tags</i>']));
    }

    public function testGetUsedCurrencyPrivateMethod(): void
    {
        $reflection = new \ReflectionClass($this->provider);
        $method = $reflection->getMethod('getUsedCurrency');
        $method->setAccessible(true);

        $this->assertEquals('USD', $method->invokeArgs($this->provider, ['US$']));
        $this->assertEquals('USD', $method->invokeArgs($this->provider, ['$']));
        $this->assertEquals('EUR', $method->invokeArgs($this->provider, ['€']));
        $this->assertEquals('GBP', $method->invokeArgs($this->provider, ['£']));
        $this->assertEquals('USD', $method->invokeArgs($this->provider, ['UNKNOWN'])); // fallback to configured currency
    }

    public function testGetProductShortURLPrivateMethod(): void
    {
        $reflection = new \ReflectionClass($this->provider);
        $method = $reflection->getMethod('getProductShortURL');
        $method->setAccessible(true);

        $result = $method->invokeArgs($this->provider, ['C123456']);
        $this->assertEquals('https://www.lcsc.com/product-detail/C123456.html', $result);
    }

    public function testGetProductDatasheetsPrivateMethod(): void
    {
        $reflection = new \ReflectionClass($this->provider);
        $method = $reflection->getMethod('getProductDatasheets');
        $method->setAccessible(true);

        $result = $method->invokeArgs($this->provider, [null]);
        $this->assertIsArray($result);
        $this->assertEmpty($result);

        $result = $method->invokeArgs($this->provider, ['https://example.com/datasheet.pdf']);
        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertInstanceOf(FileDTO::class, $result[0]);
    }

    public function testGetProductImagesPrivateMethod(): void
    {
        $reflection = new \ReflectionClass($this->provider);
        $method = $reflection->getMethod('getProductImages');
        $method->setAccessible(true);

        $result = $method->invokeArgs($this->provider, [null]);
        $this->assertIsArray($result);
        $this->assertEmpty($result);

        $result = $method->invokeArgs($this->provider, [['https://example.com/image1.jpg', 'https://example.com/image2.jpg']]);
        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertInstanceOf(FileDTO::class, $result[0]);
        $this->assertInstanceOf(FileDTO::class, $result[1]);
    }

    public function testAttributesToParametersPrivateMethod(): void
    {
        $reflection = new \ReflectionClass($this->provider);
        $method = $reflection->getMethod('attributesToParameters');
        $method->setAccessible(true);

        $attributes = [
            ['paramNameEn' => 'Resistance', 'paramValueEn' => '1kΩ'],
            ['paramNameEn' => 'Tolerance', 'paramValueEn' => '1%'],
            ['paramNameEn' => 'Empty', 'paramValueEn' => ''],
            ['paramNameEn' => 'Dash', 'paramValueEn' => '-']
        ];

        $result = $method->invokeArgs($this->provider, [$attributes]);
        $this->assertIsArray($result);
        $this->assertCount(2, $result); // Only non-empty values
        $this->assertInstanceOf(ParameterDTO::class, $result[0]);
        $this->assertInstanceOf(ParameterDTO::class, $result[1]);
    }

    public function testPricesToVendorInfoPrivateMethod(): void
    {
        $reflection = new \ReflectionClass($this->provider);
        $method = $reflection->getMethod('pricesToVendorInfo');
        $method->setAccessible(true);

        $prices = [
            ['ladder' => 1, 'productPrice' => '0.10', 'currencySymbol' => 'US$'],
            ['ladder' => 10, 'productPrice' => '0.08', 'currencySymbol' => 'US$']
        ];

        $result = $method->invokeArgs($this->provider, ['C123456', 'https://example.com', $prices]);
        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertInstanceOf(PurchaseInfoDTO::class, $result[0]);
        $this->assertEquals('LCSC', $result[0]->distributor_name);
        $this->assertEquals('C123456', $result[0]->order_number);
        $this->assertCount(2, $result[0]->prices);
    }

    public function testCategoryBuilding(): void
    {
        $mockResponse = new MockResponse(json_encode([
            'result' => [
                'productCode' => 'C123456',
                'productModel' => 'Test Component',
                'productIntroEn' => 'Test description',
                'brandNameEn' => 'Test Manufacturer',
                'parentCatalogName' => 'Electronic Components',
                'catalogName' => 'Resistors/SMT',
                'encapStandard' => '0603',
                'productImageUrl' => null,
                'productImages' => [],
                'productPriceList' => [],
                'paramVOList' => [],
                'pdfUrl' => null,
                'weight' => null
            ]
        ]));

        $this->httpClient->setResponseFactory([$mockResponse]);

        $result = $this->provider->getDetails('C123456');
        $this->assertEquals('Electronic Components -> Resistors -> SMT', $result->category);
    }

    public function testEmptyFootprintHandling(): void
    {
        $mockResponse = new MockResponse(json_encode([
            'result' => [
                'productCode' => 'C123456',
                'productModel' => 'Test Component',
                'productIntroEn' => 'Test description',
                'brandNameEn' => 'Test Manufacturer',
                'encapStandard' => '-',
                'productImageUrl' => null,
                'productImages' => [],
                'productPriceList' => [],
                'paramVOList' => [],
                'pdfUrl' => null,
                'weight' => null
            ]
        ]));

        $this->httpClient->setResponseFactory([$mockResponse]);

        $result = $this->provider->getDetails('C123456');
        $this->assertNull($result->footprint);
    }

    public function testSearchByKeywordsBatchWithEmptyKeywords(): void
    {
        $result = $this->provider->searchByKeywordsBatch([]);
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testSearchByKeywordsBatchWithException(): void
    {
        $mockResponse = new MockResponse('', ['http_code' => 500]);
        $this->httpClient->setResponseFactory([$mockResponse]);

        $results = $this->provider->searchByKeywordsBatch(['error']);
        $this->assertIsArray($results);
        $this->assertArrayHasKey('error', $results);
        $this->assertEmpty($results['error']);
    }
}
