<?php
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

namespace App\Tests\Services\InfoProviderSystem;

use App\Services\InfoProviderSystem\ProviderRegistry;
use App\Services\InfoProviderSystem\Providers\InfoProviderInterface;
use PHPUnit\Framework\TestCase;

class ProviderRegistryTest extends TestCase
{

    /** @var InfoProviderInterface[] */
    private array $providers = [];

    public function setUp(): void
    {
        //Create some mock providers
        $this->providers = [
            $this->getMockProvider('test1'),
            $this->getMockProvider('test2'),
            $this->getMockProvider('test3', false),
        ];
    }

    public function getMockProvider(string $key, bool $active = true): InfoProviderInterface
    {
        $mock = $this->createMock(InfoProviderInterface::class);
        $mock->method('getProviderKey')->willReturn($key);
        $mock->method('isActive')->willReturn($active);

        return $mock;
    }

    public function testGetProviders(): void
    {
        $registry = new ProviderRegistry($this->providers);

        $this->assertEquals(
            [
                'test1' => $this->providers[0],
                'test2' => $this->providers[1],
                'test3' => $this->providers[2],
            ],
            $registry->getProviders());
    }

    public function testGetDisabledProviders(): void
    {
        $registry = new ProviderRegistry($this->providers);

        $this->assertEquals(
            [
                'test3' => $this->providers[2],
            ],
            $registry->getDisabledProviders());
    }

    public function testGetActiveProviders(): void
    {
        $registry = new ProviderRegistry($this->providers);

        $this->assertEquals(
            [
                'test1' => $this->providers[0],
                'test2' => $this->providers[1],
            ],
            $registry->getActiveProviders());
    }

    public function testGetProviderByKey(): void
    {
        $registry = new ProviderRegistry($this->providers);

        $this->assertEquals(
            $this->providers[0],
            $registry->getProviderByKey('test1')
        );
    }

    public function testThrowOnDuplicateKeyOfProviders(): void
    {
        $this->expectException(\LogicException::class);

        $registry = new ProviderRegistry([
            $this->getMockProvider('test1'),
            $this->getMockProvider('test2'),
            $this->getMockProvider('test1'),
        ]);
    }
}
