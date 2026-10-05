<?php
/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2025 Jan Böhmer (https://github.com/jbtronics)
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
use App\Services\InfoProviderSystem\DTOs\SearchResultDTO;
use App\Services\InfoProviderSystem\Providers\PololuProvider;
use App\Services\InfoProviderSystem\Providers\ProviderCapabilities;
use App\Settings\InfoProviderSystem\PololuSettings;
use App\Tests\SettingsTestHelper;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class PololuProviderTest extends TestCase
{
    private PololuSettings $settings;
    private PololuProvider $provider;
    private MockHttpClient $httpClient;

    private const SEARCH_PAGE = <<<'HTML'
        <html><body><div id="main">
        <dl id='search'>
        <dt><a href="/product/2130"><img alt="DRV8833 Dual Motor Driver Carrier" src="https://a.pololu-files.com/picture/0J3864.100.jpg?abc" /></a> <a href="/product/2130">Pololu item 2130: <strong>DRV8833</strong> Dual Motor Driver Carrier</a></dt><dd>Since this board is a carrier for the <strong>DRV8833</strong> ... </dd><br />
        <dt><a href="/category/11/brushed-dc-motor-drivers">Brushed DC Motor Drivers</a></dt><dd>A category, which is no product</dd><br />
        <dt><a href="/product/2196"><img alt="Tamiya 70203 Low-Current Motor Gearbox (3-Speed)" src="https://a.pololu-files.com/picture/0J4308.100.jpg?def" /></a> <a href="/product/2196">Pololu item 2196: Tamiya 70203 Low-Current Motor Gearbox (3-Speed)</a></dt><dd>a perfect candidate for the <strong>DRV8833</strong> ... </dd><br />
        </dl>
        </div></body></html>
        HTML;

    private const PRODUCT_PAGE = <<<'HTML'
        <html><head>
        <meta property="og:image" content="https://a.pololu-files.com/picture/0J3864.1200x627.jpg?abc" />
        </head><body><div id='main'>
        <h2 id="breadcrumbs"><a href="/category/6/electronics">Electronics</a> &raquo; <a href="/category/11/brushed-dc-motor-drivers">Brushed DC Motor Drivers</a> &raquo;</h2>
        <h1 id="page_title">DRV8833 Dual Motor Driver Carrier</h1>
        <div class='product_top'><span class='product_top_pictures'>
        <a class="noscript-fallback" href="https://a.pololu-files.com/picture/0J3864.1200.jpg?abc"><img alt="" id="main_picture_img" class="zoomable" data-gallery-pictures="[{&quot;id&quot;:&quot;0J3864&quot;,&quot;caption&quot;:&quot;<p>DRV8833 dual motor driver carrier.</p>&quot;,&quot;url_medium&quot;:&quot;https://a.pololu-files.com/picture/0J3864.600x480.jpg?abc&quot;,&quot;url_full&quot;:&quot;https://a.pololu-files.com/picture/0J3864.1200.jpg?abc&quot;},{&quot;id&quot;:&quot;0J3872&quot;,&quot;caption&quot;:&quot;<p>Schematic diagram.</p>&quot;,&quot;url_full&quot;:&quot;https://a.pololu-files.com/picture/0J3872.1200.png?def&quot;}]" src="https://a.pololu-files.com/picture/0J3864.600x480.jpg?abc" /></a>
        </span><span class='order_form_section'>
        <form action='/cart/add' data-product-id='2130' method='post'>
        <table class='part_number_and_stock text_left'>
        <tr><td class='part_number'><span class='label'>Pololu item #:</span> <span class='value'>2130</span></td></tr>
        <tr><td><span class='label'>Brand:</span> <span class='value'><a href="/brands/pololu">Pololu</a></span></td></tr>
        <tr><td><div class='stock-notification-label-and-button-div' data-available-stock='148' data-product-id='0J2130'></div></td></tr>
        <tr><td colspan='2'><span class='label'>Status:</span> <span class='value'>Active and Preferred&nbsp;<a class="fa" href="/product-status-overview"></a></span></td></tr>
        </table>
        <table class='pricing'>
        <tr><th>Price break</th><th>Unit price (US$)</th></tr>
        <tr><td>
        1
        </td><td class=''>
        10.95
        </td></tr>
        <tr><td>5</td><td class=''>10.07</td></tr>
        <tr><td>100</td><td class=''>8.53</td></tr>
        </table>
        </form>
        <div id='short_description'>
        <p>This tiny breakout board for TI&#8217;s DRV8833 dual motor driver can deliver 1.2&nbsp;A per channel.</p>
        </div></span></div>
        <table class="tabs"><tr><th class="selected"><span id="name">Description</span></th>%TABS%</tr></table>
        <div class="tab_page"><h2>Overview</h2>
        <table class="picture_with_caption right"><tr><td><img src="https://a.pololu-files.com/picture/0J3865.250.jpg" /></td></tr><tr><th><p>Bottom view with dimensions.</p></th></tr></table>
        <p>We also carry a <a onclick="foo();" href="/product/2135">DRV8835 dual motor driver carrier</a>.</p>
        </div>
        </div></body></html>
        HTML;

    private const TABS = <<<'HTML'
        <th class=""><a href="/product/2130/specs"><span id="name">Specs</span> <span id="count">(5)</span></a></th>
        <th class=""><a href="/product/2130/resources"><span id="name">Resources</span> <span id="count">(4)</span></a></th>
        HTML;

    private const EMPTY_TABS = <<<'HTML'
        <th class="empty"><span id="name">Specs</span> <span id="count">(0)</span></th>
        <th class="empty"><span id="name">Resources</span> <span id="count">(0)</span></th>
        HTML;

    private const SPECS_PAGE = <<<'HTML'
        <html><body><div class="tab_page"><h2>Dimensions</h2>
        <table class='specifications'>
        <tr class='even'><th>
        Size:
        </th><td>
        0.5″ × 0.8″<sup><a href="#note1">1</a></sup>
        </td></tr>
        <tr class='odd'><th>Weight:</th><td>1.0 g<sup><a href="#note1">1</a></sup></td></tr>
        </table>
        <h2>General specifications</h2>
        <table class='specifications'>
        <tr class='even'><th>Motor driver:</th><td>DRV8833</td></tr>
        <tr class='odd'><th>Minimum operating voltage:</th><td>2.7 V</td></tr>
        <tr class='even'><th>Reverse voltage protection?:</th><td>Y</td></tr>
        <tr class='odd'><th>Other PCB markings:</th><td>0J3762</td></tr>
        </table>
        <h2>Notes:</h2><dl class="specification_notes"><dt><a name="note1"></a>1<dd>Without included hardware.</dd></dt></dl>
        </div></body></html>
        HTML;

    private const RESOURCES_PAGE = <<<'HTML'
        <html><body><div class="tab_page"><h2>File downloads</h2>
        <ul class='resources attached_files'>
        <li class='resource clickable'><h3 class='content_title'><a href="/file/0J1615/drv8833-dual-motor-driver-carrier-dimensions.pdf">Dimension diagram of the DRV8833 Dual Motor Driver Carrier</a> (208k pdf)</h3></li>
        <li class='resource clickable'><h3 class='content_title'><a href="/file/0J1616/drv8833-dual-motor-driver-carrier-step.step">3D model of the DRV8833 Dual Motor Driver Carrier</a> (3MB step)</h3></li>
        </ul>
        <h2>Recommended links</h2>
        <ul class='resources attached_recommended_links'>
        <li class='resource clickable'><h3 class='content_title'><a href="https://www.ti.com/lit/ds/symlink/drv8833.pdf">Texas Instruments DRV8833 motor driver datasheet</a></h3></li>
        <li class='resource clickable'><h3 class='content_title'><a href="https://www.ti.com/product/DRV8833">Texas Instruments DRV8833 product page</a></h3></li>
        </ul>
        </div></body></html>
        HTML;

    protected function setUp(): void
    {
        $this->httpClient = new MockHttpClient();
        $this->settings = SettingsTestHelper::createSettingsDummy(PololuSettings::class);
        $this->settings->enabled = true;
        //Do not slow down the tests
        $this->settings->requestDelay = 0;
        $this->provider = new PololuProvider($this->httpClient, $this->settings, new ArrayAdapter());
    }

    private function productPage(string $tabs = self::TABS): string
    {
        return str_replace('%TABS%', $tabs, self::PRODUCT_PAGE);
    }

    public function testGetProviderInfo(): void
    {
        $info = $this->provider->getProviderInfo();

        $this->assertSame('pololu', $info->key);
        $this->assertSame('Pololu', $info->name);
        $this->assertSame(PololuSettings::class, $info->settingsClass);
        $this->assertContains(ProviderCapabilities::BASIC, $info->capabilities);
        $this->assertContains(ProviderCapabilities::PICTURE, $info->capabilities);
        $this->assertContains(ProviderCapabilities::DATASHEET, $info->capabilities);
        $this->assertContains(ProviderCapabilities::PRICE, $info->capabilities);
        $this->assertContains(ProviderCapabilities::PARAMETERS, $info->capabilities);
    }

    public function testIsActive(): void
    {
        $this->assertTrue($this->provider->isActive());

        $this->settings->enabled = false;
        $this->assertFalse($this->provider->isActive());
    }

    public function testSearchByKeyword(): void
    {
        $requestedUrl = null;
        $this->httpClient->setResponseFactory(function (string $method, string $url) use (&$requestedUrl) {
            $requestedUrl = $url;
            return new MockResponse(self::SEARCH_PAGE);
        });

        $results = $this->provider->searchByKeyword('DRV8833');

        $this->assertStringStartsWith('https://www.pololu.com/search?', $requestedUrl);
        $this->assertStringContainsString('query=DRV8833', $requestedUrl);
        $this->assertStringContainsString('search_type]=products', $requestedUrl);

        //The category result must be skipped
        $this->assertCount(2, $results);
        $this->assertContainsOnlyInstancesOf(SearchResultDTO::class, $results);

        $this->assertSame('pololu', $results[0]->provider_key);
        $this->assertSame('2130', $results[0]->provider_id);
        $this->assertSame('DRV8833 Dual Motor Driver Carrier', $results[0]->name);
        $this->assertSame('https://www.pololu.com/product/2130', $results[0]->provider_url);
        $this->assertSame('https://a.pololu-files.com/picture/0J3864.100.jpg?abc', $results[0]->preview_image_url);
        $this->assertStringContainsString('carrier for the DRV8833', $results[0]->description);

        $this->assertSame('2196', $results[1]->provider_id);
        $this->assertSame('Tamiya 70203 Low-Current Motor Gearbox (3-Speed)', $results[1]->name);
    }

    public function testSearchByKeywordWithoutResults(): void
    {
        $this->httpClient->setResponseFactory([new MockResponse('<html><body><dl id="search"></dl></body></html>')]);

        $this->assertSame([], $this->provider->searchByKeyword('doesnotexist'));
    }

    public function testGetDetails(): void
    {
        $requestedUrls = [];
        $responses = [$this->productPage(), self::SPECS_PAGE, self::RESOURCES_PAGE];
        $this->httpClient->setResponseFactory(function (string $method, string $url) use (&$requestedUrls, &$responses) {
            $requestedUrls[] = $url;
            return new MockResponse(array_shift($responses));
        });

        $details = $this->provider->getDetails('2130');

        $this->assertSame([
            'https://www.pololu.com/product/2130',
            'https://www.pololu.com/product/2130/specs',
            'https://www.pololu.com/product/2130/resources',
        ], $requestedUrls);

        $this->assertInstanceOf(PartDetailDTO::class, $details);
        $this->assertSame('pololu', $details->provider_key);
        $this->assertSame('2130', $details->provider_id);
        $this->assertSame('DRV8833 Dual Motor Driver Carrier', $details->name);
        $this->assertStringStartsWith('This tiny breakout board', $details->description);
        $this->assertSame('Electronics -> Brushed DC Motor Drivers', $details->category);
        $this->assertSame('Pololu', $details->manufacturer);
        $this->assertSame('2130', $details->mpn);
        $this->assertSame(ManufacturingStatus::ACTIVE, $details->manufacturing_status);
        $this->assertSame('https://www.pololu.com/product/2130', $details->provider_url);
        $this->assertSame('https://www.pololu.com/product/2130', $details->manufacturer_product_url);
        $this->assertSame(1.0, $details->mass);

        //Images
        $this->assertSame('https://a.pololu-files.com/picture/0J3864.1200.jpg?abc', $details->preview_image_url);
        $this->assertCount(2, $details->images);
        $this->assertSame('https://a.pololu-files.com/picture/0J3864.1200.jpg?abc', $details->images[0]->url);
        $this->assertSame('DRV8833 dual motor driver carrier.', $details->images[0]->name);
        $this->assertSame('https://a.pololu-files.com/picture/0J3872.1200.png?def', $details->images[1]->url);

        //Notes: pictures are removed and links are made absolute
        $this->assertStringContainsString('<h2>Overview</h2>', $details->notes);
        $this->assertStringContainsString('href="https://www.pololu.com/product/2135"', $details->notes);
        $this->assertStringNotContainsString('picture_with_caption', $details->notes);
        $this->assertStringNotContainsString('onclick', $details->notes);

        //Prices and stock
        $this->assertCount(1, $details->vendor_infos);
        $purchaseInfo = $details->vendor_infos[0];
        $this->assertSame('Pololu', $purchaseInfo->distributor_name);
        $this->assertSame('2130', $purchaseInfo->order_number);
        $this->assertSame('https://www.pololu.com/product/2130', $purchaseInfo->product_url);
        $this->assertSame(148.0, $purchaseInfo->available_amount);
        $this->assertFalse($purchaseInfo->prices_include_vat);
        $this->assertCount(3, $purchaseInfo->prices);
        $this->assertSame(1.0, $purchaseInfo->prices[0]->minimum_discount_amount);
        $this->assertSame('10.95', $purchaseInfo->prices[0]->price);
        $this->assertSame('USD', $purchaseInfo->prices[0]->currency_iso_code);
        $this->assertSame(100.0, $purchaseInfo->prices[2]->minimum_discount_amount);
        $this->assertSame('8.53', $purchaseInfo->prices[2]->price);

        //Files: all file downloads, but only the recommended links which point to a file
        $this->assertCount(3, $details->datasheets);
        $this->assertSame('https://www.pololu.com/file/0J1615/drv8833-dual-motor-driver-carrier-dimensions.pdf', $details->datasheets[0]->url);
        $this->assertSame('Dimension diagram of the DRV8833 Dual Motor Driver Carrier', $details->datasheets[0]->name);
        $this->assertSame('https://www.pololu.com/file/0J1616/drv8833-dual-motor-driver-carrier-step.step', $details->datasheets[1]->url);
        $this->assertSame('https://www.ti.com/lit/ds/symlink/drv8833.pdf', $details->datasheets[2]->url);

        //Parameters
        $this->assertCount(6, $details->parameters);
        $parameters = [];
        foreach ($details->parameters as $parameter) {
            $parameters[$parameter->name] = $parameter;
        }

        $this->assertSame('0.5″ × 0.8″', $parameters['Size']->value_text);
        $this->assertSame('Dimensions', $parameters['Size']->group);
        $this->assertSame(1.0, $parameters['Weight']->value_typ);
        $this->assertSame('g', $parameters['Weight']->unit);
        $this->assertSame('DRV8833', $parameters['Motor driver']->value_text);
        $this->assertSame('General specifications', $parameters['Motor driver']->group);
        $this->assertSame(2.7, $parameters['Minimum operating voltage']->value_typ);
        $this->assertSame('V', $parameters['Minimum operating voltage']->unit);
        $this->assertSame('Yes', $parameters['Reverse voltage protection?']->value_text);
        //A marking must not be split into a number and a unit
        $this->assertSame('0J3762', $parameters['Other PCB markings']->value_text);
        $this->assertNull($parameters['Other PCB markings']->value_typ);
    }

    public function testGetDetailsSkipsEmptyTabs(): void
    {
        $requests = 0;
        $this->httpClient->setResponseFactory(function () use (&$requests) {
            $requests++;
            return new MockResponse($this->productPage(self::EMPTY_TABS));
        });

        $details = $this->provider->getDetails('2130');

        $this->assertSame(1, $requests);
        $this->assertSame([], $details->parameters);
        $this->assertSame([], $details->datasheets);
        $this->assertNull($details->mass);
    }

    public function testGetDetailsOfOtherBrand(): void
    {
        $page = str_replace('<a href="/brands/pololu">Pololu</a>', '<a href="/brands/tamiya">Tamiya</a>', $this->productPage(self::EMPTY_TABS));
        $this->httpClient->setResponseFactory([new MockResponse($page)]);

        $details = $this->provider->getDetails('2196');

        $this->assertSame('Tamiya', $details->manufacturer);
        //The Pololu item number is no manufacturer part number for products of other brands
        $this->assertNull($details->mpn);
        $this->assertNull($details->manufacturer_product_url);
        $this->assertSame('2196', $details->vendor_infos[0]->order_number);
    }

    public function testGetDetailsWithInvalidId(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->provider->getDetails('abc');
    }

    public function testNoFurtherRequestsAfterBeingBlocked(): void
    {
        $requests = 0;
        $this->httpClient->setResponseFactory(function () use (&$requests) {
            $requests++;
            return new MockResponse('Too many requests', ['http_code' => 429]);
        });

        try {
            $this->provider->searchByKeyword('DRV8833');
            $this->fail('An exception should have been thrown');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('429', $exception->getMessage());
        }

        try {
            $this->provider->getDetails('2130');
            $this->fail('An exception should have been thrown');
        } catch (\RuntimeException) {
            //The second lookup must not have sent a request
            $this->assertSame(1, $requests);
        }
    }

    public function testGetHandledDomains(): void
    {
        $this->assertSame(['pololu.com'], $this->provider->getHandledDomains());
    }

    public function testGetIDFromURL(): void
    {
        $this->assertSame('2130', $this->provider->getIDFromURL('https://www.pololu.com/product/2130'));
        $this->assertSame('2130', $this->provider->getIDFromURL('https://www.pololu.com/product/2130/resources'));
        $this->assertSame('2130', $this->provider->getIDFromURL('https://www.pololu.com/product/2130?foo=bar'));
        $this->assertNull($this->provider->getIDFromURL('https://www.pololu.com/category/11/brushed-dc-motor-drivers'));
    }
}
