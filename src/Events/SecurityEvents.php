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

namespace App\Events;


class SecurityEvents
{
    public const PASSWORD_CHANGED = 'security.password_changed';
    public const PASSWORD_RESET = 'security.password_reset';
    public const BACKUP_KEYS_RESET = 'security.backup_keys_reset';
    public const U2F_ADDED = 'security.u2f_added';
    public const U2F_REMOVED = 'security.u2f_removed';
    public const GOOGLE_ENABLED = 'security.google_enabled';
    public const GOOGLE_DISABLED = 'security.google_disabled';
    public const TRUSTED_DEVICE_RESET = 'security.trusted_device_reset';
    public const TFA_ADMIN_RESET = 'security.2fa_admin_reset';
}