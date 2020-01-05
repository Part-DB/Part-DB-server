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

namespace App\Services\TFA;

use App\Entity\UserSystem\User;

/**
 * This services offers methods to manage backup codes for two factor authentication.
 */
class BackupCodeManager
{
    protected $backupCodeGenerator;

    public function __construct(BackupCodeGenerator $backupCodeGenerator)
    {
        $this->backupCodeGenerator = $backupCodeGenerator;
    }

    /**
     * Enable backup codes for the given user, by generating a set of backup codes.
     * If the backup codes were already enabled before, they a.
     */
    public function enableBackupCodes(User $user): void
    {
        if (empty($user->getBackupCodes())) {
            $this->regenerateBackupCodes($user);
        }
    }

    /**
     * Disable (remove) the backup codes when no other 2 factor authentication methods are enabled.
     */
    public function disableBackupCodesIfUnused(User $user): void
    {
        if ($user->isGoogleAuthenticatorEnabled()) {
            return;
        }

        $user->setBackupCodes([]);
    }

    /**
     * Generates a new set of backup codes for the user. If no backup codes were available before, new ones are
     * generated.
     *
     * @param User $user The user for which the backup codes should be regenerated
     */
    public function regenerateBackupCodes(User $user): void
    {
        $codes = $this->backupCodeGenerator->generateCodeSet();
        $user->setBackupCodes($codes);
    }
}
