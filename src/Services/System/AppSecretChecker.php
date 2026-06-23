<?php
/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2024 Jan Böhmer (https://github.com/jbtronics)
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

namespace App\Services\System;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Checks whether APP_SECRET has been changed from the default value shipped with Part-DB.
 */
final readonly class AppSecretChecker
{
    /** Known default/example secrets that must not be used in production. */
    public const INSECURE_SECRETS = [
        'a03498528f5a5fc089273ec9ae5b2849', // default in .env
        '318b5d659e07a0b3f96d9b3a83b254ca', // default in .env.dev
        'CHANGE_ME' //example secret used in documentation and error messages
    ];

    public function __construct(
        #[Autowire('%kernel.secret%')]
        private string $appSecret,
    ) {
    }

    /**
     * @return bool True if the app secret is one of the known insecure default secrets, false otherwise.
     */
    public function isInsecureSecret(): bool
    {
        return in_array($this->appSecret, self::INSECURE_SECRETS, true);
    }

    /**
     * Generates a new random app secret that can be used to replace the default insecure one.
     * @return string
     * @throws \Random\RandomException
     */
    public function generateSecret(): string
    {
        //Symfony docs recommend 32 characters for the app secret, which are 16 random bytes when hex-encoded.
        return bin2hex(random_bytes(16));
    }
}
