<?php

declare(strict_types=1);

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
namespace App\Tests\Services\Tools;

use App\Entity\Parameters\PartParameter;
use App\Entity\Parts\Category;
use App\Entity\Parts\Footprint;
use App\Entity\Parts\Manufacturer;
use App\Entity\Parts\Part;
use App\Services\Tools\ExternalPartLinkResolver;
use App\Settings\ExternalPartLinkSettings;
use App\Tests\SettingsTestHelper;
use PHPUnit\Framework\TestCase;

final class ExternalPartLinkResolverTest extends TestCase
{
    private function createResolver(array $links): ExternalPartLinkResolver
    {
        /** @var ExternalPartLinkSettings $settings */
        $settings = SettingsTestHelper::createSettingsDummy(ExternalPartLinkSettings::class);
        $settings->links = $links;

        return new ExternalPartLinkResolver($settings);
    }

    private function createPart(): Part
    {
        $part = new Part();
        $part->setName('BC847C');

        return $part;
    }

    private function templateLink(array $overrides = []): array
    {
        return array_merge([
            'name' => 'TrustedParts',
            'source_type' => 'template',
            'source' => 'https://www.trustedparts.com/de/search/{mpn}',
            'icon' => 'external-link',
            'open_new_tab' => true,
            'enabled' => true,
        ], $overrides);
    }

    public function testEveryPlaceholderIsResolvedCorrectly(): void
    {
        $part = $this->createPart();
        $part->setManufacturerProductNumber('BC847C');
        $part->setIpn('IPN-1');
        $manufacturer = new Manufacturer();
        $manufacturer->setName('OnSemi');
        $part->setManufacturer($manufacturer);

        $resolver = $this->createResolver([
            $this->templateLink(['source' => 'https://example.com/{manufacturer}/{mpn}/{ipn}/{name}']),
        ]);

        $links = $resolver->resolve($part);
        $this->assertCount(1, $links);
        $this->assertSame('https://example.com/OnSemi/BC847C/IPN-1/BC847C', $links[0]->url);
    }

    public function testCategoryAndFootprintPlaceholdersAreResolved(): void
    {
        $part = $this->createPart();
        $category = new Category();
        $category->setName('Transistors');
        $part->setCategory($category);
        $footprint = new Footprint();
        $footprint->setName('SOT-23');
        $part->setFootprint($footprint);

        $resolver = $this->createResolver([
            $this->templateLink(['source' => 'https://example.com/{category}/{footprint}']),
        ]);

        $links = $resolver->resolve($part);
        $this->assertCount(1, $links);
        $this->assertSame('https://example.com/Transistors/SOT-23', $links[0]->url);
    }

    public function testIdPlaceholderIsNotResolvedForAnUnsavedPart(): void
    {
        //A part which was never saved has no ID, so a link built from it would point at nothing
        $resolver = $this->createResolver([
            $this->templateLink(['source' => 'https://example.com/part/{id}']),
        ]);

        $this->assertSame([], $resolver->resolve($this->createPart()));
    }

    public function testSpecialCharactersArePercentEncoded(): void
    {
        $part = $this->createPart();
        $part->setName('Test');
        $part->setManufacturerProductNumber('BC847C / Test');

        $resolver = $this->createResolver([$this->templateLink()]);

        $links = $resolver->resolve($part);
        $this->assertCount(1, $links);
        $this->assertSame('https://www.trustedparts.com/de/search/BC847C%20%2F%20Test', $links[0]->url);
    }

    public function testMissingPlaceholderValueSuppressesTheLink(): void
    {
        $part = $this->createPart();
        //No MPN set (defaults to '')

        $resolver = $this->createResolver([$this->templateLink()]);

        $this->assertSame([], $resolver->resolve($part));
    }

    public function testDisallowedUrlSchemeIsRejected(): void
    {
        $part = $this->createPart();
        $part->setManufacturerProductNumber('BC847C');

        $resolver = $this->createResolver([
            $this->templateLink(['source' => 'javascript:alert(1)//{mpn}']),
        ]);

        $this->assertSame([], $resolver->resolve($part));
    }

