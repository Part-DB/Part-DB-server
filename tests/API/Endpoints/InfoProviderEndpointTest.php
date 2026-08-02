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

namespace App\Tests\API\Endpoints;

use App\DataFixtures\APITokenFixtures;
use App\Tests\API\AuthenticatedApiTestCase;

class InfoProviderEndpointTest extends AuthenticatedApiTestCase
{
    public function testListInfoProviders(): void
    {
        $response = self::createAuthenticatedClient()->request('GET', '/api/info_providers');

        self::assertResponseIsSuccessful();

        $json = $response->toArray();
        self::assertIsArray($json['hydra:member']);
        self::assertNotEmpty($json['hydra:member']);

        $keys = array_column($json['hydra:member'], 'key');
        self::assertContains('test', $keys);

        //The 'test' provider has a disabledHelp set internally, but it must not leak into the API response
        $testProvider = $json['hydra:member'][array_search('test', $keys, true)];
        self::assertArrayHasKey('capabilities', $testProvider);
        self::assertArrayNotHasKey('disabledHelp', $testProvider);
        self::assertArrayNotHasKey('oauthAppName', $testProvider);
        self::assertArrayNotHasKey('settingsClass', $testProvider);

        //Capabilities must serialize as plain enum-name strings, not objects
        self::assertSame(['BASIC', 'FOOTPRINT'], $testProvider['capabilities']);
    }

    public function testListInfoProvidersRequiresAuthentication(): void
    {
        self::createClient()->request('GET', '/api/info_providers');
        self::assertResponseStatusCodeSame(401);
    }

    public function testListInfoProvidersRequiresPermission(): void
    {
        self::createAuthenticatedClient(APITokenFixtures::TOKEN_READONLY)->request('GET', '/api/info_providers');
        self::assertResponseStatusCodeSame(403);
    }

    public function testSearchInfoProviders(): void
    {
        $response = self::createAuthenticatedClient()->request('POST', '/api/info_providers/search', [
            'json' => [
                'keyword' => 'foo',
                'providers' => ['test'],
            ],
        ]);

        self::assertResponseIsSuccessful();

        $json = $response->toArray();
        self::assertIsArray($json['hydra:member']);
        self::assertNotEmpty($json['hydra:member']);
        self::assertSame('test', $json['hydra:member'][0]['provider_key']);
    }

    public function testSearchInfoProvidersWithUnknownProviderReturnsBadRequest(): void
    {
        self::createAuthenticatedClient()->request('POST', '/api/info_providers/search', [
            'json' => [
                'keyword' => 'foo',
                'providers' => ['unknown'],
            ],
        ]);

        self::assertResponseStatusCodeSame(400);
    }

    public function testSearchInfoProvidersRequiresAuthentication(): void
    {
        self::createClient()->request('POST', '/api/info_providers/search', [
            'json' => ['keyword' => 'foo', 'providers' => ['test']],
        ]);

        self::assertResponseStatusCodeSame(401);
    }

    public function testSearchInfoProvidersRequiresPermission(): void
    {
        self::createAuthenticatedClient(APITokenFixtures::TOKEN_READONLY)->request('POST', '/api/info_providers/search', [
            'json' => ['keyword' => 'foo', 'providers' => ['test']],
        ]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testGetInfoProviderPartDetails(): void
    {
        $response = self::createAuthenticatedClient()->request('POST', '/api/info_providers/details', [
            'json' => [
                'provider_key' => 'test',
                'provider_id' => 'element1',
            ],
        ]);

        self::assertResponseIsSuccessful();
        self::assertJsonContains([
            'provider_key' => 'test',
            'provider_id' => 'element1',
        ]);
    }

    public function testGetInfoProviderPartDetailsWithUnknownProviderReturnsBadRequest(): void
    {
        self::createAuthenticatedClient()->request('POST', '/api/info_providers/details', [
            'json' => [
                'provider_key' => 'unknown',
                'provider_id' => 'element1',
            ],
        ]);

        self::assertResponseStatusCodeSame(400);
    }

    public function testGetInfoProviderPartDetailsRequiresAuthentication(): void
    {
        self::createClient()->request('POST', '/api/info_providers/details', [
            'json' => ['provider_key' => 'test', 'provider_id' => 'element1'],
        ]);

        self::assertResponseStatusCodeSame(401);
    }

    public function testGetInfoProviderPartDetailsRequiresPermission(): void
    {
        self::createAuthenticatedClient(APITokenFixtures::TOKEN_READONLY)->request('POST', '/api/info_providers/details', [
            'json' => ['provider_key' => 'test', 'provider_id' => 'element1'],
        ]);

        self::assertResponseStatusCodeSame(403);
    }
}
