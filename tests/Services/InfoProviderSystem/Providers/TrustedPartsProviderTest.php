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

namespace App\Tests\Services\InfoProviderSystem\Providers;

use App\Services\InfoProviderSystem\DTOs\PartDetailDTO;
use App\Services\InfoProviderSystem\Providers\ProviderCapabilities;
use App\Services\InfoProviderSystem\Providers\TrustedPartsProvider;
use App\Settings\InfoProviderSystem\TrustedPartsSettings;
use App\Tests\SettingsTestHelper;
use PHPUnit\Framework\TestCase;
use Shivas\VersioningBundle\Service\VersionManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Version\Version;

final class TrustedPartsProviderTest extends TestCase
{
    private TrustedPartsSettings $settings;

    protected function setUp(): void
    {
        $this->settings = SettingsTestHelper::createSettingsDummy(TrustedPartsSettings::class);
        $this->settings->companyId = 'Test Company';
        $this->settings->apiKey = 'test-api-key';
    }

    /**
     * @param  MockResponse[]  $responses
     */
    private function getProvider(array $responses, ?MockHttpClient &$httpClient = null): TrustedPartsProvider
    {
        $httpClient = new MockHttpClient($responses);

        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn(null);

        $versionManager = $this->createMock(VersionManagerInterface::class);
        $versionManager->method('getVersion')->willReturn(Version::fromString('1.2.3'));

        return new TrustedPartsProvider($httpClient, $this->settings, new ArrayAdapter(), $security, $versionManager);
    }

