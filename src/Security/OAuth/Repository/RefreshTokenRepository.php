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

namespace App\Security\OAuth\Repository;

use App\Entity\UserSystem\ApiToken;
use App\Entity\UserSystem\OAuth\OAuthClient;
use App\Entity\UserSystem\OAuth\OAuthRefreshToken;
use App\Security\OAuth\Entity\TransientRefreshToken;
use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\EntityManagerInterface;
use League\OAuth2\Server\Entities\RefreshTokenEntityInterface;
use League\OAuth2\Server\Repositories\RefreshTokenRepositoryInterface;

/**
 * Refresh tokens are rotated on every use. Reuse of an already-revoked (i.e. already rotated-out)
 * refresh token is a signal of token theft, so isRefreshTokenRevoked() responds by revoking the whole
 * token family - deleting the linked ApiToken, not just this refresh token - per OAuth 2.1 guidance.
 *
 * revokeRefreshToken()/isRefreshTokenRevoked()'s family-kill path deliberately use bulk DQL
 * updates/deletes instead of loading entities through the ORM: during rotation,
 * League\OAuth2\Server\Grant\RefreshTokenGrant::respondToAccessTokenRequest() revokes the old access
 * token and old refresh token in the same request/EntityManager that originally persisted them, so the
 * OLD OAuthRefreshToken is still the exact same managed object sitting in the identity map from
 * issuance. Loading and mutating it via the ORM after AccessTokenRepository::revokeAccessToken() has
 * already deleted its linked ApiToken makes Doctrine try to validate that (now-stale) "apiToken"
 * association on the next flush() and fail ("a new entity was found through the relationship...").
 * Bulk DQL bypasses the UnitOfWork's per-entity changeset/association checks entirely.
 */
class RefreshTokenRepository implements RefreshTokenRepositoryInterface
{
    /**
     * The family id of the refresh token most recently revoked (via revokeRefreshToken()) in this
     * request, so the token about to be persisted (via persistNewRefreshToken(), which
     * league/oauth2-server always calls immediately afterward during rotation - see RefreshTokenGrant::
     * respondToAccessTokenRequest()) can carry the same family id forward instead of starting a new one.
     */
    private ?string $pendingFamilyId = null;

    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function getNewRefreshToken(): ?RefreshTokenEntityInterface
    {
        return new TransientRefreshToken();
    }

    public function persistNewRefreshToken(RefreshTokenEntityInterface $refreshTokenEntity): void
    {
        $accessToken = $refreshTokenEntity->getAccessToken();

        $apiToken = $this->entityManager->getRepository(ApiToken::class)
            ->findOneBy(['token' => $accessToken->getIdentifier()]);
        if (!$apiToken instanceof ApiToken) {
            throw new \LogicException('Could not resolve the ApiToken this refresh token belongs to.');
        }

        $client = $accessToken->getClient();
        if (!$client instanceof OAuthClient) {
            throw new \LogicException('Expected an OAuthClient instance.');
        }

        $familyId = $this->pendingFamilyId ?? bin2hex(random_bytes(16));
        $this->pendingFamilyId = null;

        $refreshToken = new OAuthRefreshToken(
            $refreshTokenEntity->getIdentifier(),
            $apiToken,
            $client,
            $refreshTokenEntity->getExpiryDateTime(),
            $familyId,
        );

        $this->entityManager->persist($refreshToken);
        $this->entityManager->flush();
    }

    public function revokeRefreshToken(string $tokenId): void
    {
        $familyId = $this->entityManager->createQueryBuilder()
            ->select('r.familyId')
            ->from(OAuthRefreshToken::class, 'r')
            ->where('r.identifier = :identifier')
            ->setParameter('identifier', $tokenId)
            ->getQuery()
            ->getOneOrNullResult(AbstractQuery::HYDRATE_SINGLE_SCALAR);

        if ($familyId === null) {
            return;
        }

        $this->pendingFamilyId = $familyId;

        $this->entityManager->createQueryBuilder()
            ->update(OAuthRefreshToken::class, 'r')
            ->set('r.revoked', ':revoked')
            ->where('r.identifier = :identifier')
            ->setParameter('revoked', true)
            ->setParameter('identifier', $tokenId)
            ->getQuery()
            ->execute();
    }

    public function isRefreshTokenRevoked(string $tokenId): bool
    {
        $row = $this->entityManager->createQueryBuilder()
            ->select('r.revoked', 'r.familyId')
            ->from(OAuthRefreshToken::class, 'r')
            ->where('r.identifier = :identifier')
            ->setParameter('identifier', $tokenId)
            ->getQuery()
            ->getOneOrNullResult(AbstractQuery::HYDRATE_ARRAY);

        if ($row === null) {
            //No record at all - can't validate, treat as revoked/invalid.
            return true;
        }

        if ($row['revoked']) {
            //Reuse of an already-rotated-out refresh token: kill the whole family - every refresh token
            //(and thus ApiToken) descended from the same original grant, not just this one, since the
            //currently active token in the chain is what actually needs to stop working.
            $apiTokenIds = $this->entityManager->createQueryBuilder()
                ->select('IDENTITY(r.apiToken) AS apiTokenId')
                ->from(OAuthRefreshToken::class, 'r')
                ->where('r.familyId = :familyId')
                ->setParameter('familyId', $row['familyId'])
                ->getQuery()
                ->getSingleColumnResult();

            $this->entityManager->createQueryBuilder()
                ->delete(OAuthRefreshToken::class, 'r')
                ->where('r.familyId = :familyId')
                ->setParameter('familyId', $row['familyId'])
                ->getQuery()
                ->execute();

            if ($apiTokenIds !== []) {
                $this->entityManager->createQueryBuilder()
                    ->delete(ApiToken::class, 't')
                    ->where('t.id IN (:ids)')
                    ->setParameter('ids', $apiTokenIds)
                    ->getQuery()
                    ->execute();
            }

            return true;
        }

        return false;
    }
}
