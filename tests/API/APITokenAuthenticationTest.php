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

declare(strict_types=1);


namespace API;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\DataFixtures\APITokenFixtures;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use ApiPlatform\Symfony\Bundle\Test\Client;
class APITokenAuthenticationTest  extends ApiTestCase
{
    public function testUnauthenticated(): void
    {
        self::ensureKernelShutdown();
        $client = static::createClient();
        $client->request('GET', '/api/parts');
        self::assertResponseStatusCodeSame(401);
    }

    public function testExpiredToken(): void
    {
        self::ensureKernelShutdown();
        $client = $this->createClientWithCredentials(APITokenFixtures::TOKEN_EXPIRED);
        $client->request('GET', '/api/parts');
        self::assertResponseStatusCodeSame(401);
    }

    public function testReadOnlyToken(): void
    {
        self::ensureKernelShutdown();
        $client = $this->createClientWithCredentials(APITokenFixtures::TOKEN_READONLY);

        //Read should be possible
        $client->request('GET', '/api/parts');
        self::assertResponseIsSuccessful();

        //Trying to list all users and create a new footprint should fail
        $client->request('GET', '/api/users');
        self::assertResponseStatusCodeSame(403);

        $client->request('POST', '/api/footprints', ['json' => ['name' => 'post test']]);
        self::assertResponseStatusCodeSame(403);
    }

    public function testEditToken(): void
    {
        self::ensureKernelShutdown();
        $client = $this->createClientWithCredentials(APITokenFixtures::TOKEN_EDIT);

        //Read should be possible
        $client->request('GET', '/api/parts');
        self::assertResponseIsSuccessful();

        //Trying to list all users
        $client->request('GET', '/api/users');
        self::assertResponseStatusCodeSame(403);

        $client->request('POST', '/api/footprints', ['json' => ['name' => 'post test']]);
        self::assertResponseIsSuccessful();
    }

    public function testAdminToken(): void
    {
        self::ensureKernelShutdown();
        $client = $this->createClientWithCredentials(APITokenFixtures::TOKEN_ADMIN  );

        //Read should be possible
        $client->request('GET', '/api/parts');
        self::assertResponseIsSuccessful();

        //Trying to list all users
        $client->request('GET', '/api/users');
        self::assertResponseIsSuccessful();

        $client->request('POST', '/api/footprints', ['json' => ['name' => 'post test']]);
        self::assertResponseIsSuccessful();
    }

    protected function createClientWithCredentials(string $token): Client
    {
        return static::createClient([], ['headers' => ['authorization' => 'Bearer '.$token]]);
    }
}