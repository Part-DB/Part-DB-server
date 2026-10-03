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

use App\Entity\Parts\ManufacturingStatus;
use App\Services\InfoProviderSystem\DTOs\PartDetailDTO;
use App\Services\InfoProviderSystem\DTOs\ProviderInfoDTO;
use App\Services\InfoProviderSystem\DTOs\SearchResultDTO;
use App\Services\InfoProviderSystem\Providers\AdafruitProvider;
use App\Services\InfoProviderSystem\Providers\InfoProviderInterface;
use App\Services\InfoProviderSystem\Providers\ProviderCapabilities;
use App\Settings\InfoProviderSystem\AdafruitSettings;
use App\Tests\SettingsTestHelper;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class AdafruitProviderTest extends TestCase
{
    private AdafruitSettings $settings;
    private AdafruitProvider $provider;
    private MockHttpClient $httpClient;

    protected function setUp(): void
    {
        $this->httpClient = new MockHttpClient();
        $this->settings = SettingsTestHelper::createSettingsDummy(AdafruitSettings::class);
        $this->settings->enabled = true;
        $this->settings->fetchProductPage = true;
        $this->provider = new AdafruitProvider($this->httpClient, $this->settings, new ArrayAdapter());
    }

    private function catalogEntry(string $id, string $name, array $overrides = []): array
    {
        return array_merge([
            'product_id' => $id,
            'product_image' => "https://cdn-shop.adafruit.com/640x480/$id-00.jpg",
            'product_image_alt' => 'Angled shot',
            'product_name' => $name,
            'product_model' => '',
            'product_mpn' => 'ADA' . $id,
            'product_manufacturer' => 'Adafruit',
            'product_price' => '24.95',
            'product_stock' => 'in stock',
            'products_hts' => '8517.62.0090',
            'products_coo' => 'US',
            'discontinue_status' => 'None',
            'products_coming_soon' => '0',
            'products_rohs' => '1',
            'discount_pricing' => [
                ['discounted_price' => '24.95', 'discounted_percent' => 0, 'show_qty' => '1-9', 'min_qty' => 1],
                ['discounted_price' => '22.46', 'discounted_percent' => '10', 'show_qty' => '10-99', 'min_qty' => 10],
                ['discounted_price' => '19.96', 'discounted_percent' => '20', 'show_qty' => '100+', 'min_qty' => 100],
            ],
        ], $overrides);
    }

    private function catalogResponse(): MockResponse
    {
        return new MockResponse(json_encode([
            $this->catalogEntry('4062', 'Adafruit Feather nRF52840 Express'),
            $this->catalogEntry('3857', 'Adafruit Feather M4 Express - Featuring ATSAMD51', ['product_model' => 'ATSAMD51 Cortex M4']),
            $this->catalogEntry('3800', 'Adafruit ItsyBitsy M4 Express featuring ATSAMD51'),
            $this->catalogEntry('2000', 'Old Feather M4 Prototype', ['discontinue_status' => 'Discontinued', 'product_stock' => '-3']),
            $this->catalogEntry('64', 'Half Sized Premium Breadboard - 400 Tie Points', ['product_manufacturer' => null]),
            $this->catalogEntry('5427', 'Espruino Bangle.js v2 - Hackable Javascript Smart Watch', ['product_manufacturer' => 'Espruino', 'discontinue_status' => 'Pending']),
        ], JSON_THROW_ON_ERROR));
    }

    private function productPage(): string
    {
        return <<<'HTML'
            <html><head><meta charset="utf-8"><title>Adafruit Feather nRF52840 Express</title></head><body>
            <nav aria-label="Breadcrumbs" class="breadcrumbs"><div class="container">
                <a href="/category/943">Feather</a> / <a href="/category/946">Boards</a>
                <span class="hidden-xs-inline">/</span><span class="hidden-xs-inline">Adafruit Feather nRF52840 Express</span>
            </div></nav>
            <div class="gallery-thumbnails"><img src="https://cdn-shop.adafruit.com/145x109/4062-02.jpg" alt="thumb"></div>
            <div class="gallery-slides"><div class="gallery-slides-scroll">
                <div class="gallery-slide" id="gallery-slide-0"><img id="Slide1" src="https://cdn-shop.adafruit.com/970x728/4062-02.jpg" alt="Angled shot"></div>
                <div class="gallery-slide" data-slide-type="video" id="gallery-slide-1"><div role="img"><video poster="https://cdn-shop.adafruit.com/product-videos/1024x768/4062-05.jpg">
                    <source src="https://cdn-shop.adafruit.com/product-videos/1024x768/4062-05.mp4" type="video/mp4"/>
                    <img src="https://cdn-shop.adafruit.com/product-videos/1024x768/4062-05.jpg" alt="Top down view"/></video></div></div>
                <div class="gallery-slide" id="gallery-slide-2"><img src="https://cdn-shop.adafruit.com/970x728/4062-06.jpg" alt="Back"></div>
                <div class="gallery-slide" id="gallery-slide-3"><div><a class="gallery-slide gallery-slide-youtube" href="//www.youtube.com/watch?v=abc">
                    <img src="https://cdn-shop.adafruit.com/ytimg/vi/abc/hqdefault.jpg" alt="Some video"></a></div></div>
            </div></div>
            <div id="tab-technical-details-content"><div class="row"><div><ul>
                <li>Dimensions (unassembled): 51mm x 23mm x 7.2mm / 2&quot; x 0.9&quot; x 0.28&quot;</li>
                <li>Weight: 6g</li>
                <li>This entry is no parameter</li>
                <li><a href="https://cdn-shop.adafruit.com/product-files/4062/board.pdf">Schematic</a></li>
            </ul></div></div></div>
            <div class="learn-guides-list">
                <div class="product-learn-guide learn-guide-primary"><div class="product-learn-guide-text"><div class="product-learn-guide-title">
                    <a href="https://learn.adafruit.com/introducing-the-adafruit-nrf52840-feather">
                        Primary Guide:                 Introducing the Adafruit nRF52840 Feather                </a>
                </div></div></div>
                <div class="product-learn-guide "><div class="product-learn-guide-text"><div class="product-learn-guide-title">
                    <a href="https://learn.adafruit.com/some-project">Some Project</a>
                </div></div></div>
            </div>
            </body></html>
            HTML;
    }

    private function productResponse(array $overrides = []): MockResponse
    {
        return new MockResponse(json_encode($this->catalogEntry('4062', 'Adafruit Feather nRF52840 Express', array_merge([
            'product_stock' => '53',
            'products_description' => '<p>The <strong>Adafruit Feather nRF52840 Express</strong> is the new Feather family member with Bluetooth&reg; Low Energy!&nbsp; It&#39;s our take on an all-in-one board.</p>'
                . '<p>Details are in the <a href="https://infocenter.nordicsemi.com/pdf/nRF52840_PS_v1.1.pdf">nRF52840 product specification</a>, see also <a href="https://www.adafruit.com/feather">the other Feathers</a>.</p>',
        ], $overrides)), JSON_THROW_ON_ERROR));
    }

    public function testGetProviderInfo(): void
    {
        $info = $this->provider->getProviderInfo();

        $this->assertInstanceOf(ProviderInfoDTO::class, $info);
        $this->assertSame('adafruit', $info->key);
        $this->assertSame('Adafruit', $info->name);
        $this->assertSame('https://www.adafruit.com/', $info->url);
        $this->assertContains(ProviderCapabilities::BASIC, $info->capabilities);
        $this->assertContains(ProviderCapabilities::PICTURE, $info->capabilities);
        $this->assertContains(ProviderCapabilities::DATASHEET, $info->capabilities);
        $this->assertContains(ProviderCapabilities::PRICE, $info->capabilities);
        $this->assertContains(ProviderCapabilities::PARAMETERS, $info->capabilities);
    }

    public function testIsActive(): void
    {
        $this->settings->enabled = true;
        $this->assertTrue($this->provider->isActive());

        $this->settings->enabled = false;
        $this->assertFalse($this->provider->isActive());
    }

    public function testSearchByProductID(): void
    {
        $this->httpClient->setResponseFactory([$this->catalogResponse()]);

        foreach (['4062', 'ADA4062', '#4062', 'https://www.adafruit.com/product/4062'] as $keyword) {
            $results = $this->provider->searchByKeyword($keyword);

            $this->assertNotEmpty($results, $keyword);
            $this->assertInstanceOf(SearchResultDTO::class, $results[0]);
            $this->assertSame('4062', $results[0]->provider_id, $keyword);
        }

        $result = $this->provider->searchByKeyword('4062')[0];
        $this->assertSame('adafruit', $result->provider_key);
        $this->assertSame('Adafruit Feather nRF52840 Express', $result->name);
        $this->assertSame('Adafruit', $result->manufacturer);
        $this->assertSame('ADA4062', $result->mpn);
        $this->assertSame('https://cdn-shop.adafruit.com/640x480/4062-00.jpg', $result->preview_image_url);
        $this->assertSame('https://www.adafruit.com/product/4062', $result->provider_url);
        $this->assertSame(ManufacturingStatus::ACTIVE, $result->manufacturing_status);
    }

    public function testSearchByKeyword(): void
    {
        $this->httpClient->setResponseFactory([$this->catalogResponse()]);

        //All words must occur, the case and the punctuation do not matter
        $results = $this->provider->searchByKeyword('feather M4');
        $this->assertSame(['3857', '2000'], array_map(static fn(SearchResultDTO $dto) => $dto->provider_id, $results));
        $this->assertSame('ATSAMD51 Cortex M4', $results[0]->description);
        $this->assertSame(ManufacturingStatus::DISCONTINUED, $results[1]->manufacturing_status);

        $results = $this->provider->searchByKeyword('atsamd51 express');
        $this->assertSame(['3857', '3800'], array_map(static fn(SearchResultDTO $dto) => $dto->provider_id, $results));

        //The manufacturer is searched too, and is taken from the catalog if it is given there
        $results = $this->provider->searchByKeyword('espruino');
        $this->assertCount(1, $results);
        $this->assertSame('Espruino', $results[0]->manufacturer);
        $this->assertSame(ManufacturingStatus::EOL, $results[0]->manufacturing_status);

        //Products without manufacturer are sold under the name of Adafruit
        $results = $this->provider->searchByKeyword('breadboard');
        $this->assertCount(1, $results);
        $this->assertSame('Adafruit', $results[0]->manufacturer);

        $this->assertSame([], $this->provider->searchByKeyword('does not exist'));
        $this->assertSame([], $this->provider->searchByKeyword('  '));
    }

    public function testCatalogIsOnlyDownloadedOnce(): void
    {
        $this->httpClient->setResponseFactory([$this->catalogResponse(), $this->catalogResponse()]);

        $this->provider->searchByKeyword('feather');
        $this->provider->searchByKeyword('breadboard');
        $this->provider->searchByKeyword('4062');
        $this->assertSame(1, $this->httpClient->getRequestsCount());

        //Except a fresh result is requested explicitly
        $this->provider->searchByKeyword('feather', [InfoProviderInterface::OPTION_NO_CACHE => true]);
        $this->assertSame(2, $this->httpClient->getRequestsCount());
    }

    public function testGetDetails(): void
    {
        $requestedUrls = [];
        $responses = [$this->productResponse(), new MockResponse($this->productPage())];
        $this->httpClient->setResponseFactory(function (string $method, string $url) use (&$requestedUrls, &$responses) {
            $requestedUrls[] = $url;
            return array_shift($responses);
        });

        $details = $this->provider->getDetails('4062');

        $this->assertSame(['https://www.adafruit.com/api/product/4062', 'https://www.adafruit.com/product/4062'], $requestedUrls);

        $this->assertInstanceOf(PartDetailDTO::class, $details);
        $this->assertSame('adafruit', $details->provider_key);
        $this->assertSame('4062', $details->provider_id);
        $this->assertSame('Adafruit Feather nRF52840 Express', $details->name);
        $this->assertSame('The Adafruit Feather nRF52840 Express is the new Feather family member with Bluetooth® Low Energy!', $details->description);
        $this->assertSame('Feather -> Boards', $details->category);
        $this->assertSame('Adafruit', $details->manufacturer);
        $this->assertSame('ADA4062', $details->mpn);
        $this->assertSame('https://cdn-shop.adafruit.com/640x480/4062-00.jpg', $details->preview_image_url);
        $this->assertSame(ManufacturingStatus::ACTIVE, $details->manufacturing_status);
        $this->assertSame('https://www.adafruit.com/product/4062', $details->provider_url);
        $this->assertSame('https://www.adafruit.com/product/4062', $details->manufacturer_product_url);
        $this->assertStringContainsString('<strong>Adafruit Feather nRF52840 Express</strong>', $details->notes);

        //The images of the gallery (without thumbnails and YouTube videos), for product videos the poster image
        $this->assertSame([
            'https://cdn-shop.adafruit.com/970x728/4062-02.jpg',
            'https://cdn-shop.adafruit.com/product-videos/1024x768/4062-05.jpg',
            'https://cdn-shop.adafruit.com/970x728/4062-06.jpg',
        ], array_map(static fn($file) => $file->url, $details->images));

        //Learn guides first, then the documents linked in the description and the technical details
        $this->assertSame([
            'https://learn.adafruit.com/introducing-the-adafruit-nrf52840-feather',
            'https://learn.adafruit.com/some-project',
            'https://infocenter.nordicsemi.com/pdf/nRF52840_PS_v1.1.pdf',
            'https://cdn-shop.adafruit.com/product-files/4062/board.pdf',
        ], array_map(static fn($file) => $file->url, $details->datasheets));
        $this->assertSame('Learn guide: Introducing the Adafruit nRF52840 Feather', $details->datasheets[0]->name);
        $this->assertFalse($details->datasheets[0]->downloadable);
        $this->assertSame('nRF52840 product specification', $details->datasheets[2]->name);
        $this->assertTrue($details->datasheets[2]->downloadable);

        $parameters = [];
        foreach ($details->parameters as $parameter) {
            $parameters[$parameter->name] = $parameter;
        }
        $this->assertSame(['Dimensions (unassembled)', 'Weight', 'Country of origin', 'HTS code', 'RoHS compliant'], array_keys($parameters));
        $this->assertSame('51mm x 23mm x 7.2mm / 2" x 0.9" x 0.28"', $parameters['Dimensions (unassembled)']->value_text);
        $this->assertSame(6.0, $parameters['Weight']->value_typ);
        $this->assertSame('g', $parameters['Weight']->unit);
        $this->assertSame('US', $parameters['Country of origin']->value_text);
        $this->assertSame('Yes', $parameters['RoHS compliant']->value_text);

        $this->assertCount(1, $details->vendor_infos);
        $purchaseInfo = $details->vendor_infos[0];
        $this->assertSame('Adafruit', $purchaseInfo->distributor_name);
        $this->assertSame('4062', $purchaseInfo->order_number);
        $this->assertSame('https://www.adafruit.com/product/4062', $purchaseInfo->product_url);
        $this->assertSame(53.0, $purchaseInfo->available_amount);
        $this->assertSame(
            [[1.0, '24.95', 'USD'], [10.0, '22.46', 'USD'], [100.0, '19.96', 'USD']],
            array_map(static fn($price) => [$price->minimum_discount_amount, $price->price, $price->currency_iso_code], $purchaseInfo->prices)
        );
    }

    public function testGetDetailsWithoutProductPage(): void
    {
        $this->settings->fetchProductPage = false;
        $this->httpClient->setResponseFactory([$this->productResponse([
            'product_model' => 'v2.0',
            'product_manufacturer' => 'Espruino',
            'product_stock' => 'in stock',
            'discount_pricing' => [],
            'product_price' => '99.95',
            'product_sale_price' => '59.97',
            'discontinue_status' => 'Pending',
        ])]);

        $details = $this->provider->getDetails('4062');

        $this->assertSame(1, $this->httpClient->getRequestsCount());
        $this->assertNull($details->category);
        $this->assertSame('Espruino', $details->manufacturer);
        $this->assertNull($details->manufacturer_product_url);
        $this->assertStringStartsWith('v2.0 - The Adafruit Feather', $details->description);
        $this->assertSame(ManufacturingStatus::EOL, $details->manufacturing_status);

        //The image of the API is used, and only the documents of the description are available
        $this->assertCount(1, $details->images);
        $this->assertSame('https://cdn-shop.adafruit.com/640x480/4062-00.jpg', $details->images[0]->url);
        $this->assertCount(1, $details->datasheets);
        $this->assertSame(['Country of origin', 'HTS code', 'RoHS compliant'], array_map(static fn($p) => $p->name, $details->parameters));

        //Without quantity discounts there is only one price, the stock is unknown
        $purchaseInfo = $details->vendor_infos[0];
        $this->assertNull($purchaseInfo->available_amount);
        $this->assertCount(1, $purchaseInfo->prices);
        $this->assertSame('59.97', $purchaseInfo->prices[0]->price);
        $this->assertSame(1.0, $purchaseInfo->prices[0]->minimum_discount_amount);
    }

    public function testGetDetailsIfProductPageIsNotAvailable(): void
    {
        $this->httpClient->setResponseFactory([
            $this->productResponse(['product_stock' => '-3', 'discontinue_status' => 'Discontinued']),
            new MockResponse('Forbidden', ['http_code' => 403]),
        ]);

        $details = $this->provider->getDetails('4062');

        //The data of the API is still returned
        $this->assertSame('Adafruit Feather nRF52840 Express', $details->name);
        $this->assertNull($details->category);
        $this->assertCount(1, $details->images);
        $this->assertSame(ManufacturingStatus::DISCONTINUED, $details->manufacturing_status);
        $this->assertSame(0.0, $details->vendor_infos[0]->available_amount);
    }

    public function testGetDetailsWithInvalidID(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->provider->getDetails('ADA4062');
    }

    public function testGetDetailsOfUnknownProduct(): void
    {
        $this->httpClient->setResponseFactory([new MockResponse('[]')]);

        $this->expectException(\RuntimeException::class);
        $this->provider->getDetails('999999');
    }

    public function testGetHandledDomains(): void
    {
        $this->assertSame(['adafruit.com'], $this->provider->getHandledDomains());
    }

    public function testGetIDFromURL(): void
    {
        $this->assertSame('4062', $this->provider->getIDFromURL('https://www.adafruit.com/product/4062'));
        $this->assertSame('64', $this->provider->getIDFromURL('http://adafruit.com/products/64?foo=bar'));
        $this->assertNull($this->provider->getIDFromURL('https://www.adafruit.com/category/943'));
        $this->assertNull($this->provider->getIDFromURL('https://learn.adafruit.com/some-guide'));
    }
}
