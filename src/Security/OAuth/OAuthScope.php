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

namespace App\Security\OAuth;

use App\Entity\UserSystem\ApiTokenLevel;
use League\OAuth2\Server\Entities\ScopeEntityInterface;
use League\OAuth2\Server\Entities\Traits\EntityTrait;
use League\OAuth2\Server\Entities\Traits\ScopeTrait;

/**
 * An OAuth2 scope. There is no separate scope taxonomy: scopes are just the existing ApiTokenLevel
 * enum values (read_only/edit/admin/full), so an OAuth-granted token gets exactly the same Symfony
 * roles as an equivalent manually-created Personal Access Token. See OAuthScopeRepository.
 */
class OAuthScope implements ScopeEntityInterface
{
    use EntityTrait;
    use ScopeTrait;

    public function __construct(private readonly ApiTokenLevel $level)
    {
        $this->identifier = self::scopeIdentifierFor($level);
    }

    public function getLevel(): ApiTokenLevel
    {
        return $this->level;
    }

    public static function scopeIdentifierFor(ApiTokenLevel $level): string
    {
        return strtolower($level->name);
    }
}
