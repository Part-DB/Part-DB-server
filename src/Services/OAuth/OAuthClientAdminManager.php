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

namespace App\Services\OAuth;

use App\Entity\UserSystem\DynamicallyRegisteredOAuthClient;
use Doctrine\ORM\EntityManagerInterface;
use League\Bundle\OAuth2ServerBundle\Manager\ClientManagerInterface;
use League\Bundle\OAuth2ServerBundle\Manager\ScopeManagerInterface;
use League\Bundle\OAuth2ServerBundle\Model\AbstractClient;
use League\Bundle\OAuth2ServerBundle\Model\AccessToken;
use League\Bundle\OAuth2ServerBundle\Model\AuthorizationCode;
use League\Bundle\OAuth2ServerBundle\Model\Client;
use League\Bundle\OAuth2ServerBundle\Model\ClientInterface;
use League\Bundle\OAuth2ServerBundle\Model\RefreshToken;
use League\Bundle\OAuth2ServerBundle\ValueObject\Grant;
use League\Bundle\OAuth2ServerBundle\ValueObject\RedirectUri;
use League\Bundle\OAuth2ServerBundle\ValueObject\Scope;

/**
 * Backs the admin OAuth2 client overview (App\Controller\OAuthClientAdminController) - listing every
 * registered client (almost always RFC 7591 self-registered, see that controller's docblock) with how
 * many currently-live access tokens each one holds, and permanently removing a client plus everything it
 * was ever granted.
 *
 * Deliberately does NOT use League\Bundle\OAuth2ServerBundle\Service\CredentialsRevokerInterface's
 * DoctrineCredentialsRevoker: it unconditionally also issues an UPDATE against the DeviceCode model/table,
 * which does not exist in our schema (config/packages/league_oauth2_server.yaml disables the device code
 * grant, so migrations/Version20260726180454.php never created oauth2_device_code) - that call would
 * throw "no such table" here. The access/refresh tokens and authorization codes (the 3 credential types
 * we actually persist) are bulk-deleted directly instead, before removing the client itself - the
 * "client" FK columns are mapped ON DELETE CASCADE (see League's Persistence\Mapping\Driver), but SQLite
 * only enforces that when "PRAGMA foreign_keys = ON" is set on the connection, which is not guaranteed
 * here, so this does not rely on it.
 */
