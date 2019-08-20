<?php
/**
 *
 * part-db version 0.1
 * Copyright (C) 2005 Christoph Lechner
 * http://www.cl-projects.de/
 *
 * part-db version 0.2+
 * Copyright (C) 2009 K. Jacobs and others (see authors.php)
 * http://code.google.com/p/part-db/
 *
 * Part-DB Version 0.4+
 * Copyright (C) 2016 - 2019 Jan Böhmer
 * https://github.com/jbtronics
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA
 *
 */

namespace App\Services;


use App\Entity\UserSystem\User;
use Symfony\Component\Security\Core\Security;

/**
 * Purpose of this service is to generate a key unique for a user, to use in Cache keys and tags.
 * @package App\Services
 */
class UserCacheKeyGenerator
{
    protected $security;

    public function __construct(Security $security)
    {
        $this->security = $security;
    }

    /**
     * Generates a key for the given user.
     * @param User|null $user The user for which the key should be generated. When set to null, the currently logged in
     * user is used.
     * @return string
     */
    public function generateKey(User $user = null) : string
    {
        //If no user was specified, use the currently used one.
        if ($user === null) {
            $user = $this->security->getUser();
        }

        //If the user is null, then treat it as anonymous user.
        //When the anonymous user is passed as user then use this path too.
        if ($user === null || $user->getID() === User::ID_ANONYMOUS) {
            return 'user$_' . User::ID_ANONYMOUS;
        }

        //In the most cases we can just use the username (its unique)
        return "user_" . $user->getUsername();
    }
}