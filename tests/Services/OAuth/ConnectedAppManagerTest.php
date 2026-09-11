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

namespace App\Tests\Services\OAuth;

use App\Services\OAuth\ConnectedAppManager;
use Doctrine\ORM\EntityManagerInterface;
use League\Bundle\OAuth2ServerBundle\Manager\AccessTokenManagerInterface;
use League\Bundle\OAuth2ServerBundle\Manager\AuthorizationCodeManagerInterface;
use League\Bundle\OAuth2ServerBundle\Manager\ClientManagerInterface;
use League\Bundle\OAuth2ServerBundle\Manager\RefreshTokenManagerInterface;
use League\Bundle\OAuth2ServerBundle\Model\AccessToken;
use League\Bundle\OAuth2ServerBundle\Model\AuthorizationCode;
use League\Bundle\OAuth2ServerBundle\Model\Client;
use League\Bundle\OAuth2ServerBundle\Model\RefreshToken;
use League\Bundle\OAuth2ServerBundle\ValueObject\Grant;
use League\Bundle\OAuth2ServerBundle\ValueObject\RedirectUri;
use League\Bundle\OAuth2ServerBundle\ValueObject\Scope;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class ConnectedAppManagerTest extends KernelTestCase
{
    private function createClient(ClientManagerInterface $clientManager): Client
    {
        $client = new Client('Test Connected App', 'test-conn-'.bin2hex(random_bytes(8)), null);
        $client->setRedirectUris(new RedirectUri('https://client.example.invalid/callback'));
        $client->setGrants(new Grant('authorization_code'), new Grant('refresh_token'));
        $client->setScopes(new Scope('read_only'));
        $client->setActive(true);
        $clientManager->save($client);

        return $client;
    }

    public function testListAndRevokeRoundTrip(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        $clientManager = $container->get(ClientManagerInterface::class);
        $accessTokenManager = $container->get(AccessTokenManagerInterface::class);
        $refreshTokenManager = $container->get(RefreshTokenManagerInterface::class);
        $authCodeManager = $container->get(AuthorizationCodeManagerInterface::class);
        $entityManager = $container->get(EntityManagerInterface::class);
        $manager = $container->get(ConnectedAppManager::class);

        $userIdentifier = 'test-user-'.bin2hex(random_bytes(8));
        $client = $this->createClient($clientManager);

        $accessToken = new AccessToken(
            'at-'.bin2hex(random_bytes(16)),
            new \DateTimeImmutable('+1 hour'),
            $client,
            $userIdentifier,
            [new Scope('read_only')],
        );
        $accessTokenManager->save($accessToken);

        $refreshToken = new RefreshToken(
            'rt-'.bin2hex(random_bytes(16)),
            new \DateTimeImmutable('+30 days'),
            $accessToken,
        );
        $refreshTokenManager->save($refreshToken);

        $authCode = new AuthorizationCode(
            'ac-'.bin2hex(random_bytes(16)),
            new \DateTimeImmutable('+10 minutes'),
            $client,
            $userIdentifier,
            [new Scope('read_only')],
        );
        $authCodeManager->save($authCode);

        // A second, unrelated user must not see this client as connected.
        self::assertSame([], $manager->listConnectedClients('someone-else'));

        $connected = $manager->listConnectedClients($userIdentifier);
        self::assertCount(1, $connected);
        self::assertSame($client->getIdentifier(), $connected[0]['client']->getIdentifier());
        // No App\Services\OAuth\OAuthClientGrantPreferenceManager preference was ever saved for this
        // user+client pair, so there's no registration date to report.
        self::assertNull($connected[0]['connectedAt']);

        $manager->revokeForUserAndClient($userIdentifier, $client->getIdentifier());

        self::assertSame([], $manager->listConnectedClients($userIdentifier));

        $entityManager->clear();
        self::assertTrue($accessTokenManager->find($accessToken->getIdentifier())->isRevoked());
        self::assertTrue($refreshTokenManager->find($refreshToken->getIdentifier())->isRevoked());
        self::assertTrue($authCodeManager->find($authCode->getIdentifier())->isRevoked());
    }

    public function testExpiredAccessTokenIsNotListedAsConnected(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        $clientManager = $container->get(ClientManagerInterface::class);
        $accessTokenManager = $container->get(AccessTokenManagerInterface::class);
        $manager = $container->get(ConnectedAppManager::class);

        $userIdentifier = 'test-user-'.bin2hex(random_bytes(8));
        $client = $this->createClient($clientManager);

        $accessToken = new AccessToken(
            'at-'.bin2hex(random_bytes(16)),
            new \DateTimeImmutable('-1 hour'),
            $client,
            $userIdentifier,
            [new Scope('read_only')],
        );
        $accessTokenManager->save($accessToken);

        self::assertSame([], $manager->listConnectedClients($userIdentifier));
    }
}
