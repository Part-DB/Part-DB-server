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

/**
 * This voter allows you to directly check permissions from the permission structure, without passing an object.
 * This use the syntax like "@permission.op"
 * However you should use the "normal" object based voters if possible, because they are needed for a future ACL system.
 */
class PermissionVoter extends ExtendedVoter
{
    /**
     * Similar to voteOnAttribute, but checking for the anonymous user is already done.
     * The current user (or the anonymous user) is passed by $user.
     *
     * @param string $attribute
     * @param mixed $subject
     */
    protected function voteOnUser($attribute, $subject, User $user): bool
    {
        $attribute = ltrim($attribute, '@');
        [$perm, $op] = explode('.', $attribute);

        return $this->resolver->inherit($user, $perm, $op) ?? false;
    }

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
        //Check if the attribute has the form @permission.operation
        if (preg_match('#^@\\w+\\.\\w+$#', $attribute)) {
            $attribute = ltrim($attribute, '@');
            [$perm, $op] = explode('.', $attribute);

            return $this->resolver->isValidOperation($perm, $op);
        }

        return false;
    }
}