    public function testHttpTemplateIsRejected(): void
    {
        $part = $this->createPart();
        $part->setManufacturerProductNumber('BC847C');

        $resolver = $this->createResolver([
            $this->templateLink(['source' => 'http://example.com/{mpn}']),
        ]);

        $this->assertSame([], $resolver->resolve($part));
    }

    public function testExistingValidParameterUrlIsResolved(): void
    {
        $part = $this->createPart();
        $parameter = new PartParameter();
        $parameter->setName('TrustedParts: Produkt-URL');
        $parameter->setValueText('https://www.trustedparts.com/de/search/BC847C');
        $part->addParameter($parameter);

        $resolver = $this->createResolver([
            $this->templateLink(['source_type' => 'parameter', 'source' => 'TrustedParts: Produkt-URL']),
        ]);

        $links = $resolver->resolve($part);
        $this->assertCount(1, $links);
        $this->assertSame('https://www.trustedparts.com/de/search/BC847C', $links[0]->url);
    }

    public function testMissingParameterSuppressesTheLink(): void
    {
        $part = $this->createPart();

        $resolver = $this->createResolver([
            $this->templateLink(['source_type' => 'parameter', 'source' => 'TrustedParts: Produkt-URL']),
        ]);

        $this->assertSame([], $resolver->resolve($part));
    }

    public function testEmptyParameterValueSuppressesTheLink(): void
    {
        $part = $this->createPart();
        $parameter = new PartParameter();
        $parameter->setName('TrustedParts: Produkt-URL');
        $parameter->setValueText('');
        $part->addParameter($parameter);

        $resolver = $this->createResolver([
            $this->templateLink(['source_type' => 'parameter', 'source' => 'TrustedParts: Produkt-URL']),
        ]);

        $this->assertSame([], $resolver->resolve($part));
    }

    public function testDuplicateParameterSuppressesTheLink(): void
    {
        $part = $this->createPart();
        $parameter1 = new PartParameter();
        $parameter1->setName('TrustedParts: Produkt-URL');
        $parameter1->setValueText('https://www.trustedparts.com/de/search/BC847C');
        $part->addParameter($parameter1);

        $parameter2 = new PartParameter();
        $parameter2->setName('TrustedParts: Produkt-URL');
        $parameter2->setValueText('https://www.trustedparts.com/de/search/other');
        $part->addParameter($parameter2);

        $resolver = $this->createResolver([
            $this->templateLink(['source_type' => 'parameter', 'source' => 'TrustedParts: Produkt-URL']),
        ]);

        $this->assertSame([], $resolver->resolve($part));
    }

    public function testInvalidParameterUrlIsRejected(): void
    {
        $part = $this->createPart();
        $parameter = new PartParameter();
        $parameter->setName('TrustedParts: Produkt-URL');
        $parameter->setValueText('not a url');
        $part->addParameter($parameter);

        $resolver = $this->createResolver([
            $this->templateLink(['source_type' => 'parameter', 'source' => 'TrustedParts: Produkt-URL']),
        ]);

        $this->assertSame([], $resolver->resolve($part));
    }

    public function testDisabledDefinitionsAreIgnored(): void
    {
        $part = $this->createPart();
        $part->setManufacturerProductNumber('BC847C');

        $resolver = $this->createResolver([
            $this->templateLink(['enabled' => false]),
        ]);

        $this->assertSame([], $resolver->resolve($part));
    }

    public function testMultipleDefinitionsKeepTheirConfiguredOrder(): void
    {
        $part = $this->createPart();
        $part->setManufacturerProductNumber('BC847C');

        $resolver = $this->createResolver([
            $this->templateLink(['name' => 'Second', 'source' => 'https://second.example.com/{mpn}']),
            $this->templateLink(['name' => 'First', 'source' => 'https://first.example.com/{mpn}']),
        ]);

        $links = $resolver->resolve($part);
        $this->assertSame(['Second', 'First'], [$links[0]->name, $links[1]->name]);
    }
}
