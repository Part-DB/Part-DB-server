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

namespace App\Security\Voter;

use App\Entity\LogSystem\AbstractLogEntry;
use App\Entity\UserSystem\User;

class LogEntryVoter extends ExtendedVoter
{
    public const ALLOWED_OPS = ['read', 'delete'];

    protected function voteOnUser(string $attribute, $subject, User $user): bool
    {
        if ('delete' === $attribute) {
            return $this->resolver->inherit($user, 'system', 'delete_logs') ?? false;
        }

        if ('read' === $attribute) {
            //Allow read of the users own log entries
            if (
                $subject->getUser() === $user
                && $this->resolver->inherit($user, 'self', 'show_logs')
            ) {
                return true;
            }

            return $this->resolver->inherit($user, 'system', 'show_logs') ?? false;
        }

        return false;
    }

    protected function supports($attribute, $subject): bool
    {
        if ($subject instanceof AbstractLogEntry) {
            return in_array($attribute, static::ALLOWED_OPS, true);
        }

        return false;
    }
}
