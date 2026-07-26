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

use App\Entity\Base\TimestampTrait;
use App\Entity\Contracts\TimeStampableInterface;
use App\Repository\UserSystem\OAuth\OAuthClientRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use League\OAuth2\Server\Entities\ClientEntityInterface;

/**
 * An OAuth2 client application that has been registered against this Part-DB instance, either via
 * Dynamic Client Registration (RFC 7591) or (in the future) manually by an admin.
 *
 * Only public clients (Authorization Code + PKCE, no client secret) are supported.
 *
 * Note: this class deliberately does NOT use league/oauth2-server's ClientTrait/EntityTrait. Those
 * traits declare their properties (e.g. $identifier, $name) without a type, and Doctrine attribute
 * mapping requires typed properties; PHP considers a typed redeclaration of an untyped trait property
 * "incompatible" (fatal error), so the interface is implemented manually instead.
 */
#[ORM\Entity(repositoryClass: OAuthClientRepository::class)]
#[ORM\Table(name: 'oauth_clients')]
#[ORM\HasLifecycleCallbacks]
class OAuthClient implements ClientEntityInterface, TimeStampableInterface
{
    use TimestampTrait;

    #[ORM\Id]
    #[ORM\Column(type: Types::INTEGER)]
    #[ORM\GeneratedValue]
    protected int $id;

    /**
     * The public client_id handed out to the client. This is what league/oauth2-server calls the
     * "identifier", it is NOT the same as the Doctrine primary key.
     */
    #[ORM\Column(type: Types::STRING, unique: true)]
    private string $identifier;

    #[ORM\Column(type: Types::STRING)]
    private string $name;

    /**
     * @var string[]
     */
    #[ORM\Column(type: Types::JSON)]
    private array $redirectUris = [];

    /**
     * The opaque bearer token used to authenticate against the RFC 7591 client-configuration endpoint
     * (GET/PUT/DELETE /oauth/register/{client_id}). Not related to the OAuth access tokens issued to users.
     */
    #[ORM\Column(type: Types::STRING, unique: true)]
    private string $registrationAccessToken;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $dynamicallyRegistered = true;

    /**
     * @param string[] $redirectUris
     */
    public function __construct(string $clientId, string $name, array $redirectUris, string $registrationAccessToken)
    {
        $this->identifier = $clientId;
        $this->name = $name;
        $this->redirectUris = $redirectUris;
        $this->registrationAccessToken = $registrationAccessToken;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    /**
     * @return string[]
     */
    public function getRedirectUri(): array
    {
        return $this->redirectUris;
    }

    /**
     * @return string[]
     */
    public function getRedirectUris(): array
    {
        return $this->redirectUris;
    }

    /**
     * @param string[] $redirectUris
     */
    public function setRedirectUris(array $redirectUris): self
    {
        $this->redirectUris = $redirectUris;
        return $this;
    }

    /**
     * Only public clients (Authorization Code + PKCE, no client secret) are supported.
     */
    public function isConfidential(): bool
    {
        return false;
    }

    public function getRegistrationAccessToken(): string
    {
        return $this->registrationAccessToken;
    }

    public function isDynamicallyRegistered(): bool
    {
        return $this->dynamicallyRegistered;
    }

    public function setDynamicallyRegistered(bool $dynamicallyRegistered): self
    {
        $this->dynamicallyRegistered = $dynamicallyRegistered;
        return $this;
    }
}
