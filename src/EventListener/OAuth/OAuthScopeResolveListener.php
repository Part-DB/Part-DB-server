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

use App\Entity\UserSystem\ApiTokenLevel;
use App\Services\OAuth\OAuthClientGrantPreferenceManager;
use League\Bundle\OAuth2ServerBundle\Event\ScopeResolveEvent;
use League\Bundle\OAuth2ServerBundle\OAuth2Events;
use League\Bundle\OAuth2ServerBundle\ValueObject\Scope;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Narrows the scope actually granted to an access/refresh token down to whatever level the user picked
 * on the consent screen (App\EventListener\OAuth\AuthorizationConsentListener), instead of always
 * granting everything the client requested.
 *
 * This is the *only* place that can actually narrow scopes: league/oauth2-server-bundle 1.2's
 * AuthorizationRequestResolveEvent (dispatched at /oauth/authorize) has no setter to feed narrowed scopes back
 * into the underlying League\OAuth2\Server\RequestTypes\AuthorizationRequest, and its controller
 * (League\Bundle\OAuth2ServerBundle\Controller\AuthorizationController::indexAction()) only ever reads
 * setUser()/setAuthorizationApproved() off it afterwards - so the authorization code itself always carries
 * the client's originally-requested scopes. OAuth2Events::SCOPE_RESOLVE, on the other hand, is dispatched
 * by ScopeRepository::finalizeScopes() every time a token is actually minted - for *both* the initial
 * authorization_code exchange and every subsequent refresh_token grant (see
 * League\OAuth2\Server\Grant\AuthCodeGrant and RefreshTokenGrant, both of which call finalizeScopes()) -
 * so narrowing here applies consistently for the life of the grant, not just once.
 */
#[AsEventListener(event: OAuth2Events::SCOPE_RESOLVE)]
readonly class OAuthScopeResolveListener
{
    public function __construct(
        private OAuthClientGrantPreferenceManager $grantPreferences,
    ) {
    }

    public function __invoke(ScopeResolveEvent $event): void
    {
        $userIdentifier = $event->getUserIdentifier();
        if (null === $userIdentifier) {
            // e.g. a client_credentials-style grant with no end user - not used by this app
            // (enable_client_credentials_grant is false), but guard against it regardless.
            throw new \RuntimeException(sprintf(
                'OAuthScopeResolveListener: no user identifier present for client "%s" - cannot narrow scopes',
                $event->getClient()->getIdentifier()
            ));
        }

        $preference = $this->grantPreferences->find((string) $userIdentifier, $event->getClient()->getIdentifier());
        if (null === $preference) {
            // No consent-time preference recorded (e.g. a grant made before this feature existed)
            throw new \RuntimeException(sprintf(
                'OAuthScopeResolveListener: no grant preference found for user "%s" and client "%s" - cannot narrow scopes',
                $userIdentifier,
                $event->getClient()->getIdentifier()
            ));

        }

        $allowedScopeNames = array_map(
            static fn (ApiTokenLevel $level) => strtolower($level->name),
            array_filter(
                ApiTokenLevel::cases(),
                static fn (ApiTokenLevel $level) => $level->value <= $preference->getScopeLevel()->value
            )
        );

        $narrowedScopes = array_values(array_filter(
            $event->getScopes(),
            static fn (Scope $scope) => \in_array((string) $scope, $allowedScopeNames, true)
        ));

        // Never grant an *empty* scope set just because of a stale/mismatched preference - that would
        // leave the token unable to do anything at all rather than merely narrower than requested.
        if ([] !== $narrowedScopes) {
            $event->setScopes(...$narrowedScopes);
        }
    }
}
