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

namespace App\Doctrine\OAuth;

use App\Services\OAuth\OAuthClientGrantPreferenceManager;
use League\OAuth2\Server\Entities\RefreshTokenEntityInterface;
use League\OAuth2\Server\Repositories\RefreshTokenRepositoryInterface;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;

/**
 * Applies the refresh token TTL the user picked on the consent screen
 * (App\EventListener\OAuth\AuthorizationConsentListener) - league_oauth2_server.yaml's
 * authorization_server.refresh_token_ttl (currently P30D) is a single fixed value for the whole server,
 * with no per-request hook to override it, so this decorates the bundle's own
 * League\Bundle\OAuth2ServerBundle\Repository\RefreshTokenRepository and overrides the expiry date on the
 * refresh token entity right before it's persisted - every time one is minted (initial authorization_code
 * exchange and every subsequent refresh rotation, since revoke_refresh_tokens is true), not just once.
 */
#[AsDecorator('league.oauth2_server.repository.refresh_token')]
class RefreshTokenTtlRepositoryDecorator implements RefreshTokenRepositoryInterface
{
    public function __construct(
        #[AutowireDecorated]
        private readonly RefreshTokenRepositoryInterface $decorated,
        private readonly OAuthClientGrantPreferenceManager $grantPreferences,
    ) {
    }

    public function getNewRefreshToken(): ?RefreshTokenEntityInterface
    {
        return $this->decorated->getNewRefreshToken();
    }

    public function persistNewRefreshToken(RefreshTokenEntityInterface $refreshTokenEntity): void
    {
        $userIdentifier = $refreshTokenEntity->getAccessToken()->getUserIdentifier();

        if (null !== $userIdentifier) {
            $clientIdentifier = $refreshTokenEntity->getAccessToken()->getClient()->getIdentifier();
            $preference = $this->grantPreferences->find($userIdentifier, $clientIdentifier);
            $ttlDays = $preference?->getRefreshTokenTtlDays();

            if (null !== $ttlDays) {
                $refreshTokenEntity->setExpiryDateTime(new \DateTimeImmutable(\sprintf('+%d days', $ttlDays)));
            }
        }

        $this->decorated->persistNewRefreshToken($refreshTokenEntity);
    }

    public function revokeRefreshToken(string $tokenId): void
    {
        $this->decorated->revokeRefreshToken($tokenId);
    }

    public function isRefreshTokenRevoked(string $tokenId): bool
    {
        return $this->decorated->isRefreshTokenRevoked($tokenId);
    }
}
