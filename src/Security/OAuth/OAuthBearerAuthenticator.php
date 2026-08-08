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
use League\Bundle\OAuth2ServerBundle\Security\Exception\OAuth2AuthenticationException;
use League\Bundle\OAuth2ServerBundle\Security\Exception\OAuth2AuthenticationFailedException;
use League\Bundle\OAuth2ServerBundle\Security\Passport\Badge\ScopeBadge;
use League\Bundle\OAuth2ServerBundle\Security\User\ClientCredentialsUser;
use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\ResourceServer;
use Symfony\Bridge\PsrHttpMessage\HttpMessageFactoryInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Http\Authenticator\AuthenticatorInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

/**
 * Validates OAuth2-issued Bearer tokens (auto-provisioned API/MCP app credentials, see
 * config/packages/league_oauth2_server.yaml) against the bundle's own ResourceServer/token storage.
 *
 * This is functionally almost identical to the bundle's own
 * League\Bundle\OAuth2ServerBundle\Security\Authenticator\OAuth2Authenticator - reimplemented here
 * (rather than reused) for one reason: supports() must be mutually exclusive with
 * App\Security\ApiTokenAuthenticator. Symfony's AuthenticatorManager runs *every* authenticator whose
 * supports() matches a request, not just the first one that succeeds - so if both authenticators
 * claimed every "Authorization: Bearer ..." request, whichever one runs second would immediately
 * re-validate (and fail on) a token meant for the other, overwriting an already-successful
 * authentication with a 401. Restricting each authenticator to the token shapes it actually owns
 * (ApiTokenAuthenticator: our own "tcp_..." Personal Access Tokens; this class: everything else, i.e.
 * OAuth2-issued JWTs) avoids that collision entirely. See App\Entity\UserSystem\ApiTokenType::isRecognizedToken().
 *
 * Also refuses to authenticate anything at all while the OAuth2 server is disabled (OAUTH_SERVER_ENABLED,
 * disabled by default) - so any previously-issued OAuth2 token immediately stops working the moment the
 * server is turned off, the same way its own /oauth/authorize, /oauth/token etc. routes stop being reachable (see
 * config/routes/league_oauth2_server.yaml's route condition).
 */
class OAuthBearerAuthenticator implements AuthenticatorInterface, AuthenticationEntryPointInterface
{
    /**
     * Must match config/packages/league_oauth2_server.yaml's authorization_server.role_prefix.
     */
    private const ROLE_PREFIX = 'ROLE_API_';

    public function __construct(
        #[Autowire(service: 'league.oauth2_server.factory.psr_http')]
        private readonly HttpMessageFactoryInterface $httpMessageFactory,
        private readonly ResourceServer $resourceServer,
        #[Autowire(service: 'security.user.provider.concrete.app_user_provider')]
        private readonly UserProviderInterface $userProvider,
        private readonly OAuthClientGrantPreferenceManager $grantPreferences,
        #[Autowire('%partdb.oauth_server.enabled%')]
        private readonly bool $oauth_server_enabled,
    ) {
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
        return new Response($authException?->getMessage() ?? 'Authentication required', Response::HTTP_UNAUTHORIZED, ['WWW-Authenticate' => 'Bearer']);
    }

    public function authenticate(Request $request): Passport
    {
        try {
            $psr7Request = $this->resourceServer->validateAuthenticatedRequest($this->httpMessageFactory->createRequest($request));
        } catch (OAuthServerException $e) {
            throw OAuth2AuthenticationFailedException::create('The resource server rejected the request.', $e);
        }

        /** @var string $userIdentifier */
        $userIdentifier = $psr7Request->getAttribute('oauth_user_id', '');

        /** @var string $accessTokenId */
        $accessTokenId = $psr7Request->getAttribute('oauth_access_token_id');

        /** @var list<string> $scopes */
        $scopes = $psr7Request->getAttribute('oauth_scopes', []);

        /** @var non-empty-string $oauthClientId */
        $oauthClientId = $psr7Request->getAttribute('oauth_client_id', '');

        $userLoader = function (string $userIdentifier) use ($oauthClientId): UserInterface {
            if ($oauthClientId === $userIdentifier) {
                return new ClientCredentialsUser($oauthClientId);
            }

            return $this->userProvider->loadUserByIdentifier($userIdentifier);
        };

        $passport = new SelfValidatingPassport(new UserBadge($userIdentifier, $userLoader), [
            new ScopeBadge($scopes),
        ]);

        $passport->setAttribute('accessTokenId', $accessTokenId);
        $passport->setAttribute('oauthClientId', $oauthClientId);

        return $passport;
    }

    public function createToken(Passport $passport, string $firewallName): TokenInterface
    {
        /** @var string $accessTokenId */
        $accessTokenId = $passport->getAttribute('accessTokenId');

        /** @var ScopeBadge $scopeBadge */
        $scopeBadge = $passport->getBadge(ScopeBadge::class);

        /** @var string $oauthClientId */
        $oauthClientId = $passport->getAttribute('oauthClientId', '');

        return new OAuth2Token($passport->getUser(), $accessTokenId, $oauthClientId, $scopeBadge->getScopes(), self::ROLE_PREFIX);
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
        if ($exception instanceof OAuth2AuthenticationException) {
            return new Response($exception->getMessage(), $exception->getStatusCode(), $exception->getHeaders());
        }

        throw $exception;
    }
}
