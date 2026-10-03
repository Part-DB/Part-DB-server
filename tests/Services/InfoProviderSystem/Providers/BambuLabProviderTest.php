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
use App\Services\InfoProviderSystem\DTOs\ProviderInfoDTO;
use App\Services\InfoProviderSystem\DTOs\SearchResultDTO;
use App\Services\InfoProviderSystem\Providers\BambuLabProvider;
use App\Services\InfoProviderSystem\Providers\InfoProviderInterface;
use App\Services\InfoProviderSystem\Providers\ProviderCapabilities;
use App\Settings\InfoProviderSystem\BambuLabSettings;
use App\Settings\InfoProviderSystem\BambuLabStoreRegion;
use App\Tests\SettingsTestHelper;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class BambuLabProviderTest extends TestCase
{
    private BambuLabSettings $settings;
    private BambuLabProvider $provider;
    private MockHttpClient $httpClient;

    protected function setUp(): void
    {
        $this->httpClient = new MockHttpClient();
        $this->settings = SettingsTestHelper::createSettingsDummy(BambuLabSettings::class);
        $this->settings->enabled = true;
        $this->settings->region = BambuLabStoreRegion::US;
        $this->provider = new BambuLabProvider($this->httpClient, new ArrayAdapter(), $this->settings);
    }

    private function apiResponse(array $data): MockResponse
    {
        return new MockResponse(json_encode(['code' => 1, 'message' => 'success', 'data' => $data], JSON_THROW_ON_ERROR), [
            'response_headers' => ['content-type' => 'application/json'],
        ]);
    }

    private function searchRecord(?string $highlightedSku = null): array
    {
        return [
            'id' => '7740579479688',
            'seoCode' => 'petg-translucent',
            'name' => 'PETG Translucent',
            'lowerPrice' => 11.193,
            'currency' => 'USD',
            'mediaFiles' => ['https://store.example.com/search.png'],
            'highlightProductSkuId' => $highlightedSku,
        ];
    }

    private function sku(string $id, float $price, string $color, string $type, ?float $discountPrice = null): array
    {
        return [
            'id' => $id,
            'price' => $price,
            'discountPrice' => $discountPrice,
            'mediaFile' => ['resourceFileId' => '1', 'url' => 'https://store.example.com/sku-' . $id . '.jpg'],
            'productSkuPropertyList' => [
                ['propertyKey' => 'Color', 'propertyValue' => $color, 'position' => 1],
                ['propertyKey' => 'Type', 'propertyValue' => $type, 'position' => 2],
                ['propertyKey' => 'Size', 'propertyValue' => '1 kg', 'position' => 3],
            ],
            'isSoldOut' => false,
            'skuCode' => null,
        ];
    }

    private function product(): array
    {
        return [
            'id' => '7740579479688',
            'name' => 'PETG Translucent',
            'subTitle' => null,
            'seoCode' => 'petg-translucent',
            'seoMetaDescription' => 'Bambu PETG Translucent stands out with its exceptional printing characteristics.',
            'productFeaturesTitle' => 'Product Features',
            'productFeatures' => '<div style="text-align: left;"><ul class="list"><li style="box-sizing: border-box;">Translucent Appearance</li><li>Diameter: 1.75mm +/- 0.03mm</li></ul></div>',
            'productFeaturesModuleList' => [
                ['id' => '1', 'title' => '', 'content' => 'See also the <a style="color: red;" href="../../../products/petg-hf">PETG HF</a>.'],
            ],
            'mediaFiles' => [
                ['resourceFileId' => '2', 'url' => 'https://store.example.com/product-1.jpg'],
                ['resourceFileId' => '3', 'url' => 'https://store.example.com/product-2.jpg'],
            ],
            'productSkuList' => [
                $this->sku('41638807994504', 18.99, 'Light Blue (32600)', 'Filament with spool'),
                $this->sku('42235108098184', 18.99, 'Clear (32101)', 'Filament with spool'),
                $this->sku('42479468281992', 15.99, 'Clear (32101)', 'Refill', 13.99),
            ],
            'productPropertyList' => [
                [
                    'propertyKey' => 'Color',
                    'productPropertyValueList' => [['id' => '1', 'value' => 'Light Blue (32600)'], ['id' => '2', 'value' => 'Clear (32101)']],
                ],
                [
                    'propertyKey' => 'Type',
                    'productPropertyValueList' => [['id' => '3', 'value' => 'Refill'], ['id' => '4', 'value' => 'Filament with spool']],
                ],
            ],
            'productExtraInfoVO' => [
                'productFileList' => [
                    ['id' => '9', 'title' => 'Quick Start Guide', 'url' => 'https://store.example.com/guide.pdf', 'dataType' => 'pdf'],
                ],
            ],
            'isFilament' => true,
        ];
    }

    private function productPage(): MockResponse
    {
        return new MockResponse('<html><body><a href="/products/other">Other</a>'
            . '<a href="https://store.example.com/files/tds.pdf" download="">Filament TDS</a>'
            . '<a href="https://store.example.com/guide.pdf">Guide</a>'
            . '<table><thead><tr><td>Filament</td><td>0.2 mm</td></tr></thead><tbody><tr><td>PLA Basic</td><td>yes</td></tr></tbody></table>'
            . '<table><tbody><tr><td>Max. Printing Temp.</td><td>350 ℃</td><td>Length</td><td>49.2 mm</td></tr>'
            . '<tr><td>Packaging Size</td><td>60*60*30 mm</td></tr></tbody></table></body></html>');
    }

    public function testGetProviderInfo(): void
    {
        $info = $this->provider->getProviderInfo();

        $this->assertInstanceOf(ProviderInfoDTO::class, $info);
        $this->assertSame('bambulab', $info->key);
        $this->assertSame('Bambu Lab', $info->name);
        $this->assertSame(BambuLabSettings::class, $info->settingsClass);
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

    public function testSearchByKeywordReturnsProducts(): void
    {
        $requests = [];
        $this->httpClient->setResponseFactory(function (string $method, string $url, array $options) use (&$requests) {
            $requests[] = [$method, $url, $options];
            return $this->apiResponse(['page' => ['records' => [$this->searchRecord()], 'total' => '1']]);
        });

        $results = $this->provider->searchByKeyword(' PETG Translucent ');

        $this->assertCount(1, $requests);
        $this->assertSame('POST', $requests[0][0]);
        $this->assertSame('https://na-store-api.bambulab.com/mall-goods/product/globalSearchV2', $requests[0][1]);
        $this->assertSame('PETG Translucent', json_decode($requests[0][2]['body'], true)['content']);
        $this->assertContains('X-BBL-STORE-REGION: US', $requests[0][2]['headers']);
        $this->assertContains('Bbl-Locale: en-US', $requests[0][2]['headers']);

        $this->assertCount(1, $results);
        $this->assertInstanceOf(SearchResultDTO::class, $results[0]);
        $this->assertSame('bambulab', $results[0]->provider_key);
        $this->assertSame('petg-translucent', $results[0]->provider_id);
        $this->assertSame('PETG Translucent', $results[0]->name);
        $this->assertSame('Bambu Lab', $results[0]->manufacturer);
        $this->assertSame('https://store.example.com/search.png', $results[0]->preview_image_url);
        $this->assertSame('https://us.store.bambulab.com/products/petg-translucent', $results[0]->provider_url);
    }

    public function testSearchByCodeReturnsMatchingVariants(): void
    {
        $this->httpClient->setResponseFactory([
            $this->apiResponse(['page' => ['records' => [$this->searchRecord('42235108098184')]]]),
            $this->apiResponse($this->product()),
        ]);

        $results = $this->provider->searchByKeyword('32101');

        //Both variants with the code must be returned, but not the one with the other color
        $this->assertCount(2, $results);
        $this->assertSame('petg-translucent/42235108098184', $results[0]->provider_id);
        $this->assertSame('PETG Translucent - Clear (32101) / Filament with spool / 1 kg', $results[0]->name);
        $this->assertSame('32101', $results[0]->mpn);
        $this->assertSame('https://store.example.com/sku-42235108098184.jpg', $results[0]->preview_image_url);
        $this->assertSame('https://us.store.bambulab.com/products/petg-translucent?id=42235108098184', $results[0]->provider_url);
        $this->assertSame('petg-translucent/42479468281992', $results[1]->provider_id);
        $this->assertSame('PETG Translucent - Clear (32101) / Refill / 1 kg', $results[1]->name);
    }

    public function testSearchFallsBackToHighlightedVariant(): void
    {
        $this->httpClient->setResponseFactory([
            $this->apiResponse(['page' => ['records' => [$this->searchRecord('41638807994504')]]]),
            $this->apiResponse($this->product()),
        ]);

        $results = $this->provider->searchByKeyword('something else');

        $this->assertCount(1, $results);
        $this->assertSame('petg-translucent/41638807994504', $results[0]->provider_id);
    }

    public function testSearchByEmptyKeyword(): void
    {
        $this->assertSame([], $this->provider->searchByKeyword('  '));
        $this->assertSame(0, $this->httpClient->getRequestsCount());
    }

    public function testGetDetailsOfVariant(): void
    {
        $requests = [];
        $this->httpClient->setResponseFactory(function (string $method, string $url) use (&$requests) {
            $requests[] = $method . ' ' . $url;
            return count($requests) === 1 ? $this->apiResponse($this->product()) : $this->productPage();
        });

        $details = $this->provider->getDetails('petg-translucent/42479468281992');

        $this->assertSame([
            'GET https://na-store-api.bambulab.com/mall-goods/product/queryById?seoCode=petg-translucent',
            'GET https://us.store.bambulab.com/products/petg-translucent?id=42479468281992',
        ], $requests);

        $this->assertInstanceOf(PartDetailDTO::class, $details);
        $this->assertSame('petg-translucent/42479468281992', $details->provider_id);
        $this->assertSame('PETG Translucent - Clear (32101) / Refill / 1 kg', $details->name);
        $this->assertSame('Bambu PETG Translucent stands out with its exceptional printing characteristics.', $details->description);
        $this->assertSame('Bambu Lab', $details->manufacturer);
        $this->assertSame('32101', $details->mpn);
        $this->assertSame('Filament', $details->category);
        $this->assertSame('https://us.store.bambulab.com/products/petg-translucent?id=42479468281992', $details->provider_url);

        //The image of the variant must be the first one
        $this->assertSame('https://store.example.com/sku-42479468281992.jpg', $details->preview_image_url);
        $this->assertCount(3, $details->images);

        //The layout attributes of the store must be removed from the notes
        $this->assertStringContainsString('<li>Translucent Appearance</li>', $details->notes);
        $this->assertStringNotContainsString('style=', $details->notes);
        $this->assertStringContainsString('<a href="https://us.store.bambulab.com/products/petg-hf">PETG HF</a>', $details->notes);

        //Documents of the API and the ones linked on the product page, without duplicates
        $this->assertCount(2, $details->datasheets);
        $this->assertSame('https://store.example.com/guide.pdf', $details->datasheets[0]->url);
        $this->assertSame('Quick Start Guide', $details->datasheets[0]->name);
        $this->assertSame('https://store.example.com/files/tds.pdf', $details->datasheets[1]->url);
        $this->assertSame('Filament TDS', $details->datasheets[1]->name);

        $parameters = [];
        foreach ($details->parameters as $parameter) {
            $parameters[$parameter->name] = $parameter;
        }
        $this->assertSame('Clear (32101)', $parameters['Color']->value_text);
        $this->assertSame('Refill', $parameters['Type']->value_text);
        $this->assertSame(1.0, $parameters['Size']->value_typ);
        $this->assertSame('kg', $parameters['Size']->unit);
        $this->assertSame('1.75mm +/- 0.03mm', $parameters['Diameter']->value_text);
        //The specification table of the product page must be parsed, but not the comparison table
        $this->assertSame(49.2, $parameters['Length']->value_typ);
        $this->assertSame('mm', $parameters['Length']->unit);
        $this->assertSame('60*60*30 mm', $parameters['Packaging Size']->value_text);
        $this->assertArrayHasKey('Max. Printing Temp.', $parameters);
        $this->assertArrayNotHasKey('PLA Basic', $parameters);

        //The discounted price must be used
        $this->assertCount(1, $details->vendor_infos);
        $this->assertSame('Bambu Lab', $details->vendor_infos[0]->distributor_name);
        $this->assertSame('32101', $details->vendor_infos[0]->order_number);
        $this->assertFalse($details->vendor_infos[0]->prices_include_vat);
        $this->assertCount(1, $details->vendor_infos[0]->prices);
        $this->assertSame('13.99', $details->vendor_infos[0]->prices[0]->price);
        $this->assertSame('USD', $details->vendor_infos[0]->prices[0]->currency_iso_code);
    }

    public function testGetDetailsOfProduct(): void
    {
        $this->httpClient->setResponseFactory([
            $this->apiResponse($this->product()),
            //The documents are optional, so a failing product page must not break anything
            new MockResponse('', ['http_code' => 403]),
        ]);

        $details = $this->provider->getDetails('petg-translucent');

        $this->assertSame('petg-translucent', $details->provider_id);
        $this->assertSame('PETG Translucent', $details->name);
        $this->assertNull($details->mpn);
        $this->assertSame('https://us.store.bambulab.com/products/petg-translucent', $details->provider_url);
        $this->assertSame('https://store.example.com/product-1.jpg', $details->preview_image_url);
        $this->assertCount(1, $details->datasheets);

        $parameters = [];
        foreach ($details->parameters as $parameter) {
            $parameters[$parameter->name] = $parameter;
        }
        $this->assertSame('Light Blue (32600), Clear (32101)', $parameters['Color']->value_text);

        //The lowest price of all variants
        $this->assertSame('13.99', $details->vendor_infos[0]->prices[0]->price);
        $this->assertSame('petg-translucent', $details->vendor_infos[0]->order_number);
    }

    public function testGetDetailsUsesCache(): void
    {
        $this->httpClient->setResponseFactory([
            $this->apiResponse($this->product()),
            $this->productPage(),
            $this->productPage(),
            $this->apiResponse($this->product()),
            $this->productPage(),
        ]);

        $this->provider->getDetails('petg-translucent');
        $this->assertSame(2, $this->httpClient->getRequestsCount());

        //The product data must be taken from the cache now, only the product page is requested
        $this->provider->getDetails('petg-translucent/42479468281992');
        $this->assertSame(3, $this->httpClient->getRequestsCount());

        //Unless the cache should not be used
        $this->provider->getDetails('petg-translucent', [InfoProviderInterface::OPTION_NO_CACHE => true]);
        $this->assertSame(5, $this->httpClient->getRequestsCount());
    }

    public function testGetDetailsOfUnknownVariant(): void
    {
        $this->httpClient->setResponseFactory([$this->apiResponse($this->product())]);

        $this->expectException(\RuntimeException::class);
        $this->provider->getDetails('petg-translucent/123');
    }

    public function testGetDetailsWithInvalidId(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->provider->getDetails('petg translucent/abc');
    }

    public function testApiErrorThrowsException(): void
    {
        $this->httpClient->setResponseFactory([
            new MockResponse(json_encode(['code' => 10012, 'message' => 'No matching type for code en', 'data' => null])),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No matching type for code en');
        $this->provider->searchByKeyword('PETG');
    }

    public function testRegionDeterminesApiAndStore(): void
    {
        $this->settings->region = BambuLabStoreRegion::EU;

        $requests = [];
        $this->httpClient->setResponseFactory(function (string $method, string $url, array $options) use (&$requests) {
            $requests[] = [$url, $options];
            return count($requests) === 1 ? $this->apiResponse($this->product()) : $this->productPage();
        });

        $details = $this->provider->getDetails('petg-translucent/42479468281992');

        $this->assertStringStartsWith('https://eu-store-api.bambulab.com/', $requests[0][0]);
        $this->assertContains('X-BBL-STORE-REGION: EU', $requests[0][1]['headers']);
        $this->assertSame('https://eu.store.bambulab.com/products/petg-translucent?id=42479468281992', $details->provider_url);
        $this->assertSame('EUR', $details->vendor_infos[0]->prices[0]->currency_iso_code);
        $this->assertTrue($details->vendor_infos[0]->prices_include_vat);
    }

    public function testGetHandledDomains(): void
    {
        $this->assertSame(['bambulab.com'], $this->provider->getHandledDomains());
    }

    public function testGetIDFromURL(): void
    {
        $this->assertSame('petg-translucent', $this->provider->getIDFromURL('https://us.store.bambulab.com/products/petg-translucent'));
        $this->assertSame('petg-translucent/42235108098184', $this->provider->getIDFromURL('https://us.store.bambulab.com/products/petg-translucent?id=42235108098184'));
        $this->assertSame('pla-basic-filament', $this->provider->getIDFromURL('https://eu.store.bambulab.com/de/products/pla-basic-filament?utm_source=test'));
        $this->assertNull($this->provider->getIDFromURL('https://us.store.bambulab.com/collections/bambu-lab-3d-printer-filament'));
        $this->assertNull($this->provider->getIDFromURL('https://wiki.bambulab.com/products/petg-translucent'));
    }

    public function testRegionEnvVarMapping(): void
    {
        $this->assertSame(BambuLabStoreRegion::EU, BambuLabSettings::mapRegionEnvVar('eu'));
        $this->assertSame(BambuLabStoreRegion::GLOBAL, BambuLabSettings::mapRegionEnvVar('GLOBAL'));
        $this->assertSame(BambuLabStoreRegion::US, BambuLabSettings::mapRegionEnvVar('invalid'));
        $this->assertSame(BambuLabStoreRegion::US, BambuLabSettings::mapRegionEnvVar(null));
    }
}
