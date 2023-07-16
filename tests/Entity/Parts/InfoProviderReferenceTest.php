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

namespace App\Tests\Entity\Parts;

use App\Entity\Parts\InfoProviderReference;
use App\Services\InfoProviderSystem\DTOs\PartDetailDTO;
use PHPUnit\Framework\TestCase;

class InfoProviderReferenceTest extends TestCase
{
    public function testNoProvider(): void
    {
        $provider = InfoProviderReference::noProvider();

        //The no provider instance should return false for the providerCreated method
        $this->assertFalse($provider->isProviderCreated());
        //And null for all other methods
        $this->assertNull($provider->getProviderKey());
        $this->assertNull($provider->getProviderId());
        $this->assertNull($provider->getProviderUrl());
        $this->assertNull($provider->getLastUpdated());
    }

    public function testProviderReference(): void
    {
        $provider = InfoProviderReference::providerReference('test', 'id', 'url');

        //The provider reference instance should return true for the providerCreated method
        $this->assertTrue($provider->isProviderCreated());
        //And the correct values for all other methods
        $this->assertEquals('test', $provider->getProviderKey());
        $this->assertEquals('id', $provider->getProviderId());
        $this->assertEquals('url', $provider->getProviderUrl());
        $this->assertNotNull($provider->getLastUpdated());
    }

    public function testFromPartDTO(): void
    {
        $dto = new PartDetailDTO(provider_key: 'test', provider_id: 'id', name: 'name', description: 'description', provider_url: 'url');
        $reference = InfoProviderReference::fromPartDTO($dto);

        //The provider reference instance should return true for the providerCreated method
        $this->assertTrue($reference->isProviderCreated());
        //And the correct values for all other methods
        $this->assertEquals('test', $reference->getProviderKey());
        $this->assertEquals('id', $reference->getProviderId());
        $this->assertEquals('url', $reference->getProviderUrl());
        $this->assertNotNull($reference->getLastUpdated());
    }
}
