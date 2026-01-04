<?php

declare(strict_types=1);

namespace App\Tests\Services\InfoProviderSystem\Providers;

use App\Services\InfoProviderSystem\DTOs\PartDetailDTO;
use App\Services\InfoProviderSystem\DTOs\SearchResultDTO;
use App\Services\InfoProviderSystem\Providers\BuerklinProvider;
use App\Settings\InfoProviderSystem\BuerklinSettings;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Full behavioral test suite for BuerklinProvider.
 * Includes parameter parsing, compliance parsing, images, prices and batch mode.
 */
class BuerklinProviderTest extends TestCase
{
    private HttpClientInterface $httpClient;
    private CacheItemPoolInterface $cache;
    private BuerklinSettings $settings;
    private BuerklinProvider $provider;

    protected function setUp(): void
    {
        $this->httpClient = $this->createMock(HttpClientInterface::class);

        // Cache mock
        $cacheItem = $this->createMock(CacheItemInterface::class);
        $cacheItem->method('isHit')->willReturn(false);
        $cacheItem->method('set')->willReturn($cacheItem);

        $this->cache = $this->createMock(CacheItemPoolInterface::class);
        $this->cache->method('getItem')->willReturn($cacheItem);

        // IMPORTANT: Settings must not be instantiated directly (SettingsBundle forbids constructor)
        $ref = new \ReflectionClass(BuerklinSettings::class);
        /** @var BuerklinSettings $settings */
        $settings = $ref->newInstanceWithoutConstructor();

        $settings->clientId = 'CID';
        $settings->secret = 'SECRET';
        $settings->username = 'USER';
        $settings->password = 'PASS';
        $settings->language = 'en';
        $settings->currency = 'EUR';

        $this->settings = $settings;

        $this->provider = new BuerklinProvider(
            client: $this->httpClient,
            partInfoCache: $this->cache,
            settings: $this->settings,
        );
    }

    private function mockApi(string $expectedUrl, array $jsonResponse): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn($jsonResponse);

