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

namespace App\Security\OAuth\Entity;

use App\Entity\UserSystem\User;
use League\OAuth2\Server\Entities\UserEntityInterface;

/**
 * Adapts App\Entity\UserSystem\User (whose Symfony UserInterface identifier is its username) to
 * league/oauth2-server's UserEntityInterface, which identifies a user by an opaque scalar - we use the
 * numeric database id, since that's what App\Security\OAuth\Repository\AccessTokenRepository and
 * AuthCodeRepository resolve back into a User via EntityManager::find().
 */
class OAuthUserEntity implements UserEntityInterface
{
    public function __construct(private readonly int $id)
    {
    }

    public static function fromUser(User $user): self
    {
        return new self($user->getID());
    }

    public function getIdentifier(): string
    {
        return (string) $this->id;
    }
}
