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


namespace App\Security;

use App\Entity\UserSystem\ApiToken;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Authenticator\Token\PostAuthenticationToken;

class ApiTokenAuthenticatedToken extends PostAuthenticationToken
{
    public function __construct(UserInterface $user, string $firewallName, array $roles, private readonly ApiToken $apiToken)
    {
        //Add roles for the API
        $roles[] = 'ROLE_API_AUTHENTICATED';

        //Add roles based on the token level
        $roles = array_merge($roles, $apiToken->getLevel()->getAdditionalRoles());


        parent::__construct($user, $firewallName, array_unique($roles));
    }

    /**
     * Returns the API token that was used to authenticate the user.
     * @return ApiToken
     */
    public function getApiToken(): ApiToken
    {
        return $this->apiToken;
    }
}