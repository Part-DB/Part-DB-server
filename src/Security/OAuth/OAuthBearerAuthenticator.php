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

namespace App\Security\OAuth;

use App\Entity\UserSystem\ApiTokenType;
use App\Services\OAuth\OAuthClientGrantPreferenceManager;
use League\Bundle\OAuth2ServerBundle\Security\Authentication\Token\OAuth2Token;
use League\Bundle\OAuth2ServerBundle\Security\Authenticator\OAuth2Authenticator;
use League\OAuth2\Server\ResourceServer;
use Symfony\Bridge\PsrHttpMessage\HttpMessageFactoryInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\Lazy;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Http\Authenticator\AuthenticatorInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

/**
 * Validates OAuth2-issued Bearer tokens (auto-provisioned API/MCP app credentials, see
 * config/packages/league_oauth2_server.yaml) against the bundle's own ResourceServer/token storage.
 *
 * Wraps (rather than extends, since it's final) the bundle's own
 * League\Bundle\OAuth2ServerBundle\Security\Authenticator\OAuth2Authenticator and delegates start(),
 * authenticate(), createToken() and onAuthenticationFailure() to it - those are identical to the
 * bundle's behavior. Only supports() and onAuthenticationSuccess() are genuinely different:
 *
 * supports() must be mutually exclusive with App\Security\ApiTokenAuthenticator. Symfony's
 * AuthenticatorManager runs *every* authenticator whose supports() matches a request, not just the
 * first one that succeeds - so if both authenticators claimed every "Authorization: Bearer ..."
 * request, whichever one runs second would immediately re-validate (and fail on) a token meant for the
 * other, overwriting an already-successful authentication with a 401. Restricting each authenticator to
 * the token shapes it actually owns (ApiTokenAuthenticator: our own "tcp_..." Personal Access Tokens;
 * this class: everything else, i.e. OAuth2-issued JWTs) avoids that collision entirely. See
 * App\Entity\UserSystem\ApiTokenType::isRecognizedToken().
 *
 * Also refuses to authenticate anything at all while the OAuth2 server is disabled (OAUTH_SERVER_ENABLED,
 * disabled by default) - so any previously-issued OAuth2 token immediately stops working the moment the
 * server is turned off, the same way its own /oauth/authorize, /oauth/token etc. routes stop being reachable (see
 * config/routes/league_oauth2_server.yaml's route condition).
 *
 * onAuthenticationSuccess() additionally records per-user/client grant preferences, which the bundle's
 * version (a no-op) knows nothing about.
 *
 * ResourceServer is injected #[Lazy]: its constructor eagerly reads and parses uploads/oauth_public.key
 * (League\OAuth2\Server\CryptKey throws if that file doesn't exist yet, e.g. before
 * partdb:oauth:generate-keys has ever been run). Since this authenticator - and therefore its
 * ResourceServer dependency - gets instantiated for every request that reaches the firewall regardless
 * of whether OAuth2 is even enabled or the request carries a Bearer token, an eager ResourceServer would
 * make the entire app unusable without a keypair already in place. The lazy proxy defers that read until
 * authenticate()/start() actually touch it, i.e. only once supports() has already confirmed this is a
 * real (enabled) OAuth2 bearer-token request.
 */
class OAuthBearerAuthenticator implements AuthenticatorInterface, AuthenticationEntryPointInterface
{
    /**
     * Must match config/packages/league_oauth2_server.yaml's authorization_server.role_prefix.
     */
    private const ROLE_PREFIX = 'ROLE_API_';

    private readonly OAuth2Authenticator $inner;

    public function __construct(
        #[Autowire(service: 'league.oauth2_server.factory.psr_http')]
        HttpMessageFactoryInterface $httpMessageFactory,
        #[Lazy]
        ResourceServer $resourceServer,
        #[Autowire(service: 'security.user.provider.concrete.app_user_provider')]
        UserProviderInterface $userProvider,
        private readonly OAuthClientGrantPreferenceManager $grantPreferences,
        #[Autowire('%partdb.oauth_server.enabled%')]
        private readonly bool $oauth_server_enabled,
    ) {
        $this->inner = new OAuth2Authenticator($httpMessageFactory, $resourceServer, $userProvider, self::ROLE_PREFIX);
    }

    public function supports(Request $request): bool
    {
        if (!$this->oauth_server_enabled) {
            return false;
        }

        $header = $request->headers->get('Authorization', '');
        if (!str_starts_with((string) $header, 'Bearer ')) {
            return false;
        }

        return !ApiTokenType::isRecognizedToken(substr($header, 7));
    }

    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        return $this->inner->start($request, $authException);
    }

    public function authenticate(Request $request): Passport
    {
        return $this->inner->authenticate($request);
    }

    public function createToken(Passport $passport, string $firewallName): TokenInterface
    {
        return $this->inner->createToken($passport, $firewallName);
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        if (!$token instanceof OAuth2Token) {
            return null;
        }

        $userIdentifier = $token->getUserIdentifier();
        $clientId = $token->getOAuthClientId();

        // Skip the (currently unreachable, since enable_client_credentials_grant is false)
        // ClientCredentialsUser case - see authenticate()'s $userLoader - there's no real end user to
        // record a per-user preference/last-used timestamp for.
        if ($userIdentifier !== $clientId) {
            $this->grantPreferences->recordUsage($userIdentifier, $clientId, $token->getScopes());
        }

        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        return $this->inner->onAuthenticationFailure($request, $exception);
    }
}
