<?php

declare(strict_types=1);

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

namespace App\Security\Voter;

use App\Entity\LabelSystem\LabelProfile;
use App\Entity\UserSystem\User;

class LabelProfileVoter extends ExtendedVoter
{
    protected const MAPPING = [
        'read' => 'read_profiles',
        'create' => 'create_profiles',
        'edit' => 'edit_profiles',
        'delete' => 'delete_profiles',
        'show_history' => 'show_history',
        'revert_element' => 'revert_element',
    ];

    protected function voteOnUser(string $attribute, $subject, User $user): bool
    {
        return $this->resolver->inherit($user, 'labels', self::MAPPING[$attribute]) ?? false;
    }

    protected function supports($attribute, $subject): bool
    {
        if ($subject instanceof LabelProfile) {
            if (!isset(self::MAPPING[$attribute])) {
                return false;
            }

            return $this->resolver->isValidOperation('labels', self::MAPPING[$attribute]);
        }

        return false;
    }
}