    private function getSearchResponse(): MockResponse
    {
        return new MockResponse(json_encode([
            'Messages' => [],
            'ErrorMessage' => null,
            'PartResults' => [
                [
                    'PartNumber' => 'LM358DR',
                    'Manufacturer' => 'Texas Instruments',
                    'ManufacturerId' => 1,
                    'ProductUrl' => 'https://www.trustedparts.com/en/part/texas-instruments/LM358DR',
                    'Specifications' => [
                        ['Key' => 'Package / Case', 'Value' => 'SOIC-8'],
                        ['Key' => 'Subcategory', 'Value' => 'Amplifier ICs'],
                        ['Key' => 'GBP - Gain Bandwidth Product', 'Value' => '700 kHz'],
                        //Specifications without a value should be skipped
                        ['Key' => 'Shutdown', 'Value' => ''],
                    ],
                    'Distributors' => [
                        [
                            'Id' => 1,
                            'Name' => 'DigiKey',
                            'DistributorResults' => [
                                [
                                    'Description' => 'Standard Amplifier 2 Circuit 8-SOIC',
                                    'DistributorPartNumber' => '296-LM358DRCT-ND',
                                    'Stock' => ['QuantityOnHand' => 1000.0, 'Availability' => 'In Stock'],
                                    'Links' => [
                                        ['Type' => 'Buy', 'Url' => 'https://www.trustedparts.com/productredirect?id=buy-digikey'],
                                        ['Type' => 'Datasheet', 'Url' => 'https://www.trustedparts.com/productredirect?id=datasheet'],
                                    ],
                                    'Pricing' => [
                                        'CurrencyCode' => 'EUR',
                                        'MinimumQuantity' => 1.0,
                                        'Prices' => [
                                            ['Quantity' => 1.0, 'Amount' => 0.22],
                                            ['Quantity' => 10.0, 'Amount' => 0.15],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                        [
                            'Id' => 2,
                            'Name' => 'Mouser Electronics',
                            'DistributorResults' => [
                                [
                                    'Description' => 'Operational Amplifiers - Op Amps',
                                    //Distributors which do not provide an own part number
                                    'DistributorPartNumber' => 'N/A',
                                    'Stock' => ['QuantityOnHand' => 0.0, 'Availability' => 'Out of stock'],
                                    'Links' => [
                                        ['Type' => 'View', 'Url' => 'https://www.trustedparts.com/productredirect?id=view-mouser'],
                                        //Empty datasheet links must be skipped
                                        ['Type' => 'Datasheet', 'Url' => ''],
                                    ],
                                    'Pricing' => ['CurrencyCode' => 'USD', 'MinimumQuantity' => 0.0],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ], JSON_THROW_ON_ERROR), ['response_headers' => ['content-type' => 'application/json']]);
    }

    public function testGetProviderInfo(): void
    {
        $info = $this->getProvider([])->getProviderInfo();

        $this->assertSame('trustedparts', $info->key);
        $this->assertSame('TrustedParts', $info->name);
        $this->assertSame('https://www.trustedparts.com/', $info->url);
        $this->assertSame(TrustedPartsSettings::class, $info->settingsClass);
        $this->assertContains(ProviderCapabilities::BASIC, $info->capabilities);
        $this->assertContains(ProviderCapabilities::PRICE, $info->capabilities);
        $this->assertContains(ProviderCapabilities::DATASHEET, $info->capabilities);
        $this->assertContains(ProviderCapabilities::PARAMETERS, $info->capabilities);
        $this->assertContains(ProviderCapabilities::FOOTPRINT, $info->capabilities);
    }

    public function testIsActive(): void
    {
        $provider = $this->getProvider([]);
        $this->assertTrue($provider->isActive());

        $this->settings->apiKey = null;
        $this->assertFalse($provider->isActive());

        $this->settings->apiKey = 'test-api-key';
        $this->settings->companyId = '';
        $this->assertFalse($provider->isActive());
    }

    public function testSearchByKeywordReturnsMappedResults(): void
    {
        $provider = $this->getProvider([$this->getSearchResponse()], $httpClient);
        $results = $provider->searchByKeyword('LM358DR');

        $this->assertCount(1, $results);
        $result = $results[0];
        $this->assertInstanceOf(PartDetailDTO::class, $result);

        $this->assertSame('trustedparts', $result->provider_key);
        $this->assertSame('Texas Instruments|LM358DR', $result->provider_id);
        $this->assertSame('LM358DR', $result->name);
        $this->assertSame('LM358DR', $result->mpn);
        $this->assertSame('Texas Instruments', $result->manufacturer);
        //The part itself has no description, so the one of the first distributor is used
        $this->assertSame('Standard Amplifier 2 Circuit 8-SOIC', $result->description);
        $this->assertSame('Amplifier ICs', $result->category);
        $this->assertSame('SOIC-8', $result->footprint);
        $this->assertSame('https://www.trustedparts.com/en/part/texas-instruments/LM358DR', $result->provider_url);

        //Specifications without a value are skipped
        $this->assertCount(3, $result->parameters);

        //Datasheets without an URL are skipped
        $this->assertCount(1, $result->datasheets);
        $this->assertSame('https://www.trustedparts.com/productredirect?id=datasheet', $result->datasheets[0]->url);
        $this->assertSame('Datasheet (DigiKey)', $result->datasheets[0]->name);

        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testSearchByKeywordMapsOffers(): void
    {
        $results = $this->getProvider([$this->getSearchResponse()])->searchByKeyword('LM358DR');
        $orderinfos = $results[0]->vendor_infos;

        $this->assertCount(2, $orderinfos);

        $this->assertSame('DigiKey', $orderinfos[0]->distributor_name);
        $this->assertSame('296-LM358DRCT-ND', $orderinfos[0]->order_number);
        $this->assertSame('https://www.trustedparts.com/productredirect?id=buy-digikey', $orderinfos[0]->product_url);
        $this->assertFalse($orderinfos[0]->prices_include_vat);
        $this->assertCount(2, $orderinfos[0]->prices);
        $this->assertSame(1.0, $orderinfos[0]->prices[0]->minimum_discount_amount);
        $this->assertSame('0.22', $orderinfos[0]->prices[0]->price);
        $this->assertSame('EUR', $orderinfos[0]->prices[0]->currency_iso_code);

        //If a distributor does not provide an own part number, the MPN is used instead
        $this->assertSame('Mouser Electronics', $orderinfos[1]->distributor_name);
        $this->assertSame('LM358DR', $orderinfos[1]->order_number);
        //Offers without pricing information are still shown
        $this->assertSame([], $orderinfos[1]->prices);
    }

    public function testSearchByKeywordRespectsSearchLimit(): void
    {
        $this->settings->searchLimit = 1;

        $response = new MockResponse(json_encode([
            'PartResults' => [
                ['PartNumber' => 'PART1', 'Manufacturer' => 'Test', 'Specifications' => [], 'Distributors' => []],
                ['PartNumber' => 'PART2', 'Manufacturer' => 'Test', 'Specifications' => [], 'Distributors' => []],
            ],
        ], JSON_THROW_ON_ERROR), ['response_headers' => ['content-type' => 'application/json']]);

        $results = $this->getProvider([$response])->searchByKeyword('PART');
        $this->assertCount(1, $results);
    }

    public function testSearchByKeywordWithTooShortKeyword(): void
    {
        //The API requires at least two characters, so no request should be made at all
        $provider = $this->getProvider([], $httpClient);
        $this->assertSame([], $provider->searchByKeyword('A'));
        $this->assertSame(0, $httpClient->getRequestsCount());
    }

    public function testGetDetailsUsesCachedSearchResult(): void
    {
        $provider = $this->getProvider([$this->getSearchResponse()], $httpClient);
        $provider->searchByKeyword('LM358DR');

        //The part was already retrieved during the search, so no additional request must be made
        $details = $provider->getDetails('Texas Instruments|LM358DR');
        $this->assertSame('LM358DR', $details->mpn);
        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testGetDetailsQueriesApi(): void
    {
        $provider = $this->getProvider([$this->getSearchResponse()], $httpClient);

        $details = $provider->getDetails('Texas Instruments|LM358DR');
        $this->assertSame('Texas Instruments|LM358DR', $details->provider_id);
        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testGetDetailsThrowsExceptionForUnknownPart(): void
    {
        $response = new MockResponse(json_encode(['PartResults' => []], JSON_THROW_ON_ERROR),
            ['response_headers' => ['content-type' => 'application/json']]);

        $this->expectException(\RuntimeException::class);
        $this->getProvider([$response])->getDetails('Texas Instruments|DOES-NOT-EXIST');
    }

    public function testSearchByKeywordsBatch(): void
    {
        $response = new MockResponse(json_encode([
            'PartResults' => [
                //The API ignores punctuation, so this is the result for the "BAT54C" query
                ['PartNumber' => 'BAT-54C', 'Manufacturer' => 'Test', 'Specifications' => [], 'Distributors' => []],
                ['PartNumber' => 'LM358DR', 'Manufacturer' => 'Test', 'Specifications' => [], 'Distributors' => []],
            ],
        ], JSON_THROW_ON_ERROR), ['response_headers' => ['content-type' => 'application/json']]);

        $provider = $this->getProvider([$response], $httpClient);
        $results = $provider->searchByKeywordsBatch(['BAT54C', 'LM358DR', 'UNKNOWN-PART']);

        //All keywords must be present in the result, even if nothing was found for them
        $this->assertSame(['BAT54C', 'LM358DR', 'UNKNOWN-PART'], array_keys($results));
        $this->assertCount(1, $results['BAT54C']);
        $this->assertSame('BAT-54C', $results['BAT54C'][0]->mpn);
        $this->assertCount(1, $results['LM358DR']);
        $this->assertSame([], $results['UNKNOWN-PART']);

        //All keywords must be searched with a single request
        $this->assertSame(1, $httpClient->getRequestsCount());
    }

    public function testSearchByKeywordsBatchChunksLargeRequests(): void
    {
        //The API refuses requests with more than 50 part numbers, so they have to be split up
        $keywords = [];
        for ($i = 1; $i <= 120; $i++) {
            $keywords[] = 'PART-'.$i;
        }

        $emptyResponse = static fn() => new MockResponse(json_encode(['PartResults' => []], JSON_THROW_ON_ERROR),
            ['response_headers' => ['content-type' => 'application/json']]);
        $responses = [$emptyResponse(), $emptyResponse(), $emptyResponse()];

        $provider = $this->getProvider($responses, $httpClient);
        $results = $provider->searchByKeywordsBatch($keywords);

        //120 keywords must be split into 3 requests (50 + 50 + 20)
        $this->assertSame(3, $httpClient->getRequestsCount());
        $this->assertCount(50, json_decode($responses[0]->getRequestOptions()['body'], true, 512, JSON_THROW_ON_ERROR)['Queries']);
        $this->assertCount(50, json_decode($responses[1]->getRequestOptions()['body'], true, 512, JSON_THROW_ON_ERROR)['Queries']);
        $this->assertCount(20, json_decode($responses[2]->getRequestOptions()['body'], true, 512, JSON_THROW_ON_ERROR)['Queries']);

        //Every keyword must be present in the results
        $this->assertCount(120, $results);
    }

    public function testSearchByKeywordsBatchSkipsTooShortKeywords(): void
    {
        $response = new MockResponse(json_encode(['PartResults' => []], JSON_THROW_ON_ERROR),
            ['response_headers' => ['content-type' => 'application/json']]);

        $provider = $this->getProvider([$response], $httpClient);
        $results = $provider->searchByKeywordsBatch(['A', 'LM358DR']);

        $this->assertSame(1, $httpClient->getRequestsCount());
        //The too short keyword must not be sent to the API, but still be present in the results
        $this->assertSame([['SearchToken' => 'LM358DR']],
            json_decode($response->getRequestOptions()['body'], true, 512, JSON_THROW_ON_ERROR)['Queries']);
        $this->assertSame([], $results['A']);
    }

    public function testApiErrorThrowsException(): void
    {
        $response = new MockResponse(json_encode(['ErrorMessage' => 'Invalid API key', 'PartResults' => []],
            JSON_THROW_ON_ERROR), ['response_headers' => ['content-type' => 'application/json']]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid API key');
        $this->getProvider([$response])->searchByKeyword('LM358DR');
    }

    public function testHttpErrorThrowsException(): void
    {
        $response = new MockResponse('Unauthorized', ['http_code' => 401]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('TrustedParts API returned HTTP 401');
        $this->getProvider([$response])->searchByKeyword('LM358DR');
    }

    public function testRequestContainsCredentialsAndOptions(): void
    {
        $this->settings->currency = 'USD';
        $this->settings->country = 'US';
        $this->settings->language = 'de';
        $this->settings->inStockOnly = true;

        $response = $this->getSearchResponse();
        $this->getProvider([$response])->searchByKeyword('LM358DR');

        $body = $response->getRequestOptions()['body'];
        $this->assertIsString($body);
        $request = json_decode($body, true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('Test Company', $request['CompanyId']);
        $this->assertSame('test-api-key', $request['ApiKey']);
        $this->assertSame([['SearchToken' => 'LM358DR']], $request['Queries']);
        $this->assertSame('USD', $request['CurrencyCode']);
        $this->assertSame('US', $request['CountryCode']);
        $this->assertSame('de', $request['LanguageCode']);
        $this->assertTrue($request['InStockOnly']);
        //A single keyword search must allow partial matches
        $this->assertFalse($request['ExactMatch']);
        $this->assertFalse($request['IsCrawler']);
        //The terms of use require to identify the application and the user
        $this->assertSame('Part-DB/1.2.3 (system)', $request['UserAgent']);
    }
}
