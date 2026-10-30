<?php

/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Tests\Services\InfoProviderSystem\Providers;

use App\Services\InfoProviderSystem\CreateFromUrlHelper;
use App\Services\InfoProviderSystem\Providers\GenericWebProvider;
use App\Services\InfoProviderSystem\SubmittedPageStorage;
use App\Settings\InfoProviderSystem\GenericWebProviderSettings;
use App\Tests\SettingsTestHelper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class GenericWebProviderTest extends TestCase
{
    public static function offers(): iterable
    {
        yield 'unpriced offer' => [['@type' => 'Offer'], null];
        yield 'priced offer' => [['@type' => 'Offer', 'price' => '12.50', 'priceCurrency' => 'EUR'], '12.50'];
        yield 'empty aggregate offer' => [['@type' => 'AggregateOffer'], null];
        yield 'nested offer' => [[
            '@type' => 'AggregateOffer',
            'offers' => ['@type' => 'Offer', 'price' => '12.50', 'priceCurrency' => 'EUR'],
        ], '12.50'];
    }

    #[DataProvider('offers')]
    public function testImportProductOffers(array $offer, ?string $expectedPrice): void
    {
        // A public IP avoids DNS lookup in NoPrivateNetworkHttpClient; HTTP is mocked.
        $url = 'https://8.8.8.8/product';
        $product = [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => 'Example part',
            'description' => 'Product details',
            'image' => 'https://shop.example/image.jpg',
            'sku' => 'PART-123',
            'offers' => $offer,
        ];
        $html = '<html><head><script type="application/ld+json">'.json_encode($product, JSON_THROW_ON_ERROR).'</script></head></html>';
        // Delegation is explicitly disabled, so its collaborators are not used.
        $helper = (new \ReflectionClass(CreateFromUrlHelper::class))->newInstanceWithoutConstructor();
        $provider = new GenericWebProvider(
            new MockHttpClient(new MockResponse($html)),
            SettingsTestHelper::createSettingsDummy(GenericWebProviderSettings::class),
            $helper,
            $this->createMock(SubmittedPageStorage::class),
        );

        $part = $provider->getDetails($url, [GenericWebProvider::OPTION_SKIP_DELEGATION => true]);

        self::assertSame('Example part', $part->name);
        self::assertSame('Product details', trim($part->notes));
        self::assertSame('https://shop.example/image.jpg', $part->preview_image_url);
        self::assertCount(1, $part->vendor_infos);
        $vendor = $part->vendor_infos[0];
        self::assertSame('PART-123', $vendor->order_number);
        self::assertSame($url, $vendor->product_url);
        if ($expectedPrice === null) {
            self::assertSame([], $vendor->prices);
        } else {
            self::assertCount(1, $vendor->prices);
            self::assertSame($expectedPrice, $vendor->prices[0]->price);
            self::assertSame('EUR', $vendor->prices[0]->currency_iso_code);
        }
    }

    public function testEmptyJsonLdScriptsAreIgnored(): void
    {
        $product = [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => 'Example part',
        ];
        $html = '<html><head><script type="application/ld+json"></script>'
            ."<script type='application/ld+json' id=\"empty\">\n  </script>"
            .'<script type="application/ld+json">'.json_encode($product, JSON_THROW_ON_ERROR).'</script></head></html>';
        $helper = (new \ReflectionClass(CreateFromUrlHelper::class))->newInstanceWithoutConstructor();
        $provider = new GenericWebProvider(
            new MockHttpClient(new MockResponse($html)),
            SettingsTestHelper::createSettingsDummy(GenericWebProviderSettings::class),
            $helper,
            $this->createMock(SubmittedPageStorage::class),
        );

        $part = $provider->getDetails('https://8.8.8.8/product', [GenericWebProvider::OPTION_SKIP_DELEGATION => true]);

        self::assertSame('Example part', $part->name);
    }

    public function testRawLineBreaksInJsonLdStringsAreFixed(): void
    {
        // Line breaks inside string literals are invalid JSON, but some shops output them anyway
        $json = "{\"@context\": \"https://schema.org\",\n\"@type\": \"Product\",\"name\": \"Example part\","
            ."\"review\": [{\"@type\": \"Review\",\"reviewBody\": \"First line<br />\nSecond \\\"line\\\"\r\n\tend\"}]}";
        $html = '<html><head><script type="application/ld+json">'.$json.'</script></head></html>';
        $helper = (new \ReflectionClass(CreateFromUrlHelper::class))->newInstanceWithoutConstructor();
        $provider = new GenericWebProvider(
            new MockHttpClient(new MockResponse($html)),
            SettingsTestHelper::createSettingsDummy(GenericWebProviderSettings::class),
            $helper,
            $this->createMock(SubmittedPageStorage::class),
        );

        $part = $provider->getDetails('https://8.8.8.8/product', [GenericWebProvider::OPTION_SKIP_DELEGATION => true]);

        self::assertSame('Example part', $part->name);
    }
}
