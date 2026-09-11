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
use App\Services\OAuth\ConnectedAppManager;
use Doctrine\ORM\EntityManagerInterface;
use League\Bundle\OAuth2ServerBundle\Manager\AccessTokenManagerInterface;
use League\Bundle\OAuth2ServerBundle\Manager\ClientManagerInterface;
use League\Bundle\OAuth2ServerBundle\Model\AccessToken;
use League\Bundle\OAuth2ServerBundle\Model\Client;
use League\Bundle\OAuth2ServerBundle\ValueObject\Grant;
use League\Bundle\OAuth2ServerBundle\ValueObject\RedirectUri;
use League\Bundle\OAuth2ServerBundle\ValueObject\Scope;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Drives the real user-settings "connected apps" list + revoke HTTP flow
 * (App\Controller\UserSettingsController::revokeConnectedApp, App\Services\OAuth\ConnectedAppManager).
 */
final class UserSettingsControllerConnectedAppsTest extends WebTestCase
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

    private function createConnectedApp(KernelBrowser $httpClient, User $admin): Client
    {
        $clientManager = $httpClient->getContainer()->get(ClientManagerInterface::class);
        $accessTokenManager = $httpClient->getContainer()->get(AccessTokenManagerInterface::class);

        $client = new Client('My Connected Test App', 'test-conn-http-'.bin2hex(random_bytes(8)), null);
        $client->setRedirectUris(new RedirectUri('https://client.example.invalid/callback'));
        $client->setGrants(new Grant('authorization_code'), new Grant('refresh_token'));
        $client->setScopes(new Scope('read_only'));
        $client->setActive(true);
        $clientManager->save($client);

        $accessTokenManager->save(new AccessToken(
            'at-'.bin2hex(random_bytes(16)),
            new \DateTimeImmutable('+1 hour'),
            $client,
            $admin->getUserIdentifier(),
            [new Scope('read_only')],
        ));

        return $client;
    }

    public function testConnectedAppIsListedAndCanBeRevoked(): void
    {
        [$httpClient, $admin] = $this->loginAsAdmin();
        $client = $this->createConnectedApp($httpClient, $admin);

        $crawler = $httpClient->request('GET', '/en/user/settings');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('My Connected Test App', $crawler->filter('body')->text());

        $form = $crawler->filter('button[name="client_id"][value="'.$client->getIdentifier().'"]')->form([
            'client_id' => $client->getIdentifier(),
        ]);
        $httpClient->submit($form);

        self::assertResponseRedirects('/en/user/settings');

        $connectedAppManager = $httpClient->getContainer()->get(ConnectedAppManager::class);
        self::assertSame([], $connectedAppManager->listConnectedClients($admin->getUserIdentifier()));
    }

    public function testRevokeWithInvalidCsrfTokenDoesNothing(): void
    {
        [$httpClient, $admin] = $this->loginAsAdmin();
        $client = $this->createConnectedApp($httpClient, $admin);

        $httpClient->request('POST', '/en/user/oauth_connected_apps/revoke', [
            '_method' => 'DELETE',
            '_token' => 'invalid',
            'client_id' => $client->getIdentifier(),
        ]);

        self::assertResponseRedirects('/en/user/settings');

        $connectedAppManager = $httpClient->getContainer()->get(ConnectedAppManager::class);
        self::assertCount(1, $connectedAppManager->listConnectedClients($admin->getUserIdentifier()));
    }
}
