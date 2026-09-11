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
 * Scope-exempts the /oauth/authorize consent screen's CSP header so the browser is allowed to follow the
 * post-decision redirect to the OAuth client's (already-validated) redirect_uri.
 *
 * config/packages/nelmio_security.yaml's csp.enforce has no form-action directive, so browsers fall back
 * to default-src 'self' for it - which blocks the consent form (App\EventListener\OAuth\AuthorizationConsentListener,
 * templates/oauth/authorize.html.twig) from ever navigating anywhere but this app's own host, even though
 * the form itself posts back to /oauth/authorize (same-origin) and it's only the *server-side* redirect that
 * follows (to the client's redirect_uri) that needs to leave the site. form-action is evaluated against
 * the CSP delivered with the document containing the <form> (this GET response), not the response that
 * performs the redirect - and per spec/browser behaviour, it also covers the eventual redirect target, not
 * just the form's own "action" attribute.
 *
 * Safe for the same reason as App\EventListener\OAuth\OAuthExternalRedirectListener: league/oauth2-server
 * has already strictly validated redirect_uri against the client's own pre-registered list
 * (AuthCodeGrant::validateRedirectUri()) by the time AuthorizationConsentListener renders this page - we
 * just don't have that value here directly, so AuthorizationConsentListener stashes it onto the request as
 * the "oauth_authorize_redirect_uri" attribute for us to pick up.
 *
 * Must run *after* Nelmio\SecurityBundle\EventListener\ContentSecurityPolicyListener (default kernel.response
 * priority 0, no explicit priority) has already added its header - that listener only adds a
 * Content-Security-Policy header `if (!$response->headers->has('Content-Security-Policy'))`, so running
 * any earlier would just get silently skipped by it instead of amended.
 */
#[AsEventListener(event: 'kernel.response', priority: -8)]
class OAuthAuthorizeFormActionCspListener
{
    public function __invoke(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        if ('oauth2_authorize' !== $event->getRequest()->attributes->get('_route')) {
            return;
        }

        $redirectUri = $event->getRequest()->attributes->get('oauth_authorize_redirect_uri');
        if (!\is_string($redirectUri) || '' === $redirectUri) {
            return;
        }

        $source = $this->formActionSource($redirectUri);
        if (null === $source) {
            return;
        }

        $response = $event->getResponse();
        foreach (['Content-Security-Policy', 'X-Content-Security-Policy'] as $headerName) {
            $header = $response->headers->get($headerName);
            if (\is_string($header) && '' !== $header) {
                $response->headers->set($headerName, $this->withFormAction($header, $source));
            }
        }
    }

    /**
     * Builds a CSP source-expression that matches the given redirect_uri: a host-source with a wildcard
     * port for http(s) URIs (native/CLI clients on a loopback interface use an OS-assigned, effectively
     * random port each run - see App\Controller\OAuth\ClientRegistrationController::isAllowedRedirectUri()),
     * or a scheme-source for RFC 8252 §7.1 private-use URI schemes (which have no host at all).
     */
    private function formActionSource(string $redirectUri): ?string
    {
        $scheme = parse_url($redirectUri, PHP_URL_SCHEME);
        if (!\is_string($scheme) || '' === $scheme) {
            return null;
        }

        $host = parse_url($redirectUri, PHP_URL_HOST);
        if (\is_string($host) && '' !== $host) {
            return \sprintf('%s://%s:*', $scheme, $host);
        }

        return $scheme.':';
    }

    private function withFormAction(string $cspHeader, string $source): string
    {
        $directives = array_values(array_filter(array_map(trim(...), explode(';', $cspHeader)), static fn (string $d) => '' !== $d));

        $found = false;
        $directives = array_map(static function (string $directive) use ($source, &$found): string {
            if (str_starts_with($directive, 'form-action')) {
                $found = true;

                return $directive.' '.$source;
            }

            return $directive;
        }, $directives);

        if (!$found) {
            $directives[] = "form-action 'self' ".$source;
        }

        return implode('; ', $directives);
    }
}
