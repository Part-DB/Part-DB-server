<?php

declare(strict_types=1);

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
namespace App\Services\Misc;

class ConsoleInfoHelper
{
    /**
     * Returns true if the current script is executed in a CLI environment.
     * @return bool true if the current script is executed in a CLI environment, false otherwise
     */
    public function isCLI(): bool
    {
        return \in_array(\PHP_SAPI, ['cli', 'phpdbg'], true);
    }

    /**
     * Returns the username of the user who started the current script if possible.
     * @return string|null the username of the user who started the current script if possible, null otherwise
     * @noinspection PhpUndefinedFunctionInspection
     */
    public function getCLIUser(): ?string
    {
        if (!$this->isCLI()) {
            return null;
        }

        //Try to use the posix extension if available (Linux)
        if (function_exists('posix_getpwuid') && function_exists('posix_geteuid')) {
            $user = posix_getpwuid(posix_geteuid());
            return $user['name'];
        }

        //Otherwise we can't determine the username
        return $_SERVER['USERNAME'] ?? $_SERVER['USER'] ?? null;
    }
}
