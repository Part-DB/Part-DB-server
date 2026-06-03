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

/**
 * Tests for App\Services\AI\AIPlatformRegistry
 */
declare(strict_types=1);

namespace App\Tests\Services\AI;

use App\Services\AI\AIPlatformRegistry;
use App\Services\AI\AIPlatforms;
use App\Services\AI\AIPlatformSettingsInterface;
use Jbtronics\SettingsBundle\Manager\SettingsManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\PlatformInterface;

class AIPlatformRegistryTest extends TestCase
{
    public function testRegistersEnabledPlatformsAndReturnsPlatform(): void
    {
        // Create a platform mock and expose it under the service tag name (openrouter)
        $platformMock = $this->createMock(PlatformInterface::class);

        // Settings for OpenRouter -> enabled
        $openRouterSettings = $this->createMock(AIPlatformSettingsInterface::class);
        $openRouterSettings->method('isAIPlatformEnabled')->willReturn(true);

        // Settings for LMStudio -> disabled
        $lmSettings = $this->createMock(AIPlatformSettingsInterface::class);
        $lmSettings->method('isAIPlatformEnabled')->willReturn(false);

        // Settings manager should return the corresponding settings object depending on the requested class name
        $settingsManager = $this->createMock(SettingsManagerInterface::class);
        $settingsManager->method('get')->willReturnMap([
            [AIPlatforms::OPENROUTER->toSettingsClass(), $openRouterSettings],
            [AIPlatforms::LMSTUDIO->toSettingsClass(), $lmSettings],
        ]);

        $platforms = new \ArrayIterator([
            AIPlatforms::OPENROUTER->toServiceTagName() => $platformMock,
        ]);

        $registry = new AIPlatformRegistry($settingsManager, $platforms);

        // OPENROUTER should be enabled and retrievable
        $this->assertTrue($registry->isEnabled(AIPlatforms::OPENROUTER));
        $this->assertSame($platformMock, $registry->getPlatform(AIPlatforms::OPENROUTER));

        // LMSTUDIO is either not registered or disabled -> should not be enabled
        $this->assertFalse($registry->isEnabled(AIPlatforms::LMSTUDIO));
        $this->expectException(\InvalidArgumentException::class);
        $registry->getPlatform(AIPlatforms::LMSTUDIO);
    }

    public function testGetEnabledPlatformsReturnsIndexedArray(): void
    {
        $platformMock = $this->createMock(PlatformInterface::class);

        $openRouterSettings = $this->createMock(AIPlatformSettingsInterface::class);
        $openRouterSettings->method('isAIPlatformEnabled')->willReturn(true);

        $settingsManager = $this->createMock(SettingsManagerInterface::class);
        $settingsManager->method('get')->willReturnMap([
            [AIPlatforms::OPENROUTER->toSettingsClass(), $openRouterSettings],
            [AIPlatforms::LMSTUDIO->toSettingsClass(), $this->createMock(AIPlatformSettingsInterface::class)],
        ]);

        $platforms = new \ArrayIterator([
            AIPlatforms::OPENROUTER->toServiceTagName() => $platformMock,
            // lmstudio not registered
        ]);

        $registry = new AIPlatformRegistry($settingsManager, $platforms);

        $enabled = $registry->getEnabledPlatforms();

        $this->assertArrayHasKey(AIPlatforms::OPENROUTER->value, $enabled);
        $this->assertSame($platformMock, $enabled[AIPlatforms::OPENROUTER->value]);
    }
}

