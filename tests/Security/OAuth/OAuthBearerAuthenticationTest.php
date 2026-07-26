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

namespace App\Tests\Security\OAuth;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use ApiPlatform\Symfony\Bundle\Test\Client;
use League\Bundle\OAuth2ServerBundle\Entity\User as LeagueUser;
use League\Bundle\OAuth2ServerBundle\Manager\ClientManagerInterface;
use League\Bundle\OAuth2ServerBundle\Model\Client as OAuthClientModel;
use League\Bundle\OAuth2ServerBundle\ValueObject\Grant;
use League\Bundle\OAuth2ServerBundle\ValueObject\RedirectUri;
use League\Bundle\OAuth2ServerBundle\ValueObject\Scope;
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\Exception\OAuthServerException;
use Nyholm\Psr7\Factory\Psr17Factory;
use Symfony\Component\HttpFoundation\Response;

/**
 * Drives the OAuth2 authorization code + PKCE flow end to end (against league/oauth2-server-bundle's
 * own Doctrine persistence - see config/packages/league_oauth2_server.yaml), then makes *real* HTTP
 * requests bearing the issued token against protected API routes. This specifically exercises the two
 * pieces of custom code this design needs on top of the bundle: App\Security\OAuth\OAuthBearerAuthenticator
 * (routing OAuth2 bearer tokens to the bundle's ResourceServer without colliding with
 * App\Security\ApiTokenAuthenticator) and the App\Services\UserSystem\VoterHelper fix that makes an
 * OAuth2-issued token's scope act as a permission ceiling, exactly like a Personal Access Token's level.
 *
 * All container/service access happens through the single kernel that static::createClient() boots for
 * the test (via $client->getContainer()) - mixing that with a separately-booted kernel leads to
 * inconsistent state (e.g. entities persisted through one EntityManager not visible to the other).
 */
final class OAuthBearerAuthenticationTest extends ApiTestCase
{
    private function createTestClient(Client $client, string $redirectUri, array $scopes): OAuthClientModel
    {
        $oauthClient = new OAuthClientModel('Test Client', 'test-client-'.bin2hex(random_bytes(8)), null);
        $oauthClient->setRedirectUris(new RedirectUri($redirectUri));
        $oauthClient->setGrants(new Grant('authorization_code'), new Grant('refresh_token'));
        $oauthClient->setScopes(...array_map(static fn (string $s) => new Scope($s), $scopes));
        $oauthClient->setActive(true);

        $client->getContainer()->get(ClientManagerInterface::class)->save($oauthClient);

        return $oauthClient;
    }

    /**
     * @return array{0: string, 1: string} [code_verifier, code_challenge]
     */
    private function generatePkcePair(): array
    {
        $verifier = rtrim(strtr(base64_encode(random_bytes(40)), '+/', '-_'), '=');
        $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

        return [$verifier, $challenge];
    }

    /**
     * Drives AuthorizationServer directly (bypassing the not-yet-built consent-screen UI, which will
     * sit in front of the bundle's own /authorize route) to get a real access/refresh token pair for
     * the given scopes, exactly as if a user had approved that grant.
     *
     * @param string[] $scopes
     * @return array<string, mixed>
     */
    private function issueTokenForScopes(Client $client, OAuthClientModel $oauthClient, string $redirectUri, array $scopes): array
    {
        $authServer = $client->getContainer()->get(AuthorizationServer::class);
        $psr17 = new Psr17Factory();

        [$verifier, $challenge] = $this->generatePkcePair();

        $authorizeRequest = $psr17->createServerRequest('GET', 'https://part-db.test/authorize?'.http_build_query([
            'response_type' => 'code',
            'client_id' => $oauthClient->getIdentifier(),
            'redirect_uri' => $redirectUri,
            'code_challenge' => $challenge,
            'code_challenge_method' => 'S256',
            'scope' => implode(' ', $scopes),
        ]));

        $authRequest = $authServer->validateAuthorizationRequest($authorizeRequest);
        $leagueUser = new LeagueUser();
        $leagueUser->setIdentifier('admin');
        $authRequest->setUser($leagueUser);
        $authRequest->setAuthorizationApproved(true);

        $redirectResponse = $authServer->completeAuthorizationRequest($authRequest, $psr17->createResponse());
        parse_str((string) parse_url($redirectResponse->getHeaderLine('Location'), PHP_URL_QUERY), $query);
        self::assertArrayHasKey('code', $query, 'Expected an authorization code in the redirect.');

        $tokenRequest = $psr17->createServerRequest('POST', 'https://part-db.test/token')
            ->withParsedBody([
                'grant_type' => 'authorization_code',
                'client_id' => $oauthClient->getIdentifier(),
                'redirect_uri' => $redirectUri,
                'code' => $query['code'],
                'code_verifier' => $verifier,
            ]);

        $tokenResponse = $authServer->respondToAccessTokenRequest($tokenRequest, $psr17->createResponse());

        return json_decode((string) $tokenResponse->getBody(), true, flags: JSON_THROW_ON_ERROR);
    }

