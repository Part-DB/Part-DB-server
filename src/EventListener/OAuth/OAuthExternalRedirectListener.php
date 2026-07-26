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

namespace App\EventListener\OAuth;

use Nelmio\SecurityBundle\ExternalRedirect\ExternalRedirectResponse;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

/**
 * Exempts the OAuth2 /authorize and /token redirect responses (league/oauth2-server-bundle) from
 * NelmioSecurityBundle's generic external-redirect protection (config/packages/nelmio_security.yaml's
 * external_redirects, which 403s any redirect to a host not on its fixed allow_list).
 *
 * This is safe specifically because league/oauth2-server has *already* strictly validated the redirect
 * target against the OAuth client's own pre-registered redirect_uris (AuthCodeGrant::validateRedirectUri())
 * before this response was created - Nelmio's allow_list just has no way to know about that per-client
 * validation, since it's a static, app-wide list. This only re-exempts this one response object; it does
 * not disable Nelmio's protection anywhere else.
 *
 * Must run *before* Nelmio\SecurityBundle\EventListener\ExternalRedirectListener (registered at the
 * default kernel.response priority of 0), hence the explicit positive priority here.
 */
#[AsEventListener(event: 'kernel.response', priority: 32)]
class OAuthExternalRedirectListener
{
    private const OAUTH_REDIRECT_PATHS = ['/authorize', '/token'];

    public function __invoke(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        if (!\in_array($event->getRequest()->getPathInfo(), self::OAUTH_REDIRECT_PATHS, true)) {
            return;
        }

        $response = $event->getResponse();
        if ($response instanceof ExternalRedirectResponse || !$response->isRedirect()) {
            return;
        }

        $target = $response->headers->get('Location');
        $host = \is_string($target) ? parse_url($target, PHP_URL_HOST) : null;
        if (!\is_string($host)) {
            return;
        }

        $exemptResponse = new ExternalRedirectResponse($target, [$host], $response->getStatusCode());
        $exemptResponse->headers->add($response->headers->all());

        $event->setResponse($exemptResponse);
    }
}
