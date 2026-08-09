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

use App\Entity\UserSystem\ApiTokenLevel;
use Doctrine\ORM\EntityManagerInterface;
use League\Bundle\OAuth2ServerBundle\Manager\ClientManagerInterface;
use League\Bundle\OAuth2ServerBundle\Model\AccessToken;
use League\Bundle\OAuth2ServerBundle\Model\AuthorizationCode;
use League\Bundle\OAuth2ServerBundle\Model\ClientInterface;
use League\Bundle\OAuth2ServerBundle\Model\RefreshToken;

/**
 * Lists and revokes the OAuth2 clients (League\Bundle\OAuth2ServerBundle) a given user has authorized -
 * the "connected apps" shown in user settings (templates/users/_oauth_connected_apps.html.twig).
 *
 * "Connected" means the client currently holds a live (non-revoked, non-expired) access token or refresh
 * token for that user; a client the user approved but whose grant has since fully expired or been revoked
 * no longer shows up here (there's nothing left for the user to revoke).
 */
readonly class ConnectedAppManager
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ClientManagerInterface $clientManager,
        private OAuthClientGrantPreferenceManager $grantPreferences,
    ) {
    }

    /**
     * Returns a list of all OAuth2 clients for a specific user that currently hold a live (non-revoked, non-expired) access token or refresh token for that user,
     * along with the expiry date of the latest token, the user's friendly name for that client (if any), the scope level granted, and the last used and connected timestamps.
     * Uses at the user settings page
     * @return list<array{client: ClientInterface, expiry: \DateTimeInterface, friendlyName: ?string, scopeLevel: ?ApiTokenLevel, lastUsedAt: ?\DateTimeImmutable, connectedAt: ?\DateTimeImmutable}>
     */
    public function listConnectedClients(string $userIdentifier): array
    {
        $now = new \DateTimeImmutable();

        $fromAccessTokens = $this->entityManager->createQueryBuilder()
            ->select('IDENTITY(at.client) as clientId', 'MAX(at.expiry) as expiry')
            ->from(AccessToken::class, 'at')
            ->where('at.userIdentifier = :userIdentifier')
            ->andWhere('at.revoked = false')
            ->andWhere('at.expiry > :now')
            ->groupBy('at.client')
            ->setParameter('userIdentifier', $userIdentifier)
            ->setParameter('now', $now)
            ->getQuery()
            ->getResult();

        $fromRefreshTokens = $this->entityManager->createQueryBuilder()
            ->select('IDENTITY(at.client) as clientId', 'MAX(rt.expiry) as expiry')
            ->from(RefreshToken::class, 'rt')
            ->join('rt.accessToken', 'at')
            ->where('at.userIdentifier = :userIdentifier')
            ->andWhere('rt.revoked = false')
            ->andWhere('rt.expiry > :now')
            ->groupBy('at.client')
            ->setParameter('userIdentifier', $userIdentifier)
            ->setParameter('now', $now)
            ->getQuery()
            ->getResult();

        /** @var array<string, \DateTimeInterface> $expiryByClientId */
        $expiryByClientId = [];
        foreach ([...$fromAccessTokens, ...$fromRefreshTokens] as $row) {
            $clientId = $row['clientId'];
            $expiry = $row['expiry'];
            if (!isset($expiryByClientId[$clientId]) || $expiry > $expiryByClientId[$clientId]) {
                $expiryByClientId[$clientId] = $expiry;
            }
        }

        $result = [];
        foreach ($expiryByClientId as $clientId => $expiry) {
            $client = $this->clientManager->find($clientId);
            if (null === $client) {
                continue;
            }
            $preference = $this->grantPreferences->find($userIdentifier, $clientId);
            $result[] = [
                'client' => $client,
                'expiry' => $expiry,
                'friendlyName' => $preference?->getFriendlyName(),
                'scopeLevel' => $preference?->getScopeLevel(),
                'lastUsedAt' => $preference?->getLastUsedAt(),
                'connectedAt' => $preference?->getCreatedAt(),
            ];
        }

        return $result;
    }

    /**
     * Revokes every access token, refresh token and authorization code the given client holds for the
     * given user - bulk DQL UPDATEs (mirroring League\Bundle\OAuth2ServerBundle\Service\CredentialsRevoker\DoctrineCredentialsRevoker,
     * which does the same thing but scoped to "all of a user" / "all of a client" rather than one pair),
     * not entity-load-then-flush, since this can touch many rows at once.
     */
    public function revokeForUserAndClient(string $userIdentifier, string $clientIdentifier): void
    {
        // Revoke all access tokens for this user+client pair
        $this->entityManager->createQueryBuilder()
            ->update(AccessToken::class, 'at')
            ->set('at.revoked', ':revoked')
            ->where('at.userIdentifier = :userIdentifier')
            ->andWhere('at.client = :client')
            ->setParameter('revoked', true)
            ->setParameter('userIdentifier', $userIdentifier)
            ->setParameter('client', $clientIdentifier)
            ->getQuery()
            ->execute();

        $accessTokenIdsSubQuery = $this->entityManager->createQueryBuilder()
            ->select('at2.identifier')
            ->from(AccessToken::class, 'at2')
            ->where('at2.userIdentifier = :userIdentifier')
            ->andWhere('at2.client = :client')
            ->getDQL();

        $refreshTokenUpdate = $this->entityManager->createQueryBuilder();
        $refreshTokenUpdate->update(RefreshToken::class, 'rt')
            ->set('rt.revoked', ':revoked')
            ->where($refreshTokenUpdate->expr()->in('rt.accessToken', $accessTokenIdsSubQuery))
            ->setParameter('revoked', true)
            ->setParameter('userIdentifier', $userIdentifier)
            ->setParameter('client', $clientIdentifier)
            ->getQuery()
            ->execute();

        $this->entityManager->createQueryBuilder()
            ->update(AuthorizationCode::class, 'ac')
            ->set('ac.revoked', ':revoked')
            ->where('ac.userIdentifier = :userIdentifier')
            ->andWhere('ac.client = :client')
            ->setParameter('revoked', true)
            ->setParameter('userIdentifier', $userIdentifier)
            ->setParameter('client', $clientIdentifier)
            ->getQuery()
            ->execute();
    }
}
