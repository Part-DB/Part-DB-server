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

use App\Entity\PriceInformations\Pricedetail;
use App\Entity\UserSystem\User;

class PricedetailVoter extends ExtendedVoter
{
    /**
     * @var string[] When this permsission are encountered, they are checked on part
     */
    protected const PART_PERMS = ['show_history', 'revert_element'];

    protected function voteOnUser($attribute, $subject, User $user): bool
    {
        if (in_array($attribute, self::PART_PERMS, true)) {
            return $this->resolver->inherit($user, 'parts', $attribute) ?? false;
        }

        return $this->resolver->inherit($user, 'parts_prices', $attribute) ?? false;
    }

    protected function supports($attribute, $subject)
    {
        if (is_a($subject, Pricedetail::class, true)) {
            return in_array($attribute, array_merge(
                self::PART_PERMS,
                $this->resolver->listOperationsForPermission('parts_prices')
            ), true);
        }

        return false;
    }
}