    private function bearer(string $accessToken): array
    {
        return ['headers' => ['authorization' => 'Bearer '.$accessToken]];
    }

    public function testReadOnlyScopedTokenCanReadButNotEdit(): void
    {
        $httpClient = static::createClient();
        $redirectUri = 'https://client.example.invalid/callback';
        $oauthClient = $this->createTestClient($httpClient, $redirectUri, ['read_only']);
        $data = $this->issueTokenForScopes($httpClient, $oauthClient, $redirectUri, ['read_only']);

        self::assertArrayHasKey('access_token', $data);
        //A real JWT, not one of our own "tcp_..." Personal Access Tokens.
        self::assertStringContainsString('.', $data['access_token']);

        $httpClient->request('GET', '/api/parts', $this->bearer($data['access_token']));
        self::assertResponseIsSuccessful();

        $httpClient->request('POST', '/api/footprints', array_merge(['json' => ['name' => 'oauth read_only test']], $this->bearer($data['access_token'])));
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testFullScopedTokenCanEdit(): void
    {
        $httpClient = static::createClient();
        $redirectUri = 'https://client.example.invalid/callback';
        //A real "full"-level PAT gets the cumulative ROLE_API_READ_ONLY/EDIT/ADMIN/FULL roles (see
        //App\Entity\UserSystem\ApiTokenLevel::getAdditionalRoles()); OAuth2 scopes are not cumulative,
        //so the client/token must request every level it needs explicitly.
        $oauthClient = $this->createTestClient($httpClient, $redirectUri, ['read_only', 'edit', 'admin', 'full']);
        $data = $this->issueTokenForScopes($httpClient, $oauthClient, $redirectUri, ['read_only', 'edit', 'admin', 'full']);

        $httpClient->request('GET', '/api/parts', $this->bearer($data['access_token']));
        self::assertResponseIsSuccessful();

        $httpClient->request('POST', '/api/footprints', array_merge(['json' => ['name' => 'oauth full test']], $this->bearer($data['access_token'])));
        self::assertResponseIsSuccessful();
    }

    public function testInvalidPkceVerifierIsRejected(): void
    {
        $httpClient = static::createClient();
        $redirectUri = 'https://client.example.invalid/callback';
        $oauthClient = $this->createTestClient($httpClient, $redirectUri, ['read_only']);

        $authServer = $httpClient->getContainer()->get(AuthorizationServer::class);
        $psr17 = new Psr17Factory();

        [, $challenge] = $this->generatePkcePair();

        $authorizeRequest = $psr17->createServerRequest('GET', 'https://part-db.test/authorize?'.http_build_query([
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

        $tokenRequest = $psr17->createServerRequest('POST', 'https://part-db.test/token')
            ->withParsedBody([
                'grant_type' => 'authorization_code',
                'client_id' => $oauthClient->getIdentifier(),
                'redirect_uri' => $redirectUri,
                'code' => $query['code'],
                //Wrong verifier - does not match the code_challenge used above.
                'code_verifier' => str_repeat('a', 43),
            ]);

        $this->expectException(OAuthServerException::class);
        $authServer->respondToAccessTokenRequest($tokenRequest, $psr17->createResponse());
    }

    public function testRefreshTokenRotationIssuesNewAccessTokenAndRevokesOld(): void
    {
        $httpClient = static::createClient();
        $redirectUri = 'https://client.example.invalid/callback';
        $oauthClient = $this->createTestClient($httpClient, $redirectUri, ['read_only']);
        $first = $this->issueTokenForScopes($httpClient, $oauthClient, $redirectUri, ['read_only']);

        $authServer = $httpClient->getContainer()->get(AuthorizationServer::class);
        $psr17 = new Psr17Factory();

        $refreshRequest = $psr17->createServerRequest('POST', 'https://part-db.test/token')
            ->withParsedBody([
                'grant_type' => 'refresh_token',
                'client_id' => $oauthClient->getIdentifier(),
                'refresh_token' => $first['refresh_token'],
            ]);

        $refreshResponse = $authServer->respondToAccessTokenRequest($refreshRequest, $psr17->createResponse());
        $second = json_decode((string) $refreshResponse->getBody(), true, flags: JSON_THROW_ON_ERROR);

        self::assertNotSame($first['access_token'], $second['access_token']);

        //The old access token must now be rejected by the resource server (revoked on rotation).
        $httpClient->request('GET', '/api/parts', $this->bearer($first['access_token']));
        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);

        $httpClient->request('GET', '/api/parts', $this->bearer($second['access_token']));
        self::assertResponseIsSuccessful();
    }
}
