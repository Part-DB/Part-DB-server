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

use App\Services\OAuth\OAuthClientAdminManager;
use Doctrine\ORM\EntityManagerInterface;
use League\Bundle\OAuth2ServerBundle\Manager\AccessTokenManagerInterface;
use League\Bundle\OAuth2ServerBundle\Manager\ClientManagerInterface;
use League\Bundle\OAuth2ServerBundle\Model\AccessToken;
use League\Bundle\OAuth2ServerBundle\Model\Client;
use League\Bundle\OAuth2ServerBundle\ValueObject\Grant;
use League\Bundle\OAuth2ServerBundle\ValueObject\RedirectUri;
use League\Bundle\OAuth2ServerBundle\ValueObject\Scope;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class OAuthClientAdminManagerTest extends KernelTestCase
{
    public function testListIncludesLiveTokenCountAndDeleteRemovesClientAndTokens(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        $clientManager = $container->get(ClientManagerInterface::class);
        $accessTokenManager = $container->get(AccessTokenManagerInterface::class);
        $manager = $container->get(OAuthClientAdminManager::class);

        $client = new Client('Admin Manager Test App', 'test-admin-mgr-'.bin2hex(random_bytes(8)), null);
        $client->setRedirectUris(new RedirectUri('https://client.example.invalid/callback'));
        $client->setGrants(new Grant('authorization_code'), new Grant('refresh_token'));
        $client->setScopes(new Scope('read_only'));
        $client->setActive(true);
        $clientManager->save($client);

        $accessToken = new AccessToken(
            'at-'.bin2hex(random_bytes(16)),
            new \DateTimeImmutable('+1 hour'),
            $client,
            'some-user',
            [new Scope('read_only')],
        );
        $accessTokenManager->save($accessToken);

        $rows = $manager->listClientsWithLiveTokenCounts();
        $row = self::findRowForClient($rows, $client->getIdentifier());
        self::assertNotNull($row);
        self::assertSame(1, $row['liveTokenCount']);

        self::assertTrue($manager->deleteClient($client->getIdentifier()));
        self::assertNull($clientManager->find($client->getIdentifier()));

        // AccessTokenManager::find() uses EntityManager::find(), which checks the identity map before
        // hitting the DB - without clearing it here, it would just hand back the same in-memory object
        // we already hold, masking whether the row was actually deleted.
        $container->get(EntityManagerInterface::class)->clear();
        self::assertNull($accessTokenManager->find($accessToken->getIdentifier()));

        self::assertFalse($manager->deleteClient($client->getIdentifier()));
    }

    /**
     * @param list<array{client: \League\Bundle\OAuth2ServerBundle\Model\ClientInterface, liveTokenCount: int}> $rows
     * @return array{client: \League\Bundle\OAuth2ServerBundle\Model\ClientInterface, liveTokenCount: int}|null
     */
    private static function findRowForClient(array $rows, string $identifier): ?array
    {
        foreach ($rows as $row) {
            if ($row['client']->getIdentifier() === $identifier) {
                return $row;
            }
        }

        return null;
    }
}
