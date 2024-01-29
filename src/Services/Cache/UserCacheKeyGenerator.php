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

namespace App\Services\Cache;

use App\Entity\UserSystem\User;
use Locale;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Purpose of this service is to generate a key unique for a user, to use in Cache keys and tags.
 */
class UserCacheKeyGenerator
{
    public function __construct(protected Security $security, protected RequestStack $requestStack)
    {
    }

    /**
     * Generates a key for the given user.
     *
     * @param User|null $user The user for which the key should be generated. When set to null, the currently logged in
     *                        user is used.
     */
    public function generateKey(?User $user = null): string
    {
        $request = $this->requestStack->getCurrentRequest();
        //Retrieve the locale from the request, if possible, otherwise use the default locale
        $locale = $request instanceof Request ? $request->getLocale() : Locale::getDefault();

        //If no user was specified, use the currently used one.
        if (!$user instanceof User) {
            $user = $this->security->getUser();
        }

        //If the user is null, then treat it as anonymous user.
        //When the anonymous user is passed as user then use this path too.
        if (!($user instanceof User) || User::ID_ANONYMOUS === $user->getID()) {
            return 'user$_'.User::ID_ANONYMOUS;
        }

        //Use the unique user id and the locale to generate the key
        return 'user_'.$user->getID().'_'.$locale;
    }
}
