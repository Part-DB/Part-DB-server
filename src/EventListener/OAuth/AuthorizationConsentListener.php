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
use App\Services\OAuth\OAuthClientGrantPreferenceManager;
use League\Bundle\OAuth2ServerBundle\Event\AuthorizationRequestResolveEvent;
use League\Bundle\OAuth2ServerBundle\OAuth2Events;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
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
 * /oauth/authorize access_control entry (IS_AUTHENTICATED_FULLY) makes sure a login happens first.
 *
 * The consent form resubmits to the exact same URL (same query string, so
 * league/oauth2-server re-derives an identical AuthorizationRequest - it only reads request params from
 * the query string, never the POST body), with the actual decision + a CSRF token in the POST body.
 *
 * The user also picks a scope level, an optional friendly name, and a refresh token TTL, all persisted
 * via App\Services\OAuth\OAuthClientGrantPreferenceManager - see App\EventListener\OAuth\OAuthScopeResolveListener
 * (which is what actually narrows the granted scope, at token-issuance time - not this listener; the
 * league/oauth2-server-bundle 1.2 AuthorizationRequestResolveEvent has no way to mutate the underlying
 * AuthorizationRequest's scopes) and App\Doctrine\OAuth\RefreshTokenTtlRepositoryDecorator (which applies
 * the chosen TTL).
 */
#[AsEventListener(event: OAuth2Events::AUTHORIZATION_REQUEST_RESOLVE)]
class AuthorizationConsentListener
{
    public const CSRF_TOKEN_ID = 'oauth_authorize';