        $this->httpClient
            ->method('request')
            ->with(
                'GET',
                $this->callback(fn($url) => str_contains((string) $url, $expectedUrl)),
                $this->anything()
            )
            ->willReturn($response);
    }

    public function testAttributesToParametersParsesUnitsAndValues(): void
    {
        $method = new \ReflectionMethod(BuerklinProvider::class, 'attributesToParameters');
        $method->setAccessible(true);

        $features = [
            [
                'name' => 'Zener voltage',
                'featureUnit' => ['symbol' => 'V'],
                'featureValues' => [
                    ['value' => '12']
                ]
            ],
            [
                'name' => 'Length',
                'featureUnit' => ['symbol' => 'mm'],
                'featureValues' => [
                    ['value' => '2.9']
                ]
            ],
            [
                'name' => 'Assembly',
                'featureUnit' => [],
                'featureValues' => [
                    ['value' => 'SMD']
                ]
            ]
        ];

        $params = $method->invoke($this->provider, $features, '');

        $this->assertCount(3, $params);

        $this->assertSame('Zener voltage', $params[0]->name);
        $this->assertNull($params[0]->value_text);
        $this->assertSame(12.0, $params[0]->value_typ);
        $this->assertNull($params[0]->value_min);
        $this->assertNull($params[0]->value_max);
        $this->assertSame('V', $params[0]->unit);

        $this->assertSame('Length', $params[1]->name);
        $this->assertNull($params[1]->value_text);
        $this->assertSame(2.9, $params[1]->value_typ);
        $this->assertSame('mm', $params[1]->unit);

        $this->assertSame('Assembly', $params[2]->name);
        $this->assertSame('SMD', $params[2]->value_text);
        $this->assertNull($params[2]->unit);
    }

    public function testComplianceParameters(): void
    {
        $method = new \ReflectionMethod(BuerklinProvider::class, 'complianceToParameters');
        $method->setAccessible(true);

        $product = [
            'labelRoHS' => 'Yes',
            'dateRoHS' => '2015-03-31T00:00+0000',
            'SVHC' => true,
            'hazardousGood' => false,
            'hazardousMaterials' => false,
            'countryOfOrigin' => 'China',
            'articleCustomsCode' => '85411000'
        ];

        $params = $method->invoke($this->provider, $product, 'Compliance');

        $map = [];
        foreach ($params as $p) {
            $map[$p->name] = $p->value_text;
        }

        $this->assertSame('Yes', $map['RoHS conform']);
        $this->assertSame('2015-03-31', $map['RoHS date']);
        $this->assertSame('Yes', $map['SVHC free']);
        $this->assertSame('No', $map['Hazardous good']);
        $this->assertSame('No', $map['Hazardous materials']);
        $this->assertSame('China', $map['Country of origin']);
        $this->assertSame('85411000', $map['Customs code']);
    }

    public function testImageSelectionPrefersZoomAndDeduplicates(): void
    {
        $method = new \ReflectionMethod(BuerklinProvider::class, 'getProductImages');
        $method->setAccessible(true);

        $images = [
            ['format' => 'product', 'url' => '/img/a.webp'],
            ['format' => 'zoom', 'url' => '/img/z.webp'],
            ['format' => 'zoom', 'url' => '/img/z.webp'], // duplicate
            ['format' => 'thumbnail', 'url' => '/img/t.webp']
        ];

        $results = $method->invoke($this->provider, $images);

        $this->assertCount(1, $results);
        $this->assertSame('https://www.buerklin.com/img/z.webp', $results[0]->url);
    }

    public function testFootprintExtraction(): void
    {
        $method = new \ReflectionMethod(BuerklinProvider::class, 'getPartDetail');
        $method->setAccessible(true);

        $product = [
            'code' => 'TEST1',
            'manufacturerProductId' => 'ABC',
            'description' => 'X',
            'images' => [],
            'classifications' => [
                [
                    'name' => 'Cat',
                    'features' => [
                        [
                            'name' => 'Enclosure',
                            'featureValues' => [['value' => 'SOT-23']]
                        ]
                    ]
                ]
            ],
            'price' => ['value' => 1, 'currencyIso' => 'EUR']
        ];

        $dto = $method->invoke($this->provider, $product);
        $this->assertSame('SOT-23', $dto->footprint);
    }

    public function testPriceFormatting(): void
    {
        $detailPrice = [
            [
                'minQuantity' => 1,
                'value' => 0.0885,
                'currencyIso' => 'EUR'
            ]
        ];

        $method = new \ReflectionMethod(BuerklinProvider::class, 'pricesToVendorInfo');
        $method->setAccessible(true);

        $vendorInfo = $method->invoke($this->provider, 'SKU1', 'https://x', $detailPrice);

        $price = $vendorInfo[0]->prices[0];
        $this->assertSame('0.0885', $price->price);
    }

    public function testBatchSearchReturnsSearchResultDTO(): void
    {
        $mockDetail = new PartDetailDTO(
            provider_key: 'buerklin',
            provider_id: 'TESTID',
            name: 'Zener',
            description: 'Desc'
        );

        $provider = $this->getMockBuilder(BuerklinProvider::class)
            ->setConstructorArgs([
                $this->httpClient,
                $this->cache,
                $this->settings
            ])
            ->onlyMethods(['searchByKeyword'])
            ->getMock();

        $provider->method('searchByKeyword')->willReturn([$mockDetail]);

        $result = $provider->searchByKeywordsBatch(['ABC']);

        $this->assertArrayHasKey('ABC', $result);
        $this->assertIsArray($result['ABC']);
        $this->assertCount(1, $result['ABC']);
        $this->assertInstanceOf(SearchResultDTO::class, $result['ABC'][0]);
        $this->assertSame('Zener', $result['ABC'][0]->name);
    }

    public function testConvertPartDetailToSearchResult(): void
    {
        $detail = new PartDetailDTO(
            provider_key: 'buerklin',
            provider_id: 'X1',
            name: 'PartX',
            description: 'D',
            preview_image_url: 'https://img'
        );

        $method = new \ReflectionMethod(BuerklinProvider::class, 'convertPartDetailToSearchResult');
        $method->setAccessible(true);

        $dto = $method->invoke($this->provider, $detail);

        $this->assertInstanceOf(SearchResultDTO::class, $dto);
        $this->assertSame('X1', $dto->provider_id);
        $this->assertSame('PartX', $dto->name);
        $this->assertSame('https://img', $dto->preview_image_url);
    }
}
