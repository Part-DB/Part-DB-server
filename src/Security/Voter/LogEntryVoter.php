<?php

declare(strict_types=1);

/**
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 * Copyright (C) 2019 - 2020 Jan Böhmer (https://github.com/jbtronics)
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

use App\Entity\LogSystem\AbstractLogEntry;
use App\Entity\UserSystem\User;

class LogEntryVoter extends ExtendedVoter
{
    public const ALLOWED_OPS = ['read', 'delete'];

    protected function voteOnUser($attribute, $subject, User $user): bool
    {
        if ($subject instanceof AbstractLogEntry) {
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
        }

        return false;
    }

    protected function supports($attribute, $subject)
    {
        if ($subject instanceof AbstractLogEntry) {
            return in_array($subject, static::ALLOWED_OPS, true);
        }

        return false;
    }
}
