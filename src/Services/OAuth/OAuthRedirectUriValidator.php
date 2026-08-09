<?php
/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2026 Jan Böhmer (https://github.com/jbtronics)
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

namespace App\Services\OAuth;

/**
 * Validates OAuth2 client redirect URIs - shared between RFC 7591 self-registration
 * (App\Controller\OAuth\ClientRegistrationController) and manual admin registration
 * (App\Controller\OAuthClientAdminController), so both paths accept exactly the same redirect URI shapes.
 */
class OAuthRedirectUriValidator
{
    /**
     * Accepts https:// URIs, loopback http:// URIs (127.0.0.1/localhost/[::1], any port), and private-use
     * URI schemes containing a dot (e.g. "com.example.app:/callback") - the three redirect URI shapes
     * RFC 8252 recommends for native/CLI apps like MCP clients. Plain http:// to a non-loopback host is
     * rejected (token/code leakage over an insecure channel), as is any URI carrying a fragment.
     */
    public function isAllowed(string $uri): bool
    {
        $parts = parse_url($uri);
        if (false === $parts || !isset($parts['scheme']) || isset($parts['fragment'])) {
            return false;
        }

        $scheme = strtolower($parts['scheme']);

        if ('https' === $scheme) {
            return isset($parts['host']) && '' !== $parts['host'];
        }

        if ('http' === $scheme) {
            $host = strtolower($parts['host'] ?? '');

            return \in_array($host, ['127.0.0.1', 'localhost', '::1', '[::1]'], true);
        }

        // Private-use URI scheme (RFC 8252 §7.1): require a dot to match the recommended reverse-DNS
        // style (e.g. "com.example.app"), reducing collisions with generic/likely-preregistered schemes.
        return 1 === preg_match('/^[a-z][a-z0-9+.-]*\.[a-z0-9+.-]*[a-z0-9]$/', $scheme);
    }
}
