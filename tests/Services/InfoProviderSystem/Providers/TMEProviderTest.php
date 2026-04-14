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
use App\Services\InfoProviderSystem\DTOs\PartDetailDTO;
use App\Services\InfoProviderSystem\DTOs\PurchaseInfoDTO;
use App\Services\InfoProviderSystem\DTOs\SearchResultDTO;
use App\Services\InfoProviderSystem\Providers\ProviderCapabilities;
use App\Services\InfoProviderSystem\Providers\TMEClient;
use App\Services\InfoProviderSystem\Providers\TMEProvider;
use App\Settings\InfoProviderSystem\TMESettings;
use App\Tests\SettingsTestHelper;
use PHPUnit\Framework\TestCase;
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
        // Use a short (anonymous-style) token so grossPrices is read from settings
        $this->settings->apiToken = 'test_token_000000000000000000000000000000000000000';
        $this->settings->apiSecret = 'test_secret';
        $this->settings->currency = 'EUR';
        $this->settings->language = 'en';
        $this->settings->country = 'DE';
        $this->settings->grossPrices = false;
        $this->provider = new TMEProvider(new TMEClient($this->httpClient, $this->settings), $this->settings);
    }

    // --- Mock response helpers ---
    // Only fields actually read by TMEProvider are included.

    private function mockProductList(array $products): MockResponse
    {
        return new MockResponse(json_encode([
            'Status' => 'OK',
            'Data'   => ['ProductList' => $products],
        ]));
    }

    private function mockFilesList(array $products): MockResponse
    {
        return new MockResponse(json_encode([
            'Status' => 'OK',
            'Data'   => ['ProductList' => $products],
        ]));
    }

    private function mockParametersList(array $products): MockResponse
    {
        return new MockResponse(json_encode([
            'Status' => 'OK',
            'Data'   => ['ProductList' => $products],
        ]));
    }

    private function mockPrices(string $currency, string $priceType, array $products): MockResponse
    {
        return new MockResponse(json_encode([
            'Status' => 'OK',
            'Data'   => [
                'Currency'    => $currency,
                'PriceType'   => $priceType,
                'ProductList' => $products,
            ],
        ]));
    }

    // --- Mock data ---

    private function smd0603Products(): MockResponse
    {
        return $this->mockProductList([[
            'Symbol'                 => 'SMD0603-5K1-1%',
            'OriginalSymbol'         => '0603SAF5101T5E',
            'Producer'               => 'ROYALOHM',
            'Description'            => 'Resistor: thick film; SMD; 0603; 5.1kΩ; 0.1W; ±1%; 50V; -55÷155°C',
            'Category'               => 'SMD resistors',
            'Photo'                  => '//ce8dc832c.cloudimg.io/v7/_cdn_/E9/C2/B0/00/0/732318_1.jpg',
            'ProductStatusList'      => [],
            'ProductInformationPage' => '//www.tme.eu/en/details/smd0603-5k1-1%/smd-resistors/royalohm/0603saf5101t5e/',
            'Weight'                 => 0.021,
            'WeightUnit'             => 'g',
        ]]);
    }

    private function smd0603Files(): MockResponse
    {
        return $this->mockFilesList([[
            'Symbol' => 'SMD0603-5K1-1%',
            'Files'  => [
                'AdditionalPhotoList' => [],
                'DocumentList'        => [
                    ['DocumentUrl' => '//www.tme.eu/Document/b315665a56acbc42df513c99b390ad98/ROYALOHM-THICKFILM.pdf'],
                    ['DocumentUrl' => '//www.tme.eu/Document/c283990e907c122bb808207d1578ac7f/POWER_RATING-DTE.pdf'],
                ],
            ],
        ]]);
    }

    private function smd0603Parameters(): MockResponse
    {
        return $this->mockParametersList([[
            'Symbol'        => 'SMD0603-5K1-1%',
            'ParameterList' => [
                ['ParameterId' => 34,  'ParameterName' => 'Type of resistor',  'ParameterValue' => 'thick film'],
                ['ParameterId' => 35,  'ParameterName' => 'Case - mm',         'ParameterValue' => '1608'],
                ['ParameterId' => 38,  'ParameterName' => 'Resistance',        'ParameterValue' => '5.1kΩ'],
                ['ParameterId' => 39,  'ParameterName' => 'Tolerance',         'ParameterValue' => '±1%'],
                ['ParameterId' => 120, 'ParameterName' => 'Operating voltage', 'ParameterValue' => '50V'],
            ],
        ]]);
    }

    private function smd0603Prices(): MockResponse
    {
        return $this->mockPrices('EUR', 'NET', [[
            'Symbol'    => 'SMD0603-5K1-1%',
            'PriceList' => [
                ['Amount' => 100,  'PriceValue' => 0.01077],
                ['Amount' => 1000, 'PriceValue' => 0.00291],
                ['Amount' => 5000, 'PriceValue' => 0.00150],
            ],
        ]]);
    }

    private function etqp3mProducts(): MockResponse
    {
        return $this->mockProductList([[
            'Symbol'                 => 'ETQP3M6R8KVP',
            'OriginalSymbol'         => 'ETQP3M6R8KVP',
            'Producer'               => 'PANASONIC',
            'Description'            => 'Inductor: wire; SMD; 6.8uH; 2.9A; R: 65.7mΩ; ±20%; ETQP3M; 5.5x5x3mm',
            'Category'               => 'Inductors',
            'Photo'                  => '//ce8dc832c.cloudimg.io/v7/_cdn_/9E/27/A0/00/0/684777_1.jpg',
            'ProductStatusList'      => [],
            'ProductInformationPage' => '//www.tme.eu/en/details/etqp3m6r8kvp/inductors/panasonic/',
            'Weight'                 => 0.44,
            'WeightUnit'             => 'g',
        ]]);
    }

    private function etqp3mFiles(): MockResponse
    {
        return $this->mockFilesList([[
            'Symbol' => 'ETQP3M6R8KVP',
            'Files'  => [
                'AdditionalPhotoList' => [],
                'DocumentList'        => [
                    ['DocumentUrl' => '//www.tme.eu/Document/50a845881f09d8a2248350946e11df38/AGL0000C63.pdf'],
                    ['DocumentUrl' => '//www.tme.eu/Document/8480690a42fa577214e35e33d3fc8d77/ETQP3M100KVN-LNK.txt'],
                ],
            ],
        ]]);
    }

    private function etqp3mParameters(): MockResponse
    {
        return $this->mockParametersList([[
            'Symbol'        => 'ETQP3M6R8KVP',
            'ParameterList' => [
                ['ParameterId' => 566, 'ParameterName' => 'Inductance',        'ParameterValue' => '6.8µH'],
                ['ParameterId' => 370, 'ParameterName' => 'Operating current', 'ParameterValue' => '2.9A'],
                ['ParameterId' => 39,  'ParameterName' => 'Tolerance',         'ParameterValue' => '±20%'],
            ],
        ]]);
    }

    private function etqp3mPrices(): MockResponse
    {
        return $this->mockPrices('EUR', 'NET', [[
            'Symbol'    => 'ETQP3M6R8KVP',
            'PriceList' => [
                ['Amount' => 1,  'PriceValue' => 0.589],
                ['Amount' => 5,  'PriceValue' => 0.429],
                ['Amount' => 10, 'PriceValue' => 0.399],
            ],
        ]]);
    }

    // --- Tests ---

    public function testGetProviderInfo(): void
    {
        $info = $this->provider->getProviderInfo();

        $this->assertIsArray($info);
        $this->assertArrayHasKey('name', $info);
        $this->assertArrayHasKey('description', $info);
        $this->assertArrayHasKey('url', $info);
        $this->assertEquals('TME', $info['name']);
        $this->assertEquals('https://tme.eu/', $info['url']);
    }

    public function testGetProviderKey(): void
    {
        $this->assertSame('tme', $this->provider->getProviderKey());
    }

    public function testIsActiveWithCredentials(): void
    {
        $this->assertTrue($this->provider->isActive());
    }

    public function testIsActiveWithoutCredentials(): void
    {
        $this->settings->apiToken = null;
        $provider = new TMEProvider(new TMEClient($this->httpClient, $this->settings), $this->settings);
        $this->assertFalse($provider->isActive());
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

    public function testSearchByKeyword(): void
    {
        $this->httpClient->setResponseFactory([$this->smd0603Products()]);

        $results = $this->provider->searchByKeyword('SMD0603-5K1-1%');

        $this->assertIsArray($results);
        $this->assertCount(1, $results);
        $this->assertInstanceOf(SearchResultDTO::class, $results[0]);
        $this->assertSame('SMD0603-5K1-1%', $results[0]->provider_id);
        $this->assertSame('0603SAF5101T5E', $results[0]->name);
        $this->assertSame('ROYALOHM', $results[0]->manufacturer);
        $this->assertSame('SMD resistors', $results[0]->category);
        $this->assertSame(ManufacturingStatus::ACTIVE, $results[0]->manufacturing_status);
        $this->assertSame(
            'https://www.tme.eu/en/details/smd0603-5k1-1%25/smd-resistors/royalohm/0603saf5101t5e/',
            $results[0]->provider_url
        );
    }

    public function testGetDetailsWithPercentInPartNumber(): void
    {
        $this->httpClient->setResponseFactory([
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
        $this->assertSame(ManufacturingStatus::ACTIVE, $result->manufacturing_status);
        $this->assertSame(0.021, $result->mass);
        $this->assertSame('1608', $result->footprint);
        $this->assertSame(
            'https://www.tme.eu/en/details/smd0603-5k1-1%25/smd-resistors/royalohm/0603saf5101t5e/',
            $result->provider_url
        );

        $this->assertCount(2, $result->datasheets);
        $this->assertSame('https://www.tme.eu/Document/b315665a56acbc42df513c99b390ad98/ROYALOHM-THICKFILM.pdf', $result->datasheets[0]->url);
        $this->assertCount(0, $result->images);

        $this->assertCount(1, $result->vendor_infos);
        $vendorInfo = $result->vendor_infos[0];
        $this->assertInstanceOf(PurchaseInfoDTO::class, $vendorInfo);
        $this->assertSame('TME', $vendorInfo->distributor_name);
        $this->assertSame('SMD0603-5K1-1%', $vendorInfo->order_number);
        $this->assertSame(
            'https://www.tme.eu/en/details/smd0603-5k1-1%25/smd-resistors/royalohm/0603saf5101t5e/',
            $vendorInfo->product_url
        );
        $this->assertCount(3, $vendorInfo->prices);
        $this->assertSame(100.0, $vendorInfo->prices[0]->minimum_discount_amount);
        $this->assertSame('0.01077', $vendorInfo->prices[0]->price);
        $this->assertSame('EUR', $vendorInfo->prices[0]->currency_iso_code);
        $this->assertFalse($vendorInfo->prices[0]->includes_tax);

        $this->assertCount(5, $result->parameters);
    }

    public function testGetDetailsForEtqp3m6r8kvp(): void
    {
        $this->httpClient->setResponseFactory([
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
        $this->assertSame(ManufacturingStatus::ACTIVE, $result->manufacturing_status);
        $this->assertSame(0.44, $result->mass);
        $this->assertNull($result->footprint);
        $this->assertSame('https://www.tme.eu/en/details/etqp3m6r8kvp/inductors/panasonic/', $result->provider_url);

        $this->assertCount(2, $result->datasheets);
        $this->assertSame('https://www.tme.eu/Document/50a845881f09d8a2248350946e11df38/AGL0000C63.pdf', $result->datasheets[0]->url);
        $this->assertCount(0, $result->images);

        $this->assertCount(1, $result->vendor_infos);
        $vendorInfo = $result->vendor_infos[0];
        $this->assertSame('TME', $vendorInfo->distributor_name);
        $this->assertSame('ETQP3M6R8KVP', $vendorInfo->order_number);
        $this->assertSame('https://www.tme.eu/en/details/etqp3m6r8kvp/inductors/panasonic/', $vendorInfo->product_url);
        $this->assertCount(3, $vendorInfo->prices);
        $this->assertSame(1.0, $vendorInfo->prices[0]->minimum_discount_amount);
        $this->assertSame('0.589', $vendorInfo->prices[0]->price);
        $this->assertSame('EUR', $vendorInfo->prices[0]->currency_iso_code);
        $this->assertFalse($vendorInfo->prices[0]->includes_tax);

        $this->assertCount(3, $result->parameters);
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
