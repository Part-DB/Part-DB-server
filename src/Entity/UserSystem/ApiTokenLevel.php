<?php
/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2023 Jan Böhmer (https://github.com/jbtronics)
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU Affero General Public License as published
 *  by the Free Software Foundation, either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU Affero General Public License for more details.
 *
 *  You should have received a copy of the GNU Affero General Public License
 *  along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

declare(strict_types=1);


namespace App\Entity\UserSystem;

enum ApiTokenLevel: int
{
    private const ROLE_READ_ONLY = 'ROLE_API_READ_ONLY';
    private const ROLE_EDIT = 'ROLE_API_EDIT';
    private const ROLE_ADMIN = 'ROLE_API_ADMIN';
    private const ROLE_FULL = 'ROLE_API_FULL';

    /**
     * The token can only read (non-sensitive) data.
     */
    case READ_ONLY = 1;
    /**
     * The token can read and edit (non-sensitive) data.
     */
    case EDIT = 2;
    /**
     * The token can do some administrative tasks (like viewing all log entries), but can not change passwords and create new tokens.
     */
    case ADMIN = 3;
    /**
     * The token can do everything the user can do.
     */
    case FULL = 4;

    /**
     * Returns the additional roles that the authenticated user should have when using this token.
     * @return string[]
     */
    public function getAdditionalRoles(): array
    {
        //The higher roles should always include the lower ones
        return match ($this) {
            self::READ_ONLY => [self::ROLE_READ_ONLY],
            self::EDIT => [self::ROLE_READ_ONLY, self::ROLE_EDIT],
            self::ADMIN => [self::ROLE_READ_ONLY, self::ROLE_EDIT, self::ROLE_ADMIN],
            self::FULL => [self::ROLE_READ_ONLY, self::ROLE_EDIT, self::ROLE_ADMIN, self::ROLE_FULL],
        };
    }

    /**
     * Returns the translation key for the name of this token level.
     * @return string
     */
    public function getTranslationKey(): string
    {
        return 'api_token.level.' . strtolower($this->name);
    }
}