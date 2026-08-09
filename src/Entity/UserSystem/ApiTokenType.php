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

/**
 * The type of ApiToken.
 * The enum value is the prefix of the token. It must be 3 characters long.
 */
enum ApiTokenType: string
{
    case PERSONAL_ACCESS_TOKEN = 'tcp';

    /**
     * Get the prefix of the token including the underscore
     * @return string
     */
    public function getTokenPrefix(): string
    {
        return $this->value . '_';
    }

    /**
     * Get the type from the token prefix
     * @param  string  $api_token
     * @return ApiTokenType
     */
    public static function getTypeFromToken(string $api_token): self
    {
        $parts = explode('_', $api_token);
        if (count($parts) !== 2) {
            throw new \InvalidArgumentException('Invalid token format');
        }
        return self::from($parts[0]);
    }

    /**
     * Checks if the given string looks like one of our own tokens (i.e. has a recognized prefix),
     * without throwing. Used by App\Security\ApiTokenAuthenticator and
     * App\Security\OAuth\OAuthBearerAuthenticator to decide, up front, which of the two authenticators
     * should even attempt to handle a given bearer credential.
     */
    public static function isRecognizedToken(string $token): bool
    {
        try {
            self::getTypeFromToken($token);
            return true;
        } catch (\InvalidArgumentException|\ValueError) {
            return false;
        }
    }
}
