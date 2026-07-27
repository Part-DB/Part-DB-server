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

namespace App\Tests\Controller;

use App\Entity\UserSystem\User;
use Doctrine\ORM\EntityManagerInterface;
use League\Bundle\OAuth2ServerBundle\Manager\ClientManagerInterface;
use League\Bundle\OAuth2ServerBundle\Model\Client;
use League\Bundle\OAuth2ServerBundle\ValueObject\Grant;
use League\Bundle\OAuth2ServerBundle\ValueObject\RedirectUri;
use League\Bundle\OAuth2ServerBundle\ValueObject\Scope;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Drives the real GET /tools/oauth_clients list + DELETE .../delete HTTP flow
 * (App\Controller\OAuthClientAdminController, App\Services\OAuth\OAuthClientAdminManager).
 */
final class OAuthClientAdminControllerTest extends WebTestCase
{
    private function loginAsAdmin(): array
    {
        $httpClient = static::createClient();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $admin = $entityManager->getRepository(User::class)->findOneBy(['name' => 'admin']);
        self::assertInstanceOf(User::class, $admin);
        $httpClient->loginUser($admin);

        return [$httpClient, $admin];
    }

    private function registerTestClient(KernelBrowser $httpClient): Client
    {
        $clientManager = $httpClient->getContainer()->get(ClientManagerInterface::class);

        $client = new Client('Admin-visible Test App', 'test-admin-'.bin2hex(random_bytes(8)), null);
        $client->setRedirectUris(new RedirectUri('https://client.example.invalid/callback'));
        $client->setGrants(new Grant('authorization_code'), new Grant('refresh_token'));
        $client->setScopes(new Scope('read_only'));
        $client->setActive(true);
        $clientManager->save($client);

        return $client;
    }

    public function testRegisteredClientIsListed(): void
    {
        [$httpClient, ] = $this->loginAsAdmin();
        $client = $this->registerTestClient($httpClient);

        $crawler = $httpClient->request('GET', '/en/tools/oauth_clients');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Admin-visible Test App', $crawler->filter('body')->text());
        self::assertStringContainsString($client->getIdentifier(), $crawler->filter('body')->text());
    }

    public function testDeleteRemovesClient(): void
    {
        [$httpClient, ] = $this->loginAsAdmin();
        $client = $this->registerTestClient($httpClient);
        $identifier = $client->getIdentifier();

        $crawler = $httpClient->request('GET', '/en/tools/oauth_clients');
        $form = $crawler->selectButton('Delete')->form();
        $httpClient->submit($form);

        self::assertResponseRedirects('/en/tools/oauth_clients');

        $clientManager = $httpClient->getContainer()->get(ClientManagerInterface::class);
        self::assertNull($clientManager->find($identifier));
    }

    public function testNonAdminUserIsDenied(): void
    {
        $httpClient = static::createClient();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $user = $entityManager->getRepository(User::class)->findOneBy(['name' => 'user']);
        self::assertInstanceOf(User::class, $user);
        $httpClient->loginUser($user);

        $httpClient->request('GET', '/en/tools/oauth_clients');
        self::assertResponseStatusCodeSame(403);
    }
}
