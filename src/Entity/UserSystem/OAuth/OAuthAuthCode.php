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

use App\Entity\UserSystem\User;
use App\Repository\UserSystem\OAuth\OAuthAuthCodeRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * A revocation/single-use record for a short-lived OAuth2 authorization code (RFC 6749 section 4.1).
 *
 * league/oauth2-server hands the client an encrypted, self-contained code and never asks for this
 * data again - it only calls App\Security\OAuth\Repository\AuthCodeRepository::isAuthCodeRevoked()/
 * revokeAuthCode() by identifier. So unlike OAuthClient, this entity does not need to implement
 * AuthCodeEntityInterface; App\Security\OAuth\Entity\TransientAuthCode fills that role during issuance.
 * The client/user/scopes/redirect_uri columns exist purely for admin auditing.
 */
#[ORM\Entity(repositoryClass: OAuthAuthCodeRepository::class)]
#[ORM\Table(name: 'oauth_auth_codes')]
class OAuthAuthCode
{
    #[ORM\Id]
    #[ORM\Column(type: Types::INTEGER)]
    #[ORM\GeneratedValue]
    private int $id;

    #[ORM\Column(type: Types::STRING, unique: true)]
    private string $identifier;

    #[ORM\ManyToOne(targetEntity: OAuthClient::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private OAuthClient $client;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    /**
     * @var string[]
     */
    #[ORM\Column(type: Types::JSON)]
    private array $scopeIdentifiers = [];

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $expiryDateTime;

    #[ORM\Column(type: Types::STRING, nullable: true)]
    private ?string $redirectUri = null;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $revoked = false;

    /**
     * @param string[] $scopeIdentifiers
     */
    public function __construct(
        string $identifier,
        OAuthClient $client,
        User $user,
        array $scopeIdentifiers,
        \DateTimeImmutable $expiryDateTime,
        ?string $redirectUri,
    ) {
        $this->identifier = $identifier;
        $this->client = $client;
        $this->user = $user;
        $this->scopeIdentifiers = $scopeIdentifiers;
        $this->expiryDateTime = $expiryDateTime;
        $this->redirectUri = $redirectUri;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function getClient(): OAuthClient
    {
        return $this->client;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    /**
     * @return string[]
     */
    public function getScopeIdentifiers(): array
    {
        return $this->scopeIdentifiers;
    }

    public function getExpiryDateTime(): \DateTimeImmutable
    {
        return $this->expiryDateTime;
    }

    public function getRedirectUri(): ?string
    {
        return $this->redirectUri;
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
