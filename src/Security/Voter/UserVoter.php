<?php
/**
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 * Copyright (C) 2019 - 2020 Jan Böhmer (https://github.com/jbtronics)
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

/**
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 * Copyright (C) 2019 Jan Böhmer (https://github.com/jbtronics)
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
 */

namespace App\Security\Voter;

use App\Entity\UserSystem\User;
use function in_array;

class UserVoter extends ExtendedVoter
{
    /**
     * Determines if the attribute and subject are supported by this voter.
     *
     * @param string $attribute An attribute
     * @param mixed  $subject   The subject to secure, e.g. an object the user wants to access or any other PHP type
     *
     * @return bool True if the attribute and subject are supported, false otherwise
     */
    protected function supports($attribute, $subject)
    {
        if (is_a($subject, User::class, true)) {
            return in_array($attribute, array_merge(
                $this->resolver->listOperationsForPermission('users'),
                $this->resolver->listOperationsForPermission('self')),
                            false
            );
        }

        return false;
    }

    /**
     * Similar to voteOnAttribute, but checking for the anonymous user is already done.
     * The current user (or the anonymous user) is passed by $user.
     *
     * @param string $attribute
     */
    protected function voteOnUser($attribute, $subject, User $user): bool
    {
        //Check if the checked user is the user itself
        if (($subject instanceof User) && $subject->getID() === $user->getID() &&
            $this->resolver->isValidOperation('self', $attribute)) {
            //Then we also need to check the self permission
            $tmp = $this->resolver->inherit($user, 'self', $attribute) ?? false;
            //But if the self value is not allowed then use just the user value:
            if ($tmp) {
                return $tmp;
            }
        }

        //Else just check users permission:
        if ($this->resolver->isValidOperation('users', $attribute)) {
            return $this->resolver->inherit($user, 'users', $attribute) ?? false;
        }

        return false;
    }
}
