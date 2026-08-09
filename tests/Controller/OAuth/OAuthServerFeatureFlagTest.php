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

namespace App\Tests\Controller\OAuth;

use App\Entity\UserSystem\ApiTokenLevel;
use App\Entity\UserSystem\User;
use App\Services\OAuth\OAuthClientGrantPreferenceManager;
use Doctrine\ORM\EntityManagerInterface;
use League\Bundle\OAuth2ServerBundle\Entity\User as LeagueUser;
use League\Bundle\OAuth2ServerBundle\Manager\ClientManagerInterface;
use League\Bundle\OAuth2ServerBundle\Model\Client as OAuthClientModel;
use League\Bundle\OAuth2ServerBundle\ValueObject\Grant;
use League\Bundle\OAuth2ServerBundle\ValueObject\RedirectUri;
use League\Bundle\OAuth2ServerBundle\ValueObject\Scope;
use League\OAuth2\Server\AuthorizationServer;
use Nyholm\Psr7\Factory\Psr17Factory;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Covers OAUTH_SERVER_ENABLED / OAUTH_DCR_ENABLED (config/parameters.yaml, disabled by default - see
 * .env). .env.test overrides both to enabled so the rest of the OAuth test suite keeps exercising the
 * full feature; this file specifically flips them back off (via $_SERVER/$_ENV, which
 * Symfony\Component\DependencyInjection\Container::getEnv() and routing's env() condition function both
 * resolve dynamically per request - no cached/dumped container value takes precedence) to verify the
 * *disabled* paths, restoring them in a finally block so no other test observes the override.
 *
 * Unauthenticated requests to /oauth/authorize always get a 401 from the firewall's own access_control
 * (^/oauth/authorize requires IS_AUTHENTICATED_FULLY) *before* routing ever gets a chance to 404 the disabled
 * route - so tests that need to see the true disabled-routing behavior for /oauth/authorize log in first and
 * follow redirects (the route falls through to the app's locale-redirect catch-all, then 404s).
 */
final class OAuthServerFeatureFlagTest extends WebTestCase
{
    private function withEnv(array $vars, callable $callback): mixed
    {
        $previous = [];
        foreach ($vars as $name => $value) {
            $previous[$name] = $_SERVER[$name] ?? null;
            $_SERVER[$name] = $value;
            $_ENV[$name] = $value;
        }

        try {
            return $callback();
        } finally {
            foreach ($previous as $name => $value) {
                if (null === $value) {
                    unset($_SERVER[$name], $_ENV[$name]);
                } else {
                    $_SERVER[$name] = $value;
                    $_ENV[$name] = $value;
                }
            }
        }
    }

    public function testProtocolEndpointsAreUnreachableWhenServerDisabled(): void
    {
        $this->withEnv(['OAUTH_SERVER_ENABLED' => '0', 'OAUTH_DCR_ENABLED' => '0'], function (): void {
            $httpClient = static::createClient();
            // The unmatched routes fall through to the app's locale-redirect catch-all
            // (App\Controller\RedirectController::addLocalePart()) before ultimately 404ing.
            $httpClient->followRedirects();

            // Public endpoints - no login needed to see the true routing behavior.
            $httpClient->request('POST', '/oauth/token');
            self::assertResponseStatusCodeSame(404);

            $httpClient->request('POST', '/oauth/register', ['json' => []]);
            self::assertResponseStatusCodeSame(404);

            $httpClient->request('GET', '/.well-known/oauth-authorization-server');
            self::assertResponseStatusCodeSame(404);

            $httpClient->request('GET', '/.well-known/oauth-protected-resource');
            self::assertResponseStatusCodeSame(404);

            // /oauth/authorize requires login before routing gets a chance to reject it (access_control
            // matches on path, independent of whether the route beneath it actually exists).
            $entityManager = static::getContainer()->get(EntityManagerInterface::class);
            $admin = $entityManager->getRepository(User::class)->findOneBy(['name' => 'admin']);
            self::assertInstanceOf(User::class, $admin);
            $httpClient->loginUser($admin);
            $httpClient->request('GET', '/oauth/authorize');
            self::assertResponseStatusCodeSame(404);

            $httpClient->request('GET', '/en/tools/oauth_clients');
            self::assertResponseStatusCodeSame(404);
        });
    }

    public function testDcrEndpointRequiresBothFlagsIndependentlyOfServerAdminUi(): void
    {
        $this->withEnv(['OAUTH_SERVER_ENABLED' => '1', 'OAUTH_DCR_ENABLED' => '0'], function (): void {
            $httpClient = static::createClient();
            $httpClient->followRedirects();

            // DCR specifically off...
            $httpClient->request('POST', '/oauth/register', ['json' => []]);
            self::assertResponseStatusCodeSame(404);

            // ...but the rest of the server (and the admin UI, where clients can still be registered
            // manually) keeps working.
            $entityManager = static::getContainer()->get(EntityManagerInterface::class);
            $admin = $entityManager->getRepository(User::class)->findOneBy(['name' => 'admin']);
            self::assertInstanceOf(User::class, $admin);
            $httpClient->loginUser($admin);

            $httpClient->request('GET', '/en/tools/oauth_clients');
            self::assertResponseIsSuccessful();

            $httpClient->request('GET', '/.well-known/oauth-authorization-server');
            $discovery = json_decode(
                (string) $httpClient->getResponse()->getContent(),
                true,
                flags: JSON_THROW_ON_ERROR,
            );
            self::assertArrayNotHasKey('registration_endpoint', $discovery);
        });
    }

    public function testDiscoveryAdvertisesRegistrationEndpointWhenDcrEnabled(): void
    {
        $httpClient = static::createClient();

        $httpClient->request('GET', '/.well-known/oauth-authorization-server');
        $discovery = json_decode(
            (string) $httpClient->getResponse()->getContent(),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
        self::assertArrayHasKey('registration_endpoint', $discovery);
    }

    private function createTestClient(KernelBrowser $client, string $redirectUri): OAuthClientModel
    {
        $oauthClient = new OAuthClientModel('Test Client', 'test-client-'.bin2hex(random_bytes(8)), null);
        $oauthClient->setRedirectUris(new RedirectUri($redirectUri));
        $oauthClient->setGrants(new Grant('authorization_code'), new Grant('refresh_token'));
        $oauthClient->setScopes(new Scope('read_only'));
        $oauthClient->setActive(true);

        $client->getContainer()->get(ClientManagerInterface::class)->save($oauthClient);

        return $oauthClient;
    }

    public function testOAuthBearerTokenStopsAuthenticatingWhenServerDisabled(): void
    {
        // Issue a real token while the server is enabled (the test-env default).
        $httpClient = static::createClient();
        $redirectUri = 'https://client.example.invalid/callback';
        $oauthClient = $this->createTestClient($httpClient, $redirectUri);

        // OAuthScopeResolveListener now rejects token issuance for a (user, client) pair with no
        // consent-time grant preference on file - this test isn't concerned with scope narrowing, so
        // just satisfy the precondition.
        $httpClient->getContainer()->get(OAuthClientGrantPreferenceManager::class)->save(
            'admin',
            $oauthClient->getIdentifier(),
            ApiTokenLevel::READ_ONLY,
            null,
            null,
        );

        $authServer = $httpClient->getContainer()->get(AuthorizationServer::class);
        $psr17 = new Psr17Factory();
        $verifier = rtrim(strtr(base64_encode(random_bytes(40)), '+/', '-_'), '=');
        $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

        $authorizeRequest = $psr17->createServerRequest('GET', 'https://part-db.test/oauth/authorize?'.http_build_query([
            'response_type' => 'code',
            'client_id' => $oauthClient->getIdentifier(),
            'redirect_uri' => $redirectUri,
            'code_challenge' => $challenge,
            'code_challenge_method' => 'S256',
            'scope' => 'read_only',
        ]));
        $authRequest = $authServer->validateAuthorizationRequest($authorizeRequest);
        $leagueUser = new LeagueUser();
        $leagueUser->setIdentifier('admin');
        $authRequest->setUser($leagueUser);
        $authRequest->setAuthorizationApproved(true);
        $redirectResponse = $authServer->completeAuthorizationRequest($authRequest, $psr17->createResponse());
        parse_str((string) parse_url($redirectResponse->getHeaderLine('Location'), PHP_URL_QUERY), $query);

        $tokenRequest = $psr17->createServerRequest('POST', 'https://part-db.test/oauth/token')->withParsedBody([
            'grant_type' => 'authorization_code',
            'client_id' => $oauthClient->getIdentifier(),
            'redirect_uri' => $redirectUri,
            'code' => $query['code'],
            'code_verifier' => $verifier,
        ]);
        $tokenResponse = $authServer->respondToAccessTokenRequest($tokenRequest, $psr17->createResponse());
        $data = json_decode((string) $tokenResponse->getBody(), true, flags: JSON_THROW_ON_ERROR);

        // Sanity check: the token works while the server is enabled.
        $httpClient->request('GET', '/api/parts', [], [], ['HTTP_AUTHORIZATION' => 'Bearer '.$data['access_token']]);
        self::assertResponseIsSuccessful();

        // Now disable the server and boot a *fresh* kernel/container - OAuthBearerAuthenticator's
        // "%partdb.oauth_server.enabled%"-bound constructor parameter is resolved once, when the
        // (singleton) service is first instantiated within a container's lifetime, so an already-booted
        // container/authenticator instance would keep the old value; only a fresh boot picks up the
        // override (unlike route conditions, which call env() fresh on every single request regardless of
        // container reuse). The access token itself lives in the shared test database, so it's still
        // there for this fresh container to find.
        $this->withEnv(['OAUTH_SERVER_ENABLED' => '0'], function () use ($data): void {
            static::ensureKernelShutdown();
            $freshClient = static::createClient();
            $freshClient->request('GET', '/api/parts', [], [], ['HTTP_AUTHORIZATION' => 'Bearer '.$data['access_token']]);
            self::assertResponseStatusCodeSame(401);
        });
    }
}
