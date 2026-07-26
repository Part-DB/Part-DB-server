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

use App\Entity\UserSystem\ApiTokenLevel;
use League\Bundle\OAuth2ServerBundle\Event\AuthorizationRequestResolveEvent;
use League\Bundle\OAuth2ServerBundle\OAuth2Events;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Twig\Environment;

/**
 * Renders the consent screen for the OAuth2 authorization code flow (see
 * config/packages/league_oauth2_server.yaml) and turns the user's decision into an approval/denial of
 * the AuthorizationRequestResolveEvent.
 *
 * league/oauth2-server-bundle 1.2 has no built-in consent UI or listener for this event at all (unlike
 * some other OAuth2 bundles) - the "user" on the event is resolved directly from the current Symfony
 * security session by AuthorizationRequestResolveEventFactory, which throws if nobody is logged in; the
 * /authorize access_control entry (IS_AUTHENTICATED_FULLY) makes sure a login happens first.
 *
 * The consent form resubmits to the exact same URL (same query string, so
 * league/oauth2-server re-derives an identical AuthorizationRequest - it only reads request params from
 * the query string, never the POST body), with the actual decision + a CSRF token in the POST body.
 */
#[AsEventListener(event: OAuth2Events::AUTHORIZATION_REQUEST_RESOLVE)]
class AuthorizationConsentListener
{
    public const CSRF_TOKEN_ID = 'oauth_authorize';

    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly Environment $twig,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
    ) {
    }

    public function __invoke(AuthorizationRequestResolveEvent $event): void
    {
        $request = $this->requestStack->getMainRequest();

        if ($request?->isMethod('POST') && $request->request->has('oauth_decision')) {
            $token = new CsrfToken(self::CSRF_TOKEN_ID, (string) $request->request->get('_csrf_token'));
            if (!$this->csrfTokenManager->isTokenValid($token)) {
                throw new AccessDeniedHttpException('Invalid CSRF token.');
            }

            $event->resolveAuthorization('approve' === $request->request->get('oauth_decision'));

            return;
        }

        $response = new Response($this->twig->render('oauth/authorize.html.twig', [
            'client' => $event->getClient(),
            'levels' => $this->scopesToLevels($event->getScopes()),
            'csrf_token_id' => self::CSRF_TOKEN_ID,
        ]));

        $event->setResponse($response);
    }

    /**
     * Scopes are just App\Entity\UserSystem\ApiTokenLevel case names lowercased (see
     * App\Security\OAuth\OAuthBearerAuthenticator and config/packages/league_oauth2_server.yaml's
     * scopes/role_prefix) - map them back for a human-readable consent screen.
     *
     * @param \League\Bundle\OAuth2ServerBundle\ValueObject\Scope[] $scopes
     * @return ApiTokenLevel[]
     */
    private function scopesToLevels(array $scopes): array
    {
        $requested = array_map(static fn ($scope) => (string) $scope, $scopes);

        return array_values(array_filter(
            ApiTokenLevel::cases(),
            static fn (ApiTokenLevel $level) => \in_array(strtolower($level->name), $requested, true)
        ));
    }
}
