<?php

declare(strict_types=1);

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

namespace App\Tests\State\Mcp;

use ApiPlatform\Metadata\Get;
use App\Mcp\DTO\InfoProviderSearchInput;
use App\Services\InfoProviderSystem\DTOs\ProviderInfoDTO;
use App\Services\InfoProviderSystem\DTOs\SearchResultDTO;
use App\Services\InfoProviderSystem\DTOtoEntityConverter;
use App\Services\InfoProviderSystem\PartInfoRetriever;
use App\Services\InfoProviderSystem\Providers\InfoProviderInterface;
use App\Services\InfoProviderSystem\ProviderRegistry;
use App\Settings\InfoProviderSystem\InfoProviderGeneralSettings;
use App\Settings\SystemSettings\LocalizationSettings;
use App\State\Mcp\SearchInfoProvidersProcessor;
use App\Tests\SettingsTestHelper;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final class SearchInfoProvidersProcessorTest extends TestCase
{
    private ProviderRegistry $providerRegistry;
    private PartInfoRetriever $infoRetriever;

    protected function setUp(): void
    {
        $providers = [
            $this->getMockProvider('test1'),
            $this->getMockProvider('test2', active: false),
        ];

        $this->providerRegistry = new ProviderRegistry($providers);

        $dtoToEntityConverter = new DTOtoEntityConverter(
            $this->createMock(EntityManagerInterface::class),
            SettingsTestHelper::createSettingsDummy(LocalizationSettings::class)
        );

        $this->infoRetriever = new PartInfoRetriever(
            $this->providerRegistry,
            $dtoToEntityConverter,
            new ArrayAdapter(),
            debugMode: true
        );
    }

    private function getMockProvider(string $key, bool $active = true): InfoProviderInterface
    {
        $mock = $this->createMock(InfoProviderInterface::class);
        $mock->method('isActive')->willReturn($active);
        $mock->method('getProviderInfo')->willReturn(new ProviderInfoDTO(key: $key, name: $key));
        $mock->method('searchByKeyword')->willReturn([
            new SearchResultDTO(provider_key: $key, provider_id: '1', name: 'Element 1', description: 'desc'),
        ]);

        return $mock;
    }

    public function testSearchWithExplicitProvider(): void
    {
        $processor = new SearchInfoProvidersProcessor($this->infoRetriever, $this->providerRegistry, SettingsTestHelper::createSettingsDummy(InfoProviderGeneralSettings::class));

        $result = $processor->process(new InfoProviderSearchInput(keyword: 'foo', providers: ['test1']), new Get());

        $this->assertCount(1, $result);
        $this->assertInstanceOf(SearchResultDTO::class, $result[0]);
        $this->assertSame('test1', $result[0]->provider_key);
    }

    public function testSearchWithUnknownProviderThrowsBadRequest(): void
    {
        $processor = new SearchInfoProvidersProcessor($this->infoRetriever, $this->providerRegistry, SettingsTestHelper::createSettingsDummy(InfoProviderGeneralSettings::class));

        $this->expectException(BadRequestHttpException::class);
        $processor->process(new InfoProviderSearchInput(keyword: 'foo', providers: ['unknown']), new Get());
    }

    public function testSearchWithInactiveProviderThrowsBadRequest(): void
    {
        $processor = new SearchInfoProvidersProcessor($this->infoRetriever, $this->providerRegistry, SettingsTestHelper::createSettingsDummy(InfoProviderGeneralSettings::class));

        $this->expectException(BadRequestHttpException::class);
        $processor->process(new InfoProviderSearchInput(keyword: 'foo', providers: ['test2']), new Get());
    }

    public function testSearchFallsBackToConfiguredDefaultProviders(): void
    {
        $settings = SettingsTestHelper::createSettingsDummy(InfoProviderGeneralSettings::class);
        $settings->defaultSearchProviders = ['test1'];

        $processor = new SearchInfoProvidersProcessor($this->infoRetriever, $this->providerRegistry, $settings);

        $result = $processor->process(new InfoProviderSearchInput(keyword: 'foo'), new Get());

        $this->assertCount(1, $result);
        $this->assertSame('test1', $result[0]->provider_key);
    }

    public function testSearchWithoutProvidersAndNoDefaultsThrowsBadRequest(): void
    {
        $processor = new SearchInfoProvidersProcessor($this->infoRetriever, $this->providerRegistry, SettingsTestHelper::createSettingsDummy(InfoProviderGeneralSettings::class));

        $this->expectException(BadRequestHttpException::class);
        $processor->process(new InfoProviderSearchInput(keyword: 'foo'), new Get());
    }
}
