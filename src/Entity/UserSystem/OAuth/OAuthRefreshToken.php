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

namespace App\Entity\UserSystem\OAuth;

use App\Entity\UserSystem\ApiToken;
use App\Repository\UserSystem\OAuth\OAuthRefreshTokenRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * A revocation record for a refresh token issued alongside an OAuth2 access token (which is itself
 * just an ApiToken, see AccessTokenRepository). Refresh tokens are rotated on every use; reuse of an
 * already-revoked refresh token revokes the whole token family (see
 * App\Security\OAuth\Repository\RefreshTokenRepository::isRefreshTokenRevoked()).
 *
 * league/oauth2-server hands the client an encrypted, self-contained refresh token and never asks for
 * this data again - it only calls RefreshTokenRepository::isRefreshTokenRevoked()/revokeRefreshToken()
 * by identifier. So unlike OAuthClient, this entity does not need to implement
 * RefreshTokenEntityInterface; App\Security\OAuth\Entity\TransientRefreshToken fills that role during
 * issuance.
 */
#[ORM\Entity(repositoryClass: OAuthRefreshTokenRepository::class)]
#[ORM\Table(name: 'oauth_refresh_tokens')]
class OAuthRefreshToken
{
    #[ORM\Id]
    #[ORM\Column(type: Types::INTEGER)]
    #[ORM\GeneratedValue]
    private int $id;

    #[ORM\Column(type: Types::STRING, unique: true)]
    private string $identifier;

    /**
     * The ApiToken that this refresh token can be exchanged for a new access token for. Cascading the
     * delete means revoking/deleting the ApiToken (e.g. via the "revoke" button in user settings)
     * automatically invalidates the refresh token too.
     */
    #[ORM\ManyToOne(targetEntity: ApiToken::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ApiToken $apiToken;

    #[ORM\ManyToOne(targetEntity: OAuthClient::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private OAuthClient $client;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $expiryDateTime;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $revoked = false;

    /**
     * Shared by every refresh token in the same rotation chain (i.e. copied forward on each rotation,
     * starting from the one issued at the original authorization). Lets
     * App\Security\OAuth\Repository\RefreshTokenRepository::isRefreshTokenRevoked() revoke the whole
     * chain's *currently active* token when an old, already-rotated-out refresh token is replayed -
     * not just re-delete the (already dead) token from when this one was issued.
     */
    #[ORM\Column(type: Types::STRING)]
    private string $familyId;

    public function __construct(string $identifier, ApiToken $apiToken, OAuthClient $client, \DateTimeImmutable $expiryDateTime, string $familyId)
    {
        $this->identifier = $identifier;
        $this->apiToken = $apiToken;
        $this->client = $client;
        $this->expiryDateTime = $expiryDateTime;
        $this->familyId = $familyId;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function getApiToken(): ApiToken
    {
        return $this->apiToken;
    }

    public function getClient(): OAuthClient
    {
        return $this->client;
    }

    public function getExpiryDateTime(): \DateTimeImmutable
    {
        return $this->expiryDateTime;
    }

    public function getFamilyId(): string
    {
        return $this->familyId;
    }

    public function isRevoked(): bool
    {
        return $this->revoked;
    }

    public function setRevoked(bool $revoked): self
    {
        $this->revoked = $revoked;
        return $this;
    }
}