class OAuthClientAdminManager
{
    private const MAX_CLIENT_NAME_LENGTH = 128;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ClientManagerInterface $clientManager,
        private readonly ScopeManagerInterface $scopeManager,
        private readonly OAuthRedirectUriValidator $redirectUriValidator,
    ) {
    }

    /**
     * Manually registers a public (secret-less) client with a fixed authorization_code + refresh_token
     * grant set - the same shape App\Controller\OAuth\ClientRegistrationController's RFC 7591 endpoint
     * produces, just created directly by an admin instead of by the client itself. Useful when Dynamic
     * Client Registration is disabled (OAUTH_DCR_ENABLED) or simply not desired for a particular app.
     *
     * @param list<string> $redirectUris
     * @param list<string> $scopeNames
     *
     * @throws \InvalidArgumentException if $name, $redirectUris or $scopeNames are invalid - the message
     *                                    is safe to show directly to the (admin-only) user of this form
     */
    public function createClient(string $name, array $redirectUris, array $scopeNames): ClientInterface
    {
        $name = trim($name);
        if ('' === $name) {
            throw new \InvalidArgumentException('Name must not be empty.');
        }
        $name = mb_substr($name, 0, self::MAX_CLIENT_NAME_LENGTH);

        if ([] === $redirectUris) {
            throw new \InvalidArgumentException('At least one redirect URI is required.');
        }
        foreach ($redirectUris as $uri) {
            if (!$this->redirectUriValidator->isAllowed($uri)) {
                throw new \InvalidArgumentException(\sprintf('"%s" is not an allowed redirect URI.', $uri));
            }
        }

        if ([] === $scopeNames) {
            throw new \InvalidArgumentException('At least one scope is required.');
        }
        foreach ($scopeNames as $scopeName) {
            if (null === $this->scopeManager->find($scopeName)) {
                throw new \InvalidArgumentException(\sprintf('Unknown scope "%s".', $scopeName));
            }
        }

        $identifier = bin2hex(random_bytes(16));

        $client = new Client($name, $identifier, null);
        $client->setRedirectUris(...array_map(static fn (string $uri): RedirectUri => new RedirectUri($uri), $redirectUris));
        $client->setGrants(new Grant('authorization_code'), new Grant('refresh_token'));
        $client->setScopes(...array_map(static fn (string $scope): Scope => new Scope($scope), $scopeNames));
        $client->setActive(true);

        $this->clientManager->save($client);

        return $client;
    }

    /**
     * List every registered client, with how many currently-live access tokens each one holds and whether it was dynamically registered (RFC 7591) or manually created by an admin.
     * @return list<array{client: ClientInterface, liveTokenCount: int, dynamicallyRegistered: bool}>
     */
    public function listClientsWithLiveTokenCounts(): array
    {
        $clients = $this->clientManager->list(null);

        $rows = $this->entityManager->createQueryBuilder()
            ->select('IDENTITY(at.client) as clientId', 'COUNT(at.identifier) as tokenCount')
            ->from(AccessToken::class, 'at')
            ->where('at.revoked = false')
            ->andWhere('at.expiry > :now')
            ->groupBy('at.client')
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->getResult();

        /** @var array<string, int> $countByClientId */
        $countByClientId = [];
        foreach ($rows as $row) {
            $countByClientId[$row['clientId']] = (int) $row['tokenCount'];
        }

        $dynamicIdentifiers = array_flip($this->entityManager->createQueryBuilder()
            ->select('IDENTITY(m.client) as clientId')
            ->from(DynamicallyRegisteredOAuthClient::class, 'm')
            ->getQuery()
            ->getSingleColumnResult());

        return array_map(static fn (ClientInterface $client): array => [
            'client' => $client,
            'liveTokenCount' => $countByClientId[$client->getIdentifier()] ?? 0,
            'dynamicallyRegistered' => isset($dynamicIdentifiers[$client->getIdentifier()]),
        ], $clients);
    }

    /**
     * @return bool false if no client with that identifier exists
     */
    public function deleteClient(string $identifier): bool
    {
        $client = $this->clientManager->find($identifier);
        if (!$client instanceof AbstractClient) {
            return false;
        }

        $this->deleteAllCredentialsForClient($identifier);
        $this->clientManager->remove($client);
        // Any App\Entity\UserSystem\DynamicallyRegisteredOAuthClient row for this client is removed by the
        // database itself (ON DELETE CASCADE FK to oauth2_client.identifier) - no cleanup needed here.

        return true;
    }

    private function deleteAllCredentialsForClient(string $clientIdentifier): void
    {
        // Refresh tokens first - they reference access tokens (not the client directly), so this has to
        // run before the access tokens themselves are deleted.
        $accessTokenIdsSubQuery = $this->entityManager->createQueryBuilder()
            ->select('at2.identifier')
            ->from(AccessToken::class, 'at2')
            ->where('at2.client = :client')
            ->getDQL();

        $refreshTokenDelete = $this->entityManager->createQueryBuilder();
        $refreshTokenDelete->delete(RefreshToken::class, 'rt')
            ->where($refreshTokenDelete->expr()->in('rt.accessToken', $accessTokenIdsSubQuery))
            ->setParameter('client', $clientIdentifier)
            ->getQuery()
            ->execute();

        $this->entityManager->createQueryBuilder()
            ->delete(AccessToken::class, 'at')
            ->where('at.client = :client')
            ->setParameter('client', $clientIdentifier)
            ->getQuery()
            ->execute();

        $this->entityManager->createQueryBuilder()
            ->delete(AuthorizationCode::class, 'ac')
            ->where('ac.client = :client')
            ->setParameter('client', $clientIdentifier)
            ->getQuery()
            ->execute();
    }
}
