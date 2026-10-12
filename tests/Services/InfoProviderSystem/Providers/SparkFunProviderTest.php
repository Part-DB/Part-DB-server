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

use App\Entity\Parts\ManufacturingStatus;
use App\Services\InfoProviderSystem\DTOs\PartDetailDTO;
use App\Services\InfoProviderSystem\DTOs\ProviderInfoDTO;
use App\Services\InfoProviderSystem\DTOs\SearchResultDTO;
use App\Services\InfoProviderSystem\Providers\ProviderCapabilities;
use App\Services\InfoProviderSystem\Providers\SparkFunProvider;
use App\Settings\InfoProviderSystem\SparkFunSettings;
use App\Tests\SettingsTestHelper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class SparkFunProviderTest extends TestCase
{
    private SparkFunSettings $settings;
    private SparkFunProvider $provider;
    private MockHttpClient $httpClient;
    private ArrayAdapter $cache;

    protected function setUp(): void
    {
        $this->httpClient = new MockHttpClient();
        $this->cache = new ArrayAdapter();
        $this->settings = SettingsTestHelper::createSettingsDummy(SparkFunSettings::class);
        $this->settings->enabled = true;
        //The tests should not wait between the requests
        $this->settings->requestDelay = 0;
        $this->provider = new SparkFunProvider($this->httpClient, $this->settings, $this->cache);
    }

    /**
     * A product like the GraphQL endpoint of the shop returns it
     */
    private function productData(string $sku = 'BOB-12009', string $name = 'SparkFun Logic Level Converter - Bi-Directional'): array
    {
        return [
            '__typename' => 'SimpleProduct',
            'sku' => $sku,
            'name' => $name,
            'url_key' => 'sparkfun-logic-level-converter-bi-directional',
            'url_suffix' => '.html',
            'stock_status' => 'IN_STOCK',
            'small_image' => ['url' => 'https://www.sparkfun.com/media/catalog/product/1/2/12009-06.jpg'],
            'short_description' => ['html' => '<p>Safely steps down 5V signals to 3.3V &amp; steps up 3.3V to 5V.</p>'],
            'categories' => [
                ['name' => 'Development Boards', 'level' => 2, 'breadcrumbs' => null],
                ['name' => 'Level Shifters', 'level' => 4, 'breadcrumbs' => [
                    ['category_name' => 'Development Boards'], ['category_name' => 'Logic'],
                ]],
                ['name' => 'SparkFun Originals', 'level' => 3, 'breadcrumbs' => [['category_name' => 'Special Categories']]],
            ],
            'image' => ['url' => 'https://www.sparkfun.com/media/catalog/product/1/2/12009-06.jpg'],
            'description' => ['html' => '<p>A level converter. See also the <a href="/other-product.html">other product</a>.</p>'
                . '<p><strong>Documents:</strong></p><ul>'
                . '<li><a href="http://cdn.sparkfun.com/datasheets/BreakoutBoards/Logic_Level_Bidirectional.pdf">Schematic</a></li>'
                . '<li><a href="http://cdn.sparkfun.com/datasheets/BreakoutBoards/BSS138.pdf">Datasheet</a> (BSS138)</li>'
                . '<li><a href="https://github.com/sparkfun/Logic_Level_Bidirectional">GitHub</a></li>'
                . '<li><a href="https://www.sparkfun.com/videos#all/K3SPijvXtew/16">Product Video</a></li>'
                . '</ul>'],
            'weight' => 0.01,
            'price_range' => ['minimum_price' => ['final_price' => ['value' => 3.95, 'currency' => 'USD']]],
            'price_tiers' => [
                ['quantity' => 10, 'final_price' => ['value' => 3.75, 'currency' => 'USD']],
                ['quantity' => 25, 'final_price' => ['value' => 3.56, 'currency' => 'USD']],
            ],
            'media_gallery' => [
                ['__typename' => 'ProductImage', 'url' => 'https://www.sparkfun.com/media/catalog/product/1/2/12009-07.jpg', 'label' => 'Back', 'position' => 2, 'disabled' => false],
                ['__typename' => 'ProductVideo', 'url' => 'https://www.sparkfun.com/media/catalog/product/v/i/video.jpg', 'label' => 'Video', 'position' => 3, 'disabled' => false],
                ['__typename' => 'ProductImage', 'url' => 'https://www.sparkfun.com/media/catalog/product/1/2/12009-06.jpg', 'label' => 'Front', 'position' => 1, 'disabled' => false],
            ],
        ];
    }

    private function productPage(): string
    {
        return <<<'HTML'
            <html><body>
            <div id="content-features"><div class="am-custom-tab"><h2>Features &amp; Specs</h2><ul>
                <li>Operating Voltage: 3.3V</li>
                <li>4 bi-directional channels</li>
            </ul></div></div>
            <div id="content-documentation"><div class="am-custom-tab"><h2>Documentation</h2><ul>
                <li><a href="https://cdn.sparkfun.com/datasheets/BreakoutBoards/Logic_Level_Bidirectional.pdf">Schematic</a></li>
                <li><a href="//cdn.sparkfun.com/datasheets/BreakoutBoards/Logic_Level_Bidirectional.zip">Eagle Files</a></li>
                <li><a href="https://youtu.be/K3SPijvXtew">Product Video</a></li>
                <li><a href="https://www.sparkfun.com/arduino_guide">Arduino Buying Guide</a></li>
                <li><a href="https://www.P65Warnings.ca.gov">www.P65Warnings.ca.gov</a></li>
            </ul>
            <a href="https://learn.sparkfun.com/tutorials/bi-directional-logic-level-converter-hookup-guide" class="document-item" data-document-type="hookup-guide">
                <img src="guide.svg" alt="Hookup Guide"> Hookup Guide</a>
            </div></div>
            </body></html>
            HTML;
    }

    private function graphQLResponse(array $data): MockResponse
    {
        return new MockResponse(json_encode(['data' => $data], JSON_THROW_ON_ERROR), [
            'response_headers' => ['content-type' => 'application/json'],
        ]);
    }

    public function testGetProviderInfo(): void
    {
        $info = $this->provider->getProviderInfo();

        $this->assertInstanceOf(ProviderInfoDTO::class, $info);
        $this->assertSame('sparkfun', $info->key);
        $this->assertSame('SparkFun', $info->name);
        $this->assertSame('https://www.sparkfun.com/', $info->url);
        $this->assertContains(ProviderCapabilities::BASIC, $info->capabilities);
        $this->assertContains(ProviderCapabilities::PICTURE, $info->capabilities);
        $this->assertContains(ProviderCapabilities::PRICE, $info->capabilities);
        $this->assertContains(ProviderCapabilities::DATASHEET, $info->capabilities);
    }

    public function testIsActive(): void
    {
        $this->settings->enabled = true;
        $this->assertTrue($this->provider->isActive());

        $this->settings->enabled = false;
        $this->assertFalse($this->provider->isActive());
    }

    public function testSearchByKeyword(): void
    {
        $this->httpClient->setResponseFactory(function (string $method, string $url, array $options): MockResponse {
            $this->assertSame('POST', $method);
            $this->assertSame('https://www.sparkfun.com/graphql', $url);
            $this->assertSame('level converter', json_decode($options['body'], true)['variables']['search']);

            return $this->graphQLResponse(['products' => ['items' => [$this->productData()]]]);
        });

        $results = $this->provider->searchByKeyword('level converter');

        $this->assertCount(1, $results);
        $this->assertInstanceOf(SearchResultDTO::class, $results[0]);
        $this->assertSame('sparkfun', $results[0]->provider_key);
        $this->assertSame('BOB-12009', $results[0]->provider_id);
        $this->assertSame('BOB-12009', $results[0]->mpn);
        $this->assertSame('SparkFun', $results[0]->manufacturer);
        $this->assertSame('SparkFun Logic Level Converter - Bi-Directional', $results[0]->name);
        $this->assertSame('Safely steps down 5V signals to 3.3V & steps up 3.3V to 5V.', $results[0]->description);
        $this->assertSame('Development Boards -> Logic -> Level Shifters', $results[0]->category);
        $this->assertSame(ManufacturingStatus::ACTIVE, $results[0]->manufacturing_status);
        $this->assertSame('https://www.sparkfun.com/sparkfun-logic-level-converter-bi-directional.html', $results[0]->provider_url);
        $this->assertSame('https://www.sparkfun.com/media/catalog/product/1/2/12009-06.jpg', $results[0]->preview_image_url);
    }

    public function testSearchByKeywordPutsExactSKUFirst(): void
    {
        //The search of the shop is fuzzy and can return a neighbouring SKU first
        $this->httpClient->setResponseFactory([$this->graphQLResponse(['products' => ['items' => [
            $this->productData('PRT-08808', 'ProtoBoard - Square 1&quot; Single Sided'),
            $this->productData('PRT-08809', 'Wanted'),
        ]]])]);

        $results = $this->provider->searchByKeyword('8809');

        $this->assertCount(2, $results);
        $this->assertSame('PRT-08809', $results[0]->provider_id);
        $this->assertSame('PRT-08808', $results[1]->provider_id);
        //The shop returns the names with HTML entities
        $this->assertSame('ProtoBoard - Square 1" Single Sided', $results[1]->name);
    }

    public function testSearchByKeywordWithoutResults(): void
    {
        $this->httpClient->setResponseFactory([$this->graphQLResponse(['products' => ['items' => []]])]);

        $this->assertSame([], $this->provider->searchByKeyword('does not exist'));
    }

    public function testGetDetails(): void
    {
        $this->httpClient->setResponseFactory([
            $this->graphQLResponse(['bySku' => ['items' => [$this->productData()]], 'byUrlKey' => ['items' => []]]),
            new MockResponse($this->productPage()),
        ]);

        $details = $this->provider->getDetails('BOB-12009');

        $this->assertInstanceOf(PartDetailDTO::class, $details);
        $this->assertSame('BOB-12009', $details->provider_id);
        $this->assertSame('BOB-12009', $details->mpn);
        $this->assertSame('SparkFun', $details->manufacturer);
        $this->assertSame('SparkFun Logic Level Converter - Bi-Directional', $details->name);
        $this->assertSame('Development Boards -> Logic -> Level Shifters', $details->category);
        $this->assertSame('https://www.sparkfun.com/sparkfun-logic-level-converter-bi-directional.html', $details->provider_url);
        $this->assertSame('https://www.sparkfun.com/media/catalog/product/1/2/12009-06.jpg', $details->preview_image_url);
        $this->assertEqualsWithDelta(4.54, $details->mass, 0.001);

        //The images must be ordered by their position and must not contain the video
        $this->assertSame([
            'https://www.sparkfun.com/media/catalog/product/1/2/12009-06.jpg',
            'https://www.sparkfun.com/media/catalog/product/1/2/12009-07.jpg',
        ], array_column($details->images, 'url'));

        //Documents of the product page come first, the ones of the description follow without duplicates, videos and links to other pages
        $this->assertSame([
            'https://cdn.sparkfun.com/datasheets/BreakoutBoards/Logic_Level_Bidirectional.pdf' => 'Schematic',
            'https://cdn.sparkfun.com/datasheets/BreakoutBoards/Logic_Level_Bidirectional.zip' => 'Eagle Files',
            'https://learn.sparkfun.com/tutorials/bi-directional-logic-level-converter-hookup-guide' => 'Hookup Guide',
            'https://cdn.sparkfun.com/datasheets/BreakoutBoards/BSS138.pdf' => 'Datasheet (BSS138)',
            'https://github.com/sparkfun/Logic_Level_Bidirectional' => 'GitHub',
        ], array_column($details->datasheets, 'name', 'url'));

        //Only features with a value become a parameter
        $this->assertCount(1, $details->parameters);
        $this->assertSame('Operating Voltage', $details->parameters[0]->name);
        $this->assertSame(3.3, $details->parameters[0]->value_typ);
        $this->assertSame('V', $details->parameters[0]->unit);

        $this->assertStringContainsString('A level converter.', $details->notes);
        $this->assertStringContainsString('<li>4 bi-directional channels</li>', $details->notes);

        $this->assertCount(1, $details->vendor_infos);
        $vendorInfo = $details->vendor_infos[0];
        $this->assertSame('SparkFun', $vendorInfo->distributor_name);
        $this->assertSame('BOB-12009', $vendorInfo->order_number);
        $this->assertSame($details->provider_url, $vendorInfo->product_url);
        $this->assertFalse($vendorInfo->prices_include_vat);
        $this->assertCount(3, $vendorInfo->prices);
        $this->assertSame([1.0, 10.0, 25.0], array_column($vendorInfo->prices, 'minimum_discount_amount'));
        $this->assertSame(['3.95', '3.75', '3.56'], array_column($vendorInfo->prices, 'price'));
        $this->assertSame('USD', $vendorInfo->prices[0]->currency_iso_code);
    }

    public function testGetDetailsByNumberOnlyAcceptsExactSKU(): void
    {
        $this->httpClient->setResponseFactory(function (string $method, string $url, array $options): MockResponse {
            if ($method === 'GET') {
                return new MockResponse($this->productPage());
            }

            //The numbers of the SKUs are padded to five digits
            $this->assertSame('08809', json_decode($options['body'], true)['variables']['search']);

            return $this->graphQLResponse(['products' => ['items' => [
                $this->productData('PRT-08808', 'Neighbour'),
                $this->productData('PRT-08809', 'Wanted'),
            ]]]);
        });

        $details = $this->provider->getDetails('8809');

        $this->assertSame('PRT-08809', $details->provider_id);
        $this->assertSame('Wanted', $details->name);
    }

    public function testGetDetailsByUrlKey(): void
    {
        $this->httpClient->setResponseFactory([
            $this->graphQLResponse(['bySku' => ['items' => []], 'byUrlKey' => ['items' => [$this->productData()]]]),
            new MockResponse($this->productPage()),
        ]);

        $details = $this->provider->getDetails('sparkfun-logic-level-converter-bi-directional');

        $this->assertSame('BOB-12009', $details->provider_id);
    }

    public function testGetDetailsWorksWithoutProductPage(): void
    {
        $this->httpClient->setResponseFactory([
            $this->graphQLResponse(['bySku' => ['items' => [$this->productData()]], 'byUrlKey' => ['items' => []]]),
            new MockResponse('', ['http_code' => 503]),
        ]);

        $details = $this->provider->getDetails('BOB-12009');

        $this->assertSame('BOB-12009', $details->provider_id);
        $this->assertSame([], $details->parameters);
        //The documents of the description are still available
        $this->assertSame([
            'https://cdn.sparkfun.com/datasheets/BreakoutBoards/Logic_Level_Bidirectional.pdf',
            'https://cdn.sparkfun.com/datasheets/BreakoutBoards/BSS138.pdf',
            'https://github.com/sparkfun/Logic_Level_Bidirectional',
        ], array_column($details->datasheets, 'url'));
    }

    public function testGetDetailsThrowsIfProductDoesNotExist(): void
    {
        $this->httpClient->setResponseFactory([
            $this->graphQLResponse(['bySku' => ['items' => []], 'byUrlKey' => ['items' => []]]),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->provider->getDetails('XXX-99999');
    }

    public function testRequestsAreSpacedOut(): void
    {
        $this->settings->requestDelay = 1;

        $times = [];
        $this->httpClient->setResponseFactory(function (string $method) use (&$times): MockResponse {
            $times[] = microtime(true);
            if ($method === 'GET') {
                return new MockResponse($this->productPage());
            }
            return $this->graphQLResponse([
                'products' => ['items' => [$this->productData()]],
                'bySku' => ['items' => [$this->productData()]], 'byUrlKey' => ['items' => []],
            ]);
        });

        $start = microtime(true);
        $this->provider->searchByKeyword('BOB-12009');
        //The GraphQL request and the request of the product page
        $this->provider->getDetails('BOB-12009');

        $this->assertCount(3, $times);
        //The first request does not have to wait
        $this->assertLessThan(0.5, $times[0] - $start);
        //All others have to keep the delay to their predecessor, no matter if they go to GraphQL or a product page
        $this->assertGreaterThanOrEqual(0.95, $times[1] - $times[0]);
        $this->assertGreaterThanOrEqual(0.95, $times[2] - $times[1]);
    }

    #[DataProvider('refusedStatusProvider')]
    public function testPausesAfterShopRefusedRequest(int $status): void
    {
        $this->httpClient->setResponseFactory(fn() => new MockResponse('Go away', ['http_code' => $status]));

        try {
            $this->provider->searchByKeyword('level converter');
            $this->fail('The refused request must throw');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('HTTP status ' . $status, $e->getMessage());
            $this->assertStringContainsString('paused until', $e->getMessage());
        }
        $this->assertSame(1, $this->httpClient->getRequestsCount());

        //Further lookups must not reach the shop anymore, and tell why
        foreach ([fn() => $this->provider->searchByKeyword('redboard'), fn() => $this->provider->getDetails('BOB-12009')] as $lookup) {
            try {
                $lookup();
                $this->fail('The provider must refuse to send requests');
            } catch (\RuntimeException $e) {
                $this->assertStringContainsString('paused until', $e->getMessage());
                $this->assertStringContainsString('HTTP status ' . $status, $e->getMessage());
            }
        }
        $this->assertSame(1, $this->httpClient->getRequestsCount());
    }

    public static function refusedStatusProvider(): \Iterator
    {
        yield 'forbidden' => [403];
        yield 'too many requests' => [429];
        yield 'unavailable' => [503];
    }

    public function testNoRequestsWhilePaused(): void
    {
        //The pause is kept in the cache, so it also applies if another process has run into it
        $until = time() + 1800;
        $item = $this->cache->getItem('sparkfun_paused');
        $item->set(['until' => $until, 'reason' => 'HTTP status 429']);
        $this->cache->save($item);

        try {
            $this->provider->getDetails('BOB-12009');
            $this->fail('The provider must refuse to send requests');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('paused until ' . date('Y-m-d H:i:s T', $until), $e->getMessage());
            $this->assertStringContainsString('HTTP status 429', $e->getMessage());
        }
        $this->assertSame(0, $this->httpClient->getRequestsCount());
    }

    public function testRefusedProductPagePausesProvider(): void
    {
        $this->httpClient->setResponseFactory([
            $this->graphQLResponse(['bySku' => ['items' => [$this->productData()]], 'byUrlKey' => ['items' => []]]),
            new MockResponse('', ['http_code' => 429]),
        ]);

        //The product page is optional, so the details are still returned
        $details = $this->provider->getDetails('BOB-12009');
        $this->assertSame('BOB-12009', $details->provider_id);
        $this->assertSame(2, $this->httpClient->getRequestsCount());

        //But the next lookup must not send a request
        try {
            $this->provider->searchByKeyword('BOB-12009');
            $this->fail('The provider must refuse to send requests');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('paused until', $e->getMessage());
        }
        $this->assertSame(2, $this->httpClient->getRequestsCount());
    }

    public function testGetHandledDomains(): void
    {
        $this->assertSame(['sparkfun.com'], $this->provider->getHandledDomains());
    }

    #[DataProvider('urlProvider')]
    public function testGetIDFromURL(string $url, ?string $expected): void
    {
        $this->assertSame($expected, $this->provider->getIDFromURL($url));
    }

    public static function urlProvider(): \Iterator
    {
        yield ['https://www.sparkfun.com/products/13975', '13975'];
        yield ['https://www.sparkfun.com/products/13975?foo=bar', '13975'];
        yield ['https://www.sparkfun.com/sparkfun-redboard-programmed-with-arduino.html', 'sparkfun-redboard-programmed-with-arduino'];
        yield ['https://www.sparkfun.com/development-boards/microcontrollers.html', null];
        yield ['https://www.sparkfun.com/', null];
    }
}