    /**
     * Preset choices offered on the consent screen for the refresh token TTL, in days. `null` means
     * "use the server default" (league_oauth2_server.yaml's authorization_server.refresh_token_ttl).
     *
     * @var list<int|null>
     */
    public const TTL_PRESETS_DAYS = [null, 1, 7, 30, 90, 365];

    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly Environment $twig,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
        private readonly OAuthClientGrantPreferenceManager $grantPreferences,
        #[Autowire('%league.oauth2_server.refresh_token_ttl.default%')]
        private readonly string $defaultRefreshTokenTtl,
    ) {
    }

    /**
     * Resolves league_oauth2_server.yaml's authorization_server.refresh_token_ttl (an ISO 8601 duration,
     * e.g. "P30D") to a whole number of days, so the consent screen can tell the user what "Default"
     * actually means instead of leaving it a mystery.
     */
    private function defaultTtlDays(): int
    {
        $now = new \DateTimeImmutable();

        return $now->diff($now->add(new \DateInterval($this->defaultRefreshTokenTtl)))->days;
    }

    public function __invoke(AuthorizationRequestResolveEvent $event): void
    {
        $request = $this->requestStack->getMainRequest();
        $userIdentifier = $event->getUser()->getUserIdentifier();
        $clientIdentifier = $event->getClient()->getIdentifier();

        $requestedLevels = $this->scopesToLevels($event->getScopes());
        $maxLevel = $this->highestLevel($requestedLevels) ?? ApiTokenLevel::READ_ONLY;
        $availableLevels = array_values(array_filter(
            ApiTokenLevel::cases(),
            static fn (ApiTokenLevel $level) => $level->value <= $maxLevel->value
        ));

        if ($request?->isMethod('POST') && $request->request->has('oauth_decision')) {
            $token = new CsrfToken(self::CSRF_TOKEN_ID, (string) $request->request->get('_csrf_token'));
            if (!$this->csrfTokenManager->isTokenValid($token)) {
                throw new AccessDeniedHttpException('Invalid CSRF token.');
            }

            $approved = 'approve' === $request->request->get('oauth_decision');

            if ($approved) {
                //Store an OAuthClientGrantPreference for this user+client pair, so the user's choice of scope level, friendly name,
                // and refresh token TTL is remembered for future grants (and so App\EventListener\OAuth\OAuthScopeResolveListener can narrow the granted scopes to that level).

                $selectedLevel = $this->parseSelectedLevel($request->request->get('oauth_scope_level'), $availableLevels);
                $friendlyName = $this->parseFriendlyName($request->request->get('oauth_friendly_name'));
                $ttlDays = $this->parseTtlDays($request->request->get('oauth_ttl_days'));

                $this->grantPreferences->save($userIdentifier, $clientIdentifier, $selectedLevel, $friendlyName, $ttlDays);
            }

            $event->resolveAuthorization($approved);

            return;
        }

        $existingPreference = $this->grantPreferences->find($userIdentifier, $clientIdentifier);

        // Stashed for App\EventListener\OAuth\OAuthAuthorizeFormActionCspListener (kernel.response), which
        // needs the already-validated redirect_uri to scope-exempt this one response's CSP form-action
        // directive (nelmio_security.yaml's csp has no form-action, so it falls back to default-src
        // 'self', which would otherwise block the browser from following the post-decision redirect to
        // this client's external redirect_uri).
        $request?->attributes->set('oauth_authorize_redirect_uri', $event->getRedirectUri());

        $response = new Response($this->twig->render('oauth/authorize.html.twig', [
            'client' => $event->getClient(),
            'levels' => $requestedLevels,
            'available_levels' => $availableLevels,
            'selected_level' => $existingPreference?->getScopeLevel() ?? $maxLevel,
            'friendly_name' => $existingPreference?->getFriendlyName(),
            'ttl_presets_days' => self::TTL_PRESETS_DAYS,
            'selected_ttl_days' => $existingPreference?->getRefreshTokenTtlDays(),
            'default_ttl_days' => $this->defaultTtlDays(),
            'csrf_token_id' => self::CSRF_TOKEN_ID,
            // "edit" (read + write of ordinary, non-sensitive data) is the normal ceiling for an app to
            // ask for; admin/full go beyond that (e.g. viewing all users' log entries, or acting as the
            // user entirely), so the template calls those out with an extra warning.
            'edit_level_value' => ApiTokenLevel::EDIT->value,
            'elevated_scope_requested' => $maxLevel->value > ApiTokenLevel::EDIT->value,
        ]));

        $event->setResponse($response);
    }

    /**
     * @param ApiTokenLevel[] $availableLevels
     */
    private function parseSelectedLevel(mixed $rawLevel, array $availableLevels): ApiTokenLevel
    {
        if (!\is_string($rawLevel)) {
            throw new BadRequestHttpException('Missing scope level.');
        }

        foreach ($availableLevels as $level) {
            if (strtolower($level->name) === $rawLevel) {
                return $level;
            }
        }

        throw new BadRequestHttpException('Invalid scope level.');
    }

    private function parseFriendlyName(mixed $rawName): ?string
    {
        if (!\is_string($rawName)) {
            return null;
        }

        $trimmed = trim($rawName);
        if ('' === $trimmed) {
            return null;
        }

        return mb_substr($trimmed, 0, 255);
    }

    private function parseTtlDays(mixed $rawTtlDays): ?int
    {
        if (!\is_string($rawTtlDays) || '' === $rawTtlDays) {
            return null;
        }

        if (!ctype_digit($rawTtlDays)) {
            throw new BadRequestHttpException('Invalid refresh token TTL.');
        }

        $days = (int) $rawTtlDays;
        if (!\in_array($days, self::TTL_PRESETS_DAYS, true)) {
            throw new BadRequestHttpException('Invalid refresh token TTL.');
        }

        return $days;
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

    /**
     * @param ApiTokenLevel[] $levels
     */
    private function highestLevel(array $levels): ?ApiTokenLevel
    {
        if ([] === $levels) {
            return null;
        }

        usort($levels, static fn (ApiTokenLevel $a, ApiTokenLevel $b) => $b->value <=> $a->value);

        return $levels[0];
    }
}
