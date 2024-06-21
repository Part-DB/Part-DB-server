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


namespace App\Entity;

use App\Entity\Base\AbstractNamedDBElement;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use League\OAuth2\Client\Token\AccessTokenInterface;

/**
 * This entity represents a OAuth token pair (access and refresh token), for an application
 */
#[ORM\Entity]
#[ORM\Table(name: 'oauth_tokens')]
#[ORM\UniqueConstraint(name: 'oauth_tokens_unique_name', columns: ['name'])]
#[ORM\Index(columns: ['name'], name: 'oauth_tokens_name_idx')]
class OAuthToken extends AbstractNamedDBElement implements AccessTokenInterface
{
    /** @var string|null The short-term usable OAuth2 token */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $token = null;

    /** @var \DateTimeImmutable|null The date when the token expires */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $expires_at = null;

    /** @var string|null The refresh token for the OAuth2 auth */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $refresh_token = null;

    /**
     * The default expiration time for a authorization token, if no expiration time is given
     */
    private const DEFAULT_EXPIRATION_TIME = 3600;

    public function __construct(string $name, ?string $refresh_token, ?string $token = null, \DateTimeImmutable $expires_at = null)
    {
        //If token is given, you also have to give the expires_at date
        if ($token !== null && $expires_at === null) {
            throw new \InvalidArgumentException('If you give a token, you also have to give the expires_at date');
        }

        //If no refresh_token is given, the token is a client credentials grant token, which must have a token
        if ($refresh_token === null && $token === null) {
            throw new \InvalidArgumentException('If you give no refresh_token, you have to give a token!');
        }

        $this->name = $name;
        $this->refresh_token = $refresh_token;
        $this->expires_at = $expires_at;
        $this->token = $token;
    }

    public static function fromAccessToken(AccessTokenInterface $accessToken, string $name): self
    {
        return new self(
            $name,
            $accessToken->getRefreshToken(),
            $accessToken->getToken(),
            self::unixTimestampToDatetime($accessToken->getExpires() ?? time() + self::DEFAULT_EXPIRATION_TIME)
        );
    }

    private static function unixTimestampToDatetime(int $timestamp): \DateTimeImmutable
    {
        return \DateTimeImmutable::createFromFormat('U', (string)$timestamp);
    }

    public function getToken(): ?string
    {
        return $this->token;
    }

    public function getExpirationDate(): ?\DateTimeInterface
    {
        return $this->expires_at;
    }

    public function getRefreshToken(): string
    {
        return $this->refresh_token;
    }

    public function isExpired(): bool
    {
        //null token is always expired
        if ($this->token === null) {
            return true;
        }

        if ($this->expires_at === null) {
            return false;
        }

        return $this->expires_at->getTimestamp() < time();
    }

    /**
     * Returns true if this token is a client credentials grant token (meaning it has no refresh token), and
     * needs to be refreshed via the client credentials grant.
     * @return bool
     */
    public function isClientCredentialsGrant(): bool
    {
        return $this->refresh_token === null;
    }

    public function replaceWithNewToken(AccessTokenInterface $accessToken): void
    {
        $this->token = $accessToken->getToken();
        $this->refresh_token = $accessToken->getRefreshToken();
        //If no expiration date is given, we set it to the default expiration time
        $this->expires_at = self::unixTimestampToDatetime($accessToken->getExpires() ?? time() + self::DEFAULT_EXPIRATION_TIME);
    }

    public function getExpires(): ?int
    {
        return $this->expires_at->getTimestamp();
    }

    public function hasExpired(): bool
    {
        return $this->isExpired();
    }

    public function getValues(): array
    {
        return [];
    }
}