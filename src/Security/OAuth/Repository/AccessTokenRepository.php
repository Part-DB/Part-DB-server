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
use App\Entity\UserSystem\ApiTokenLevel;
use App\Entity\UserSystem\ApiTokenType;
use App\Entity\UserSystem\OAuth\OAuthClient;
use App\Entity\UserSystem\User;
use App\Security\OAuth\Entity\TransientAccessToken;
use App\Security\OAuth\OAuthScope;
use Doctrine\ORM\EntityManagerInterface;
use League\OAuth2\Server\Entities\AccessTokenEntityInterface;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Repositories\AccessTokenRepositoryInterface;

/**
 * Makes an OAuth2 access token just be a regular App\Entity\UserSystem\ApiToken (with a new
 * ApiTokenType::OAUTH_ACCESS_TOKEN prefix), so App\Security\ApiTokenAuthenticator validates it with no
 * changes at all - no parallel JWT-verification path, no second revocation mechanism. See
 * TransientAccessToken for why the AccessTokenEntityInterface object itself is never persisted.
 */
class AccessTokenRepository implements AccessTokenRepositoryInterface
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function getNewToken(ClientEntityInterface $clientEntity, array $scopes, ?string $userIdentifier = null): AccessTokenEntityInterface
    {
        $accessToken = new TransientAccessToken();
        $accessToken->setClient($clientEntity);
        foreach ($scopes as $scope) {
            $accessToken->addScope($scope);
        }
        if ($userIdentifier !== null) {
            $accessToken->setUserIdentifier($userIdentifier);
        }

        return $accessToken;
    }

    public function persistNewAccessToken(AccessTokenEntityInterface $accessTokenEntity): void
    {
        $client = $accessTokenEntity->getClient();
        if (!$client instanceof OAuthClient) {
            throw new \LogicException('Expected an OAuthClient instance.');
        }

        $user = $this->entityManager->find(User::class, $accessTokenEntity->getUserIdentifier());
        if (!$user instanceof User) {
            throw new \LogicException('Could not resolve the user the access token was issued to.');
        }

        //ScopeRepository::finalizeScopes() always collapses the requested scopes down to a single
        //ApiTokenLevel, so there is exactly one scope here.
        $level = ApiTokenLevel::READ_ONLY;
        foreach ($accessTokenEntity->getScopes() as $scope) {
            if ($scope instanceof OAuthScope) {
                $level = $scope->getLevel();
                break;
            }
        }

        $apiToken = new ApiToken(ApiTokenType::OAUTH_ACCESS_TOKEN);
        $apiToken->setUser($user);
        $apiToken->setLevel($level);
        $apiToken->setName(sprintf('OAuth: %s', $client->getName()));
        $apiToken->setValidUntil($accessTokenEntity->getExpiryDateTime());
        $apiToken->setOauthClient($client);

        $this->entityManager->persist($apiToken);
        $this->entityManager->flush();

        //Overwrite league's randomly generated identifier with our own ApiToken's token string, so the
        //bearer token string returned to the client and api_tokens.token are the same value.
        $accessTokenEntity->setIdentifier($apiToken->getToken());
    }

    public function revokeAccessToken(string $tokenId): void
    {
        //A bulk DQL delete instead of load+remove()+flush(): during refresh token rotation,
        //RefreshTokenGrant::respondToAccessTokenRequest() revokes this access token and then, in the
        //same request, touches the OLD OAuthRefreshToken row that still references it - which is the
        //very same managed object sitting in the identity map since issuance. Loading (and therefore
        //re-managing) this ApiToken via the ORM here makes Doctrine try to validate that association on
        //the next flush() and fail ("a new entity was found through the relationship..."). A bulk delete
        //never touches the UnitOfWork's per-entity changeset tracking, so it can't trigger that.
        $this->entityManager->createQueryBuilder()
            ->delete(ApiToken::class, 't')
            ->where('t.token = :token')
            ->setParameter('token', $tokenId)
            ->getQuery()
            ->execute();
    }

    public function isAccessTokenRevoked(string $tokenId): bool
    {
        $apiToken = $this->findByToken($tokenId);

        return !$apiToken instanceof ApiToken || !$apiToken->isValid();
    }

    private function findByToken(string $token): ?ApiToken
    {
        return $this->entityManager->getRepository(ApiToken::class)->findOneBy(['token' => $token]);
    }
}
