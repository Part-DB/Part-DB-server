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

namespace App\Security\OAuth\Repository;

use App\Entity\UserSystem\ApiTokenLevel;
use App\Security\OAuth\OAuthScope;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\ScopeEntityInterface;
use League\OAuth2\Server\Repositories\ScopeRepositoryInterface;

/**
 * Scopes are just the existing ApiTokenLevel enum (read_only/edit/admin/full) - see OAuthScope. There
 * is no fine-grained per-endpoint scoping; an OAuth-granted token gets exactly the same Symfony roles
 * as an equivalent manually-created Personal Access Token of that level.
 */
class ScopeRepository implements ScopeRepositoryInterface
{
    public function getScopeEntityByIdentifier(string $identifier): ?ScopeEntityInterface
    {
        $level = $this->levelFromIdentifier($identifier);

        return $level !== null ? new OAuthScope($level) : null;
    }

    /**
     * @param  ScopeEntityInterface[]  $scopes
     * @return ScopeEntityInterface[]
     */
    public function finalizeScopes(array $scopes, string $grantType, ClientEntityInterface $clientEntity, ?string $userIdentifier = null, ?string $authCodeId = null): array
    {
        //ApiTokenLevel is cumulative (a higher level already includes everything a lower one grants),
        //so a token only ever has a single level. If several scopes were requested, keep only the
        //highest one; if none were requested/valid, default to the least-privileged level.
        $highest = ApiTokenLevel::READ_ONLY;
        foreach ($scopes as $scope) {
            if ($scope instanceof OAuthScope && $scope->getLevel()->value > $highest->value) {
                $highest = $scope->getLevel();
            }
        }

        return [new OAuthScope($highest)];
    }

    private function levelFromIdentifier(string $identifier): ?ApiTokenLevel
    {
        foreach (ApiTokenLevel::cases() as $level) {
            if (OAuthScope::scopeIdentifierFor($level) === $identifier) {
                return $level;
            }
        }

        return null;
    }
}
