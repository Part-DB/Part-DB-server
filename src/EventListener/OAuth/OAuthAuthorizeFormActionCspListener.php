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

namespace App\EventListener\OAuth;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

/**
 * Adds "form-action 'self'" to the CSP of the /oauth/authorize pages (consent screen and the
 * App\EventListener\OAuth\OAuthAuthorizeRedirectPageListener return page).
 *
 * config/packages/nelmio_security.yaml's CSP has no form-action directive, and form-action does *not* fall
 * back to default-src - without it, form submissions are not restricted at all. The consent form grants an
 * application access to the user's account, so we restrict it to only ever post back to Part-DB itself
 * (protects against injected markup retargeting the form).
 *
 * This does not interfere with the redirect back to the client: the consent form posts to Part-DB itself,
 * and the client's redirect_uri is reached via the return page's meta refresh, which is a new navigation
 * that form-action does not apply to. (A plain redirect would not work here: Chrome checks form-action on
 * every redirect after a form submission, including redirects the client's redirect_uri performs itself.)
 *
 * Scoped to this route instead of being set globally in nelmio_security.yaml, as other forms might rely on
 * redirecting to external hosts after submission.
 *
 * Must run *after* Nelmio\SecurityBundle\EventListener\ContentSecurityPolicyListener (kernel.response,
 * priority 0), which adds the header we amend here.
 */
#[AsEventListener(event: 'kernel.response', priority: -8)]
class OAuthAuthorizeFormActionCspListener
{
    private const DIRECTIVE = "form-action 'self'";

    public function __invoke(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        if ('oauth2_authorize' !== $event->getRequest()->attributes->get('_route')) {
            return;
        }

        $response = $event->getResponse();
        foreach (['Content-Security-Policy', 'X-Content-Security-Policy'] as $headerName) {
            $header = $response->headers->get($headerName);
            if (!\is_string($header) || '' === $header) {
                continue;
            }

            $directives = array_values(array_filter(
                array_map(trim(...), explode(';', $header)),
                static fn (string $directive): bool => '' !== $directive
            ));

            // Replace any existing form-action, so this page always gets exactly 'self'.
            $directives = array_values(array_filter(
                $directives,
                static fn (string $directive): bool => !str_starts_with(strtolower($directive), 'form-action')
            ));
            $directives[] = self::DIRECTIVE;

            $response->headers->set($headerName, implode('; ', $directives));
        }
    }
}
