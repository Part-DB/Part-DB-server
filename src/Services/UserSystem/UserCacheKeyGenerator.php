<?php
/**
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 * Copyright (C) 2019 - 2022 Jan Böhmer (https://github.com/jbtronics)
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

declare(strict_types=1);

namespace App\Services\UserSystem;

use App\Entity\UserSystem\User;
use Locale;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Security;

/**
 * Purpose of this service is to generate a key unique for a user, to use in Cache keys and tags.
 */
class UserCacheKeyGenerator
{
    protected Security $security;
    protected RequestStack $requestStack;

    public function __construct(Security $security, RequestStack $requestStack)
    {
        $this->security = $security;
        $this->requestStack = $requestStack;
    }

    /**
     * Generates a key for the given user.
     *
     * @param User|null $user The user for which the key should be generated. When set to null, the currently logged in
     *                        user is used.
     */
    public function generateKey(?User $user = null): string
    {
        $main_request = $this->requestStack->getMainRequest();
        //Retrieve the locale from the request, if possible, otherwise use the default locale
        $locale = $main_request ? $main_request->getLocale() : Locale::getDefault();

        //If no user was specified, use the currently used one.
        if (null === $user) {
            $user = $this->security->getUser();
        }

        //If the user is null, then treat it as anonymous user.
        //When the anonymous user is passed as user then use this path too.
        if (!($user instanceof User) || User::ID_ANONYMOUS === $user->getID()) {
            return 'user$_'.User::ID_ANONYMOUS;
        }

        //In the most cases we can just use the username (its unique)
        return 'user_'.$user->getUsername().'_'.$locale;
    }
}
