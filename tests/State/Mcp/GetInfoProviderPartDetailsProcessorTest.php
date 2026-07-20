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
use App\Mcp\DTO\InfoProviderPartDetailsInput;
use App\Services\InfoProviderSystem\DTOs\PartDetailDTO;
use App\Services\InfoProviderSystem\DTOtoEntityConverter;
use App\Services\InfoProviderSystem\PartInfoRetriever;
use App\Services\InfoProviderSystem\Providers\InfoProviderInterface;
use App\Services\InfoProviderSystem\ProviderRegistry;
use App\Settings\SystemSettings\LocalizationSettings;
use App\State\Mcp\GetInfoProviderPartDetailsProcessor;
use App\Tests\SettingsTestHelper;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final class GetInfoProviderPartDetailsProcessorTest extends TestCase
{
    private PartInfoRetriever $infoRetriever;

    protected function setUp(): void
    {
        $activeProvider = $this->createMock(InfoProviderInterface::class);
        $activeProvider->method('getProviderKey')->willReturn('test1');
        $activeProvider->method('isActive')->willReturn(true);
        $activeProvider->method('getDetails')->willReturn(
            new PartDetailDTO(provider_key: 'test1', provider_id: '42', name: 'Element 42', description: 'desc')
        );

        $inactiveProvider = $this->createMock(InfoProviderInterface::class);
        $inactiveProvider->method('getProviderKey')->willReturn('test2');
        $inactiveProvider->method('isActive')->willReturn(false);

        $providerRegistry = new ProviderRegistry([$activeProvider, $inactiveProvider]);

        $dtoToEntityConverter = new DTOtoEntityConverter(
            $this->createMock(EntityManagerInterface::class),
            SettingsTestHelper::createSettingsDummy(LocalizationSettings::class)
        );

        $this->infoRetriever = new PartInfoRetriever(
            $providerRegistry,
            $dtoToEntityConverter,
            new ArrayAdapter(),
            debugMode: true
        );
    }

    public function testGetDetails(): void
    {
        $processor = new GetInfoProviderPartDetailsProcessor($this->infoRetriever);

        $result = $processor->process(new InfoProviderPartDetailsInput(provider_key: 'test1', provider_id: '42'), new Get());

        $this->assertInstanceOf(PartDetailDTO::class, $result);
        $this->assertSame('test1', $result->provider_key);
        $this->assertSame('42', $result->provider_id);
    }

    public function testGetDetailsWithUnknownProviderThrowsBadRequest(): void
    {
        $processor = new GetInfoProviderPartDetailsProcessor($this->infoRetriever);

        $this->expectException(BadRequestHttpException::class);
        $processor->process(new InfoProviderPartDetailsInput(provider_key: 'unknown', provider_id: '42'), new Get());
    }

    public function testGetDetailsWithInactiveProviderThrowsBadRequest(): void
    {
        $processor = new GetInfoProviderPartDetailsProcessor($this->infoRetriever);

        $this->expectException(BadRequestHttpException::class);
        $processor->process(new InfoProviderPartDetailsInput(provider_key: 'test2', provider_id: '42'), new Get());
    }
}
