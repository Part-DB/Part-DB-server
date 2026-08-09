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
use League\Bundle\OAuth2ServerBundle\Model\Client;

/**
 * Marks a single OAuth2 client (League\Bundle\OAuth2ServerBundle) as having been created through RFC 7591
 * Dynamic Client Registration (App\Controller\OAuth\ClientRegistrationController) rather than registered
 * by hand by an administrator (App\Services\OAuth\OAuthClientAdminManager::createClient()) - both produce
 * identical League\Bundle\OAuth2ServerBundle\Model\Client rows, so this is the only place that distinction
 * is persisted. One row is written per self-registered client; presence of a row is the marker, there is
 * nothing else to store.
 *
 * Keyed by an identifying ManyToOne to Client itself (mirroring how the bundle's own Driver maps
 * AccessToken/AuthorizationCode to Client - see League\Bundle\OAuth2ServerBundle\Persistence\Mapping\Driver)
 * with ON DELETE CASCADE, so this row is removed automatically by the database whenever the client it
 * refers to is deleted - App\Services\OAuth\OAuthClientAdminManager::deleteClient() does not clean this
 * table up itself, it relies entirely on this FK. On MySQL/PostgreSQL that cascade is always enforced; on
 * SQLite it is only enforced when "PRAGMA foreign_keys = ON" is set on the connection, which this app does
 * not do (see OAuthClientAdminManager's class docblock for the same trade-off on the bundle's own
 * oauth2_access_token/oauth2_authorization_code/oauth2_refresh_token tables) - so on SQLite a deleted
 * client's marker row is left behind as a harmless orphan (identifiers are random and never reused, and the
 * row carries no security-sensitive data).
 */
#[ORM\Entity]
#[ORM\Table(name: 'oauth_dynamically_registered_clients')]
class DynamicallyRegisteredOAuthClient
{
    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: Client::class)]
    #[ORM\JoinColumn(name: 'client_identifier', referencedColumnName: 'identifier', nullable: false, onDelete: 'CASCADE')]
    private Client $client;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, name: 'registered_at')]
    private \DateTimeImmutable $registeredAt;

    public function __construct(Client $client)
    {
        $this->client = $client;
        $this->registeredAt = new \DateTimeImmutable();
    }

    public function getClient(): Client
    {
        return $this->client;
    }

    public function getRegisteredAt(): \DateTimeImmutable
    {
        return $this->registeredAt;
    }
}
