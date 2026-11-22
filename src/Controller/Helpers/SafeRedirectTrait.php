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


namespace App\Controller\Helpers;

use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Helper for controllers, which redirect to a user supplied URL (like a _redirect or redirect_back parameter).
 * Use safeRedirect() instead of redirect() for such URLs, to prevent open redirects.
 * Must only be used in classes extending Symfony's AbstractController.
 *
 * Only host-relative paths (like "/en/part/1?foo=bar") are accepted as redirect targets, as these are the only kind of
 * redirect targets Part-DB generates itself (see the uri_without_host twig function). Everything else (absolute URLs,
 * protocol-relative URLs, scheme-relative URLs like "https:/evil.example", backslash tricks, etc.) is rejected.
 * We cannot rely on nelmio's external_redirects listener for this, as it only detects URLs with a host part that PHP's
 * parse_url() understands, while browsers are much more lenient (e.g. "https:/evil.example" leads to evil.example).
 */
trait SafeRedirectTrait
{
    /**
     * Redirects to the given user supplied URL, if it is a local path on this server.
     * Otherwise (also if no URL was given) redirects to the given fallback route.
     * @param  mixed  $url The user supplied redirect target
     * @param  string  $fallbackRoute The route to redirect to, if $url is not a safe local path
     * @param  array<string, mixed>  $fallbackParameters The parameters of the fallback route
     */
    protected function safeRedirect(mixed $url, string $fallbackRoute, array $fallbackParameters = [], int $status = 302): RedirectResponse
    {
        $url = static::sanitizeRedirectTarget($url);
        if ($url !== null) {
            return $this->redirect($url, $status);
        }

        return $this->redirectToRoute($fallbackRoute, $fallbackParameters, $status);
    }

    /**
     * Returns the given user supplied URL, if it is a safe, host-relative path on this server, otherwise null.
     * Use this only if you need the URL itself (e.g. to render it in a template), otherwise use safeRedirect().
     * @return non-empty-string|null
     */
    protected static function sanitizeRedirectTarget(mixed $url): ?string
    {
        if (!is_string($url) || $url === '') {
            return null;
        }

        //Must start with a single slash. "//" and "/\" are protocol-relative URLs to an external host in browsers
        if ($url[0] !== '/' || (isset($url[1]) && ($url[1] === '/' || $url[1] === '\\'))) {
            return null;
        }

        //Browsers treat backslashes like slashes and strip tabs/newlines, which could be used to construct
        //a protocol-relative URL (e.g. "/\t/evil.example"). Legit paths are URL-encoded and never contain these chars.
        if (str_contains($url, '\\') || preg_match('/[\x00-\x20\x7F]/', $url) === 1) {
            return null;
        }

        return $url;
    }
}
