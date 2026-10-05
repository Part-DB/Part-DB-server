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


namespace App\Tests\Services\InfoProviderSystem;

use App\Entity\Parts\InfoProviderReference;
use App\Entity\Parts\Part;
use App\Entity\Parts\Supplier;
use App\Entity\PriceInformations\Orderdetail;
use App\Services\InfoProviderSystem\DTOs\PartDetailDTO;
use App\Services\InfoProviderSystem\DTOs\ProviderInfoDTO;
use App\Services\InfoProviderSystem\DTOs\PurchaseInfoDTO;
use App\Services\InfoProviderSystem\DTOs\SearchResultDTO;
use App\Services\InfoProviderSystem\ProviderOnViewMatcher;
use App\Services\InfoProviderSystem\ProviderRegistry;
use App\Services\InfoProviderSystem\Providers\InfoProviderInterface;
use App\Services\InfoProviderSystem\Providers\URLHandlerInfoProviderInterface;
use App\Settings\InfoProviderSystem\CanopySettings;
use App\Settings\InfoProviderSystem\InfoProviderGeneralSettings;
use App\Tests\SettingsTestHelper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ProviderOnViewMatcherTest extends TestCase
{
    private InfoProviderGeneralSettings $settings;
    private CanopySettings $canopySettings;

    /** @var int The number of times a provider was asked for search results or details */
    private int $providerRequests = 0;

    protected function setUp(): void
    {
        $this->settings = SettingsTestHelper::createSettingsDummy(InfoProviderGeneralSettings::class);
        $this->canopySettings = SettingsTestHelper::createSettingsDummy(CanopySettings::class);
        $this->canopySettings->domain = 'US';
        $this->providerRequests = 0;
    }

    /**
     * A store provider, which recognizes the URLs https://www.<key>.com/product/<id>
     */
    private function getStoreProvider(string $key, string $name, bool $active = true): InfoProviderInterface
    {
        $mock = $this->createMockForIntersectionOfInterfaces([InfoProviderInterface::class, URLHandlerInfoProviderInterface::class]);
        $mock->method('getProviderInfo')->willReturn(new ProviderInfoDTO(key: $key, name: $name));
        $mock->method('isActive')->willReturn($active);
        $mock->method('getHandledDomains')->willReturn(["$key.com"]);
        $mock->method('getIDFromURL')->willReturnCallback(
            static fn(string $url): ?string => preg_match('#/product/(\w+)#', $url, $matches) === 1 ? $matches[1] : null
        );
        $this->countRequests($mock);

        return $mock;
    }

    /**
     * A provider which does not handle URLs (like the API of a distributor)
     */
    private function getPlainProvider(string $key, string $name): InfoProviderInterface
    {
        $mock = $this->createMock(InfoProviderInterface::class);
        $mock->method('getProviderInfo')->willReturn(new ProviderInfoDTO(key: $key, name: $name));
        $mock->method('isActive')->willReturn(true);
        $this->countRequests($mock);

        return $mock;
    }

    private function countRequests(InfoProviderInterface $mock): void
    {
        $mock->method('searchByKeyword')->willReturnCallback(function (): array {
            $this->providerRequests++;
            return [];
        });
        $mock->method('getDetails')->willReturnCallback(function (): never {
            $this->providerRequests++;
            throw new \RuntimeException('The matcher must not request details');
        });
    }

    /**
     * @param  string[]  $enabled The keys of the providers "fetch on view" is enabled for
     */
    private function getMatcher(array $enabled, ?array $providers = null): ProviderOnViewMatcher
    {
        $this->settings->fetchOnViewProviders = $enabled;

        return new ProviderOnViewMatcher($this->settings, $this->canopySettings, new ProviderRegistry($providers ?? [
            $this->getStoreProvider('bambulab', 'Bambu Lab'),
            $this->getStoreProvider('sparkfun', 'SparkFun'),
            $this->getStoreProvider('adafruit', 'Adafruit'),
            $this->getStoreProvider('pololu', 'Pololu', active: false),
            $this->getPlainProvider('canopy', 'Amazon (Canopy)'),
            $this->getPlainProvider('distributor', 'Distributor'),
        ]));
    }

    private function getPart(?string $supplier, string $supplier_part_nr = '', string $url = ''): Part
    {
        $part = new Part();
        $part->setName('Test part');
        $this->addOrderdetail($part, $supplier, $supplier_part_nr, $url);

        return $part;
    }

    private function addOrderdetail(Part $part, ?string $supplier, string $supplier_part_nr = '', string $url = ''): Orderdetail
    {
        $orderdetail = new Orderdetail();
        if ($supplier !== null) {
            $orderdetail->setSupplier((new Supplier())->setName($supplier));
        }
        $orderdetail->setSupplierpartnr($supplier_part_nr);
        $orderdetail->setSupplierProductUrl($url);
        $part->addOrderdetail($orderdetail);

        return $orderdetail;
    }

    private function searchResult(string $provider_id, ?string $mpn = null, string $provider_key = 'sparkfun'): SearchResultDTO
    {
        return new SearchResultDTO(provider_key: $provider_key, provider_id: $provider_id, name: 'Product '.$provider_id,
            description: '', mpn: $mpn);
    }

    public function testNothingEnabledByDefault(): void
    {
        $matcher = $this->getMatcher([]);

        $this->assertSame([], $matcher->getEnabledProviders());
        $this->assertSame([], $matcher->findMatches($this->getPart('Adafruit', '4062', 'https://www.adafruit.com/product/4062')));
    }

    public function testEnabledProvidersKeepOrderAndSkipInactiveAndUnknown(): void
    {
        $matcher = $this->getMatcher(['sparkfun', 'pololu', 'does_not_exist', 'bambulab']);

        $this->assertSame(['sparkfun', 'bambulab'], array_keys($matcher->getEnabledProviders()));
    }

    public function testCanopyIsEnabledByItsOwnSetting(): void
    {
        $matcher = $this->getMatcher(['adafruit']);
        $this->assertSame(['adafruit'], array_keys($matcher->getEnabledProviders()));

        $this->canopySettings->fetchOnView = true;
        $this->assertSame(['adafruit', 'canopy'], array_keys($matcher->getEnabledProviders()));
    }

    public function testDailyLimit(): void
    {
        $matcher = $this->getMatcher(['adafruit']);
        $this->settings->fetchOnViewDailyLimit = 7;
        $this->canopySettings->fetchOnViewDailyLimit = 3;

        $this->assertSame(7, $matcher->getDailyLimit('adafruit'));
        //Canopy is billed per request, so it keeps the limit of its own settings
        $this->assertSame(3, $matcher->getDailyLimit('canopy'));
    }

    public function testMatchByProductURL(): void
    {
        $matcher = $this->getMatcher(['adafruit', 'sparkfun']);
        //The supplier does not matter, if the URL is a product page of the provider
        $part = $this->getPart('Some reseller', 'XYZ', 'https://www.adafruit.com/product/4062');

        $matches = $matcher->findMatches($part);

        $this->assertCount(1, $matches);
        $this->assertSame('adafruit', $matches[0]->getProviderKey());
        $this->assertSame('Adafruit', $matches[0]->getProviderName());
        $this->assertSame('4062', $matches[0]->providerId);
        $this->assertNull($matches[0]->supplierPartNr);
        $this->assertSame($part->getOrderdetails()->first(), $matches[0]->orderdetail);
    }

    public function testURLOfOtherDomainOrOtherPageDoesNotMatch(): void
    {
        $matcher = $this->getMatcher(['adafruit']);

        //Looks like a product URL, but is another website
        $this->assertSame([], $matcher->findMatches($this->getPart(null, '', 'https://www.notadafruit.com/product/4062')));
        $this->assertSame([], $matcher->findMatches($this->getPart(null, '', 'https://adafruit.com.example.org/product/4062')));
        //The right website, but no product page
        $this->assertSame([], $matcher->findMatches($this->getPart(null, '', 'https://www.adafruit.com/category/17')));
        //Not an URL at all
        $this->assertSame([], $matcher->findMatches($this->getPart(null, '', 'adafruit.com/product/4062')));
    }

    public function testMatchBySupplierNameAndPartNumber(): void
    {
        $matcher = $this->getMatcher(['bambulab', 'sparkfun']);

        $matches = $matcher->findMatches($this->getPart('Bambu Lab', ' 32101 '));
        $this->assertCount(1, $matches);
        $this->assertSame('bambulab', $matches[0]->getProviderKey());
        $this->assertNull($matches[0]->providerId);
        $this->assertSame('32101', $matches[0]->supplierPartNr);

        //The name is compared without case, spaces and punctuation
        $this->assertSame('bambulab', $matcher->findMatches($this->getPart('BAMBULAB', '32101'))[0]->getProviderKey());
        $this->assertSame('sparkfun', $matcher->findMatches($this->getPart('Spark-Fun', 'DEV-13975'))[0]->getProviderKey());
    }

    public function testSupplierNameAloneIsNotEnough(): void
    {
        $matcher = $this->getMatcher(['bambulab', 'sparkfun']);

        //No supplier part number to look up
        $this->assertSame([], $matcher->findMatches($this->getPart('Bambu Lab', '')));
        //Not the name of the provider
        $this->assertSame([], $matcher->findMatches($this->getPart('Bambu Lab Europe', '32101')));
        //No supplier
        $this->assertSame([], $matcher->findMatches($this->getPart(null, '32101')));
        //The provider of this supplier is not enabled
        $this->assertSame([], $matcher->findMatches($this->getPart('Adafruit', '4062')));
        //No orderdetail at all
        $this->assertSame([], $matcher->findMatches((new Part())->setName('Test')));
    }

    public function testURLMatchesComeBeforeSupplierMatches(): void
    {
        $matcher = $this->getMatcher(['bambulab', 'adafruit']);

        $part = $this->getPart('Bambu Lab', '32101');
        $this->addOrderdetail($part, 'Somebody', '', 'https://www.adafruit.com/product/4062');

        $matches = $matcher->findMatches($part);
        $this->assertCount(2, $matches);
        $this->assertSame('adafruit', $matches[0]->getProviderKey());
        $this->assertSame('4062', $matches[0]->providerId);
        $this->assertSame('bambulab', $matches[1]->getProviderKey());
    }

    public function testOrderdetailWithURLAndSupplierGivesOnlyTheURLMatch(): void
    {
        $matcher = $this->getMatcher(['adafruit']);

        $matches = $matcher->findMatches($this->getPart('Adafruit', 'ADA4062', 'https://www.adafruit.com/product/4062'));
        $this->assertCount(1, $matches);
        $this->assertSame('4062', $matches[0]->providerId);
    }

    public function testPartWithProviderReferenceIsNotTouched(): void
    {
        $matcher = $this->getMatcher(['adafruit']);

        $part = $this->getPart('Adafruit', '4062', 'https://www.adafruit.com/product/4062');
        $part->setProviderReference(InfoProviderReference::providerReference('distributor', 'ABC'));

        $this->assertSame([], $matcher->findMatches($part));
    }

    public function testCanopyMatchesAmazonURLsOfTheConfiguredMarketplace(): void
    {
        $this->canopySettings->fetchOnView = true;
        $matcher = $this->getMatcher([]);

        $matches = $matcher->findMatches($this->getPart('Amazon', '', 'https://www.amazon.com/Some-Product/dp/B00EXAMPLE?th=1'));
        $this->assertCount(1, $matches);
        $this->assertSame('canopy', $matches[0]->getProviderKey());
        $this->assertSame('B00EXAMPLE', $matches[0]->providerId);

        $this->assertSame('B00EXAMPLE', $matcher->findMatches($this->getPart(null, '', 'https://smile.amazon.com/gp/product/B00EXAMPLE'))[0]->providerId);

        //Another marketplace, no product page, and a supplier named Amazon without an URL
        $this->assertSame([], $matcher->findMatches($this->getPart('Amazon', '', 'https://www.amazon.de/dp/B00EXAMPLE')));
        $this->assertSame([], $matcher->findMatches($this->getPart('Amazon', '', 'https://www.amazon.com/gp/help/customer')));
        $this->assertSame([], $matcher->findMatches($this->getPart('Amazon', 'B00EXAMPLE')));
    }

    public function testCanopyIsNotUsedIfDisabled(): void
    {
        $matcher = $this->getMatcher(['adafruit']);

        $this->assertSame([], $matcher->findMatches($this->getPart('Amazon', '', 'https://www.amazon.com/dp/B00EXAMPLE')));
    }

    public function testProviderWithoutURLHandlingMatchesBySupplierOnly(): void
    {
        $matcher = $this->getMatcher(['distributor']);

        $this->assertSame([], $matcher->findMatches($this->getPart(null, '', 'https://www.distributor.com/product/1')));
        $this->assertCount(1, $matcher->findMatches($this->getPart('Distributor', 'ABC-1')));
    }

    public function testMatchingDoesNotContactTheProviders(): void
    {
        $matcher = $this->getMatcher(['bambulab', 'sparkfun', 'adafruit', 'distributor']);

        $part = $this->getPart('Bambu Lab', '32101');
        $this->addOrderdetail($part, 'Adafruit', '4062', 'https://www.adafruit.com/product/4062');
        $this->addOrderdetail($part, 'Distributor', 'ABC');

        $this->assertCount(3, $matcher->findMatches($part));
        $this->assertSame(0, $this->providerRequests);
    }

    public static function numbersProvider(): \Generator
    {
        yield 'identical' => [true, '32101', '32101'];
        yield 'case' => [true, 'dev-13975', 'DEV-13975'];
        yield 'whitespace' => [true, ' KM100 ', 'KM100'];
        yield 'leading zeros' => [true, '08809', '8809'];
        yield 'hash' => [true, '#4062', '4062'];
        yield 'vendor prefix from the name' => [true, 'ADA4062', '4062', ['adafruit', 'Adafruit']];
        yield 'vendor prefix on the other side' => [true, '4062', 'ada4062', ['adafruit', 'Adafruit']];
        yield 'prefix with separator' => [true, '13975', 'DEV-13975'];
        yield 'prefix with separator and zeros' => [true, '8809', 'PRT-08809'];

        yield 'empty' => [false, '', ''];
        yield 'one empty' => [false, '4062', ''];
        yield 'different numbers' => [false, '4062', '4063'];
        yield 'number contained' => [false, '4062', '14062'];
        yield 'different prefixes' => [false, 'DEV-13975', 'PRT-13975'];
        yield 'prefix is not the vendor' => [false, 'KM100', '100', ['thorlabs', 'Thorlabs']];
        yield 'prefix without vendor names' => [false, 'ADA4062', '4062'];
        yield 'suffix' => [false, '4062-A', '4062'];
        yield 'similar' => [false, 'KM100', 'KM100T'];
    }

    #[DataProvider('numbersProvider')]
    public function testNumbersEqual(bool $expected, string $a, string $b, array $vendor_names = []): void
    {
        $this->assertSame($expected, ProviderOnViewMatcher::numbersEqual($a, $b, $vendor_names));
        $this->assertSame($expected, ProviderOnViewMatcher::numbersEqual($b, $a, $vendor_names));
    }

    public function testPickExactResultByProviderIdMpnOrOrderNumber(): void
    {
        $matcher = $this->getMatcher(['sparkfun']);
        $provider = $matcher->getEnabledProviders()['sparkfun'];

        //By provider ID, with the vendor prefix ignored. The fuzzy neighbours are not taken.
        $results = [$this->searchResult('DEV-13976'), $this->searchResult('DEV-13975'), $this->searchResult('DEV-139750')];
        $this->assertSame($results[1], $matcher->pickExactResult($provider, '13975', $results));
        $this->assertSame($results[1], $matcher->pickExactResult($provider, 'dev-13975', $results));

        //By manufacturer part number
        $results = [$this->searchResult('some-slug/1', '32100'), $this->searchResult('other-slug/2', '32101')];
        $this->assertSame($results[1], $matcher->pickExactResult($provider, '32101', $results));

        //By the order number of the store
        $detail = new PartDetailDTO(provider_key: 'sparkfun', provider_id: 'slug', name: 'Product', description: '',
            vendor_infos: [new PurchaseInfoDTO('SparkFun', 'SKU-777', [])]);
        $this->assertSame($detail, $matcher->pickExactResult($provider, 'SKU-777', [$this->searchResult('other'), $detail]));
    }

    public function testPickExactResultNeverGuesses(): void
    {
        $matcher = $this->getMatcher(['sparkfun']);
        $provider = $matcher->getEnabledProviders()['sparkfun'];

        //No results at all
        $this->assertNull($matcher->pickExactResult($provider, '13975', []));
        //A single result is not taken just because it is the only one
        $this->assertNull($matcher->pickExactResult($provider, '13975', [$this->searchResult('DEV-13976', 'DEV-13976')]));
        //Similar numbers
        $this->assertNull($matcher->pickExactResult($provider, '1397', [$this->searchResult('DEV-13975'), $this->searchResult('DEV-01398')]));
        //The number only appears in the name
        $this->assertNull($matcher->pickExactResult($provider, '13975', [
            new SearchResultDTO(provider_key: 'sparkfun', provider_id: 'DEV-1', name: 'Replacement for 13975', description: '13975'),
        ]));
        //A result of another provider
        $this->assertNull($matcher->pickExactResult($provider, '13975', [$this->searchResult('13975', provider_key: 'adafruit')]));
    }

    public function testPickExactResultWithMultipleExactResults(): void
    {
        $matcher = $this->getMatcher(['bambulab']);
        $provider = $matcher->getEnabledProviders()['bambulab'];

        //The packaging variants of one article share its number: the first one (the best match of the provider) is taken
        $results = [
            $this->searchResult('petg-translucent/1', '32101', 'bambulab'),
            $this->searchResult('petg-translucent/2', '32101', 'bambulab'),
            $this->searchResult('petg-translucent/3', '32102', 'bambulab'),
        ];
        $this->assertSame($results[0], $matcher->pickExactResult($provider, '32101', $results));

        //The same result listed twice is still one product
        $results = [$this->searchResult('4062', null, 'bambulab'), $this->searchResult('4062', null, 'bambulab')];
        $this->assertSame($results[0], $matcher->pickExactResult($provider, '4062', $results));

        //Different products claiming the number (one by its ID, one by its part number) are ambiguous
        $results = [$this->searchResult('32101', 'ABC', 'bambulab'), $this->searchResult('other', '32101', 'bambulab')];
        $this->assertNull($matcher->pickExactResult($provider, '32101', $results));
        $results = [$this->searchResult('32101', null, 'bambulab'), $this->searchResult('BBL-32101', null, 'bambulab')];
        $this->assertNull($matcher->pickExactResult($provider, '32101', $results));
    }

    public function testEnvVarMapper(): void
    {
        $this->assertSame(['bambulab', 'sparkfun', 'adafruit'],
            InfoProviderGeneralSettings::mapFetchOnViewProvidersEnv(' bambulab, SparkFun ,,adafruit,bambulab'));
        $this->assertSame([], InfoProviderGeneralSettings::mapFetchOnViewProvidersEnv(''));
    }
}
