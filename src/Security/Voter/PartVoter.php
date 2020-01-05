<?php

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

use App\Entity\Parts\Part;
use App\Entity\UserSystem\User;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * A Voter that votes on Part entities.
 *
 * See parts permissions for valid operations.
 */
class PartVoter extends ExtendedVoter
{
    public const READ = 'read';

    protected function supports($attribute, $subject)
    {
        // replace with your own logic
        // https://symfony.com/doc/current/security/voters.html
        //return ($subject instanceof Part || in_array($subject, ['PERM_parts', 'PERM_parts_name']));

        if ($subject instanceof Part) {
            //Check if a sub permission should be checked -> $attribute has format name.edit
            if (false !== strpos($attribute, '.')) {
                [$perm, $op] = explode('.', $attribute);

                return $this->resolver->isValidOperation('parts_'.$perm, $op);
            }

            return $this->resolver->isValidOperation('parts', $attribute);
        }

        return false;
    }

    protected function voteOnUser($attribute, $subject, User $user): bool
    {
        if ($subject instanceof Part) {
            //Check for sub permissions
            if (false !== strpos($attribute, '.')) {
                [$perm, $op] = explode('.', $attribute);

                return $this->resolver->inherit($user, 'parts_'.$perm, $op) ?? false;
            }

            //Null concealing operator means, that no
            return $this->resolver->inherit($user, 'parts', $attribute) ?? false;
        }

        //Deny access by default.
        return false;
    }
}
