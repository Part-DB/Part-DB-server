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
use App\Entity\UserSystem\OAuthClientGrantPreference;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Manages the per (user, OAuth2 client) preferences recorded on the /oauth/authorize consent screen
 * (App\EventListener\OAuth\AuthorizationConsentListener): the granted scope level, an optional friendly
 * name, and a refresh token TTL - plus last-use tracking used by App\Security\OAuth\OAuthBearerAuthenticator
 * (the OAuth equivalent of App\Entity\UserSystem\ApiToken::$last_time_used).
 */
class OAuthClientGrantPreferenceManager
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function find(string $userIdentifier, string $clientIdentifier): ?OAuthClientGrantPreference
    {
        return $this->entityManager->getRepository(OAuthClientGrantPreference::class)
            ->findOneBy(['userIdentifier' => $userIdentifier, 'clientIdentifier' => $clientIdentifier]);
    }

    public function save(
        string $userIdentifier,
        string $clientIdentifier,
        ApiTokenLevel $scopeLevel,
        ?string $friendlyName,
        ?int $refreshTokenTtlDays,
    ): OAuthClientGrantPreference {
        $preference = $this->find($userIdentifier, $clientIdentifier);

        if (null === $preference) {
            $preference = new OAuthClientGrantPreference($userIdentifier, $clientIdentifier, $scopeLevel);
            $this->entityManager->persist($preference);
        } else {
            $preference->setScopeLevel($scopeLevel);
        }

        $preference->setFriendlyName($friendlyName);
        $preference->setRefreshTokenTtlDays($refreshTokenTtlDays);

        $this->entityManager->flush();

        return $preference;
    }

    /**
     * Records that an access token for this user+client pair was just used to authenticate a request -
     * throttled the same way App\Security\ApiTokenAuthenticator throttles ApiToken::$last_time_used, to
     * avoid a DB write on every single API request. Self-heals a missing preference row (e.g. a grant
     * made before this feature existed) by creating one from the token's own scopes, so usage is never
     * silently dropped just because the user never re-consented.
     *
     * @param list<string> $fallbackScopeNames lowercase ApiTokenLevel names (see
     *                                         App\Security\OAuth\OAuthBearerAuthenticator), used only if
     *                                         no preference row exists yet
     */
    public function recordUsage(string $userIdentifier, string $clientIdentifier, array $fallbackScopeNames = []): void
    {
        $preference = $this->find($userIdentifier, $clientIdentifier);

        if (null === $preference) {
            $preference = new OAuthClientGrantPreference(
                $userIdentifier,
                $clientIdentifier,
                $this->highestLevelAmong($fallbackScopeNames),
            );
            $this->entityManager->persist($preference);
        }

        $lastUsedAt = $preference->getLastUsedAt();
        $now = new \DateTimeImmutable();
        $preference->setLastUsedAt($now);

        // Only flush if the last-used date actually changed by more than 10 minutes (or was never set) -
        // otherwise every single API request would trigger a write.
        if (null === $lastUsedAt || $lastUsedAt->diff($now)->i > 10 || $lastUsedAt->diff($now)->days > 0) {
            $this->entityManager->flush();
        }
    }

    /**
     * @param list<string> $scopeNames lowercase ApiTokenLevel names
     */
    private function highestLevelAmong(array $scopeNames): ApiTokenLevel
    {
        $levels = array_values(array_filter(
            ApiTokenLevel::cases(),
            static fn (ApiTokenLevel $level) => \in_array(strtolower($level->name), $scopeNames, true)
        ));

        if ([] === $levels) {
            return ApiTokenLevel::READ_ONLY;
        }

        usort($levels, static fn (ApiTokenLevel $a, ApiTokenLevel $b) => $b->value <=> $a->value);

        return $levels[0];
    }
}
