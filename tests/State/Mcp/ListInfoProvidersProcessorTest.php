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
use App\Mcp\DTO\ListInfoProvidersInput;
use App\Services\InfoProviderSystem\DTOs\InfoProviderDTO;
use App\Services\InfoProviderSystem\Providers\InfoProviderInterface;
use App\Services\InfoProviderSystem\Providers\ProviderCapabilities;
use App\Services\InfoProviderSystem\ProviderRegistry;
use App\State\Mcp\ListInfoProvidersProcessor;
use PHPUnit\Framework\TestCase;

final class ListInfoProvidersProcessorTest extends TestCase
{
    public function testOnlyActiveProvidersAreListed(): void
    {
        $active = $this->createMock(InfoProviderInterface::class);
        $active->method('getProviderKey')->willReturn('active_provider');
        $active->method('isActive')->willReturn(true);
        $active->method('getProviderInfo')->willReturn([
            'name' => 'Active Provider',
            'description' => 'A provider that is active',
            'url' => 'https://example.com',
        ]);
        $active->method('getCapabilities')->willReturn([ProviderCapabilities::BASIC, ProviderCapabilities::PRICE]);

        $disabled = $this->createMock(InfoProviderInterface::class);
        $disabled->method('getProviderKey')->willReturn('disabled_provider');
        $disabled->method('isActive')->willReturn(false);
        $disabled->method('getProviderInfo')->willReturn(['name' => 'Disabled Provider']);
        $disabled->method('getCapabilities')->willReturn([]);

        $registry = new ProviderRegistry([$active, $disabled]);
        $processor = new ListInfoProvidersProcessor($registry);

        $result = $processor->process(new ListInfoProvidersInput(), new Get());

        $this->assertCount(1, $result);
        $this->assertInstanceOf(InfoProviderDTO::class, $result[0]);
        $this->assertSame('active_provider', $result[0]->key);
        $this->assertSame('Active Provider', $result[0]->name);
        $this->assertSame('A provider that is active', $result[0]->description);
        $this->assertSame('https://example.com', $result[0]->url);
        $this->assertSame(['BASIC', 'PRICE'], $result[0]->capabilities);
    }
}
