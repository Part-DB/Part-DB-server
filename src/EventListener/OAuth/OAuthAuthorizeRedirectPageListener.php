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
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Twig\Environment;

/**
 * Replaces the redirect back to the OAuth client after the user submitted the consent form
 * (App\EventListener\OAuth\AuthorizationConsentListener, templates/oauth/authorize.html.twig) with a small
 * page that sends the browser on to the client's redirect_uri via a meta refresh (plus a manual link).
 *
 * Why not just redirect: browsers (Chrome in particular) enforce the consent page's CSP form-action directive
 * on *every* redirect of the navigation started by the form submission, not only on the form's own action.
 * We could allow the client's redirect_uri host there, but some clients' redirect URIs redirect further to
 * hosts we can't know in advance (e.g. Gemini's https://oauth-redirect.googleusercontent.com/r/... forwards
 * to other Google hosts), which then gets blocked by form-action. A meta refresh starts a new navigation that
 * form-action does not apply to, so the global "form-action 'self'" (nelmio_security.yaml) can stay strict.
 *
 * Safe for the same reason as App\EventListener\OAuth\OAuthExternalRedirectListener: league/oauth2-server has
 * already strictly validated the redirect target against the client's pre-registered redirect_uris.
 *
 * Runs before OAuthExternalRedirectListener (priority 32), which then sees a non-redirect response and leaves it
 * alone; error redirects of the initial GET request (e.g. invalid_scope) are not form submissions and keep
 * using a normal redirect.
 */
#[AsEventListener(event: 'kernel.response', priority: 40)]
class OAuthAuthorizeRedirectPageListener
{
    public function __construct(
        private readonly Environment $twig,
    ) {
    }

    public function __invoke(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        if ('oauth2_authorize' !== $request->attributes->get('_route') || !$request->isMethod('POST')) {
            return;
        }

        $response = $event->getResponse();
        if (!$response->isRedirect()) {
            return;
        }

        $target = $response->headers->get('Location');
        if (!\is_string($target) || '' === $target) {
            return;
        }

        $page = new Response($this->twig->render('oauth/authorize_redirect.html.twig', [
            'redirect_target' => $target,
        ]));
        // The target URL carries the authorization code - never cache this page.
        $page->headers->set('Cache-Control', 'no-store');

        $event->setResponse($page);
    }
}
