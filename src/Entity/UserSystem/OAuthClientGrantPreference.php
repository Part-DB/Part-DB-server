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

namespace App\Entity\UserSystem;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * A single user's preferences for one OAuth2 client (League\Bundle\OAuth2ServerBundle) they have
 * authorized at /authorize - the scope level they granted, an optional friendly name to tell apart
 * multiple connections to the same self-registered (RFC 7591) client, and how long the resulting
 * refresh token should stay valid before requiring re-authorization.
 *
 * Deliberately keyed by plain userIdentifier/clientIdentifier strings, not Doctrine relations to
 * App\Entity\UserSystem\User or League\Bundle\OAuth2ServerBundle\Model\Client - this mirrors how the
 * bundle's own AccessToken/RefreshToken/AuthorizationCode models already reference their user (see
 * App\Services\OAuth\ConnectedAppManager), and lets App\EventListener\OAuth\OAuthScopeResolveListener /
 * App\ApiPlatform... (App\Doctrine\OAuth\RefreshTokenTtlDecorator) look this up on every token mint
 * without needing an extra User entity fetch.
 */
#[ORM\Entity]
#[ORM\Table(name: 'oauth_client_grant_preferences')]
#[ORM\UniqueConstraint(name: 'oauth_client_grant_pref_user_client', columns: ['user_identifier', 'client_identifier'])]
class OAuthClientGrantPreference
{
    #[ORM\Id]
    #[ORM\Column(type: Types::INTEGER)]
    #[ORM\GeneratedValue]
    private int $id;

    #[ORM\Column(type: Types::STRING, length: 128, name: 'user_identifier')]
    private string $userIdentifier;

    #[ORM\Column(type: Types::STRING, length: 32, name: 'client_identifier')]
    private string $clientIdentifier;

    #[ORM\Column(type: Types::SMALLINT, enumType: ApiTokenLevel::class)]
    private ApiTokenLevel $scopeLevel;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $friendlyName = null;

    /**
     * Refresh token lifetime chosen by the user, in days - null means "use the server default"
     * (league_oauth2_server.yaml's authorization_server.refresh_token_ttl, currently P30D).
     */
    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $refreshTokenTtlDays = null;

    /**
     * Last time an OAuth2 access token for this user+client pair was used to authenticate a request -
     * the OAuth equivalent of App\Entity\UserSystem\ApiToken::$last_time_used. Updated by
     * App\Security\OAuth\OAuthBearerAuthenticator on every successful authentication (throttled the same
     * way ApiTokenAuthenticator throttles its own flush, to avoid a DB write on every single API request).
     */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $lastUsedAt = null;

    public function __construct(string $userIdentifier, string $clientIdentifier, ApiTokenLevel $scopeLevel)
    {
        $this->userIdentifier = $userIdentifier;
        $this->clientIdentifier = $clientIdentifier;
        $this->scopeLevel = $scopeLevel;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getUserIdentifier(): string
    {
        return $this->userIdentifier;
    }

    public function getClientIdentifier(): string
    {
        return $this->clientIdentifier;
    }

    public function getScopeLevel(): ApiTokenLevel
    {
        return $this->scopeLevel;
    }

    public function setScopeLevel(ApiTokenLevel $scopeLevel): self
    {
        $this->scopeLevel = $scopeLevel;
        return $this;
    }

    public function getFriendlyName(): ?string
    {
        return $this->friendlyName;
    }

    public function setFriendlyName(?string $friendlyName): self
    {
        $this->friendlyName = ('' === $friendlyName) ? null : $friendlyName;
        return $this;
    }

    public function getRefreshTokenTtlDays(): ?int
    {
        return $this->refreshTokenTtlDays;
    }

    public function setRefreshTokenTtlDays(?int $refreshTokenTtlDays): self
    {
        $this->refreshTokenTtlDays = $refreshTokenTtlDays;
        return $this;
    }

    public function getLastUsedAt(): ?\DateTimeImmutable
    {
        return $this->lastUsedAt;
    }

    public function setLastUsedAt(?\DateTimeImmutable $lastUsedAt): self
    {
        $this->lastUsedAt = $lastUsedAt;
        return $this;
    }
}
