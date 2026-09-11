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

namespace App\Tests\Security\OAuth;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use ApiPlatform\Symfony\Bundle\Test\Client;
use App\Entity\UserSystem\ApiTokenLevel;
use App\Services\OAuth\OAuthClientGrantPreferenceManager;
use Doctrine\ORM\EntityManagerInterface;
use League\Bundle\OAuth2ServerBundle\Entity\User as LeagueUser;
use League\Bundle\OAuth2ServerBundle\Manager\ClientManagerInterface;
use League\Bundle\OAuth2ServerBundle\Model\AccessToken;
use League\Bundle\OAuth2ServerBundle\Model\Client as OAuthClientModel;
use League\Bundle\OAuth2ServerBundle\Model\RefreshToken;
use League\Bundle\OAuth2ServerBundle\ValueObject\Grant;
use League\Bundle\OAuth2ServerBundle\ValueObject\RedirectUri;
use League\Bundle\OAuth2ServerBundle\ValueObject\Scope;
use League\OAuth2\Server\AuthorizationServer;
use Nyholm\Psr7\Factory\Psr17Factory;

/**
 * Covers the two things that only take effect at token-issuance time, below the consent screen already
 * covered by App\Tests\EventListener\OAuth\AuthorizationConsentListenerTest:
 *  - App\EventListener\OAuth\OAuthScopeResolveListener actually narrowing the granted scope to whatever
 *    App\Services\OAuth\OAuthClientGrantPreferenceManager has on file for the (user, client) pair, rejecting
 *    the grant outright rather than falling back to the client's full requested scope when no preference
 *    is on file, and
 *  - App\Doctrine\OAuth\RefreshTokenTtlRepositoryDecorator actually applying a configured refresh token TTL.
 *
 * Drives AuthorizationServer directly (bypassing the consent-screen UI, like
 * App\Tests\Security\OAuth\OAuthBearerAuthenticationTest already does), since both mechanisms are keyed off
 * a preference row that would normally be written by the consent screen - here it's written directly to
 * isolate these two mechanisms from the HTML form/CSRF plumbing already covered elsewhere.
 */
final class OAuthScopeNarrowingAndTtlTest extends ApiTestCase
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
     * @param string[] $scopes
     * @return array<string, mixed>
     */
    private function issueTokenForScopes(Client $client, OAuthClientModel $oauthClient, string $redirectUri, array $scopes): array
    {
        $authServer = $client->getContainer()->get(AuthorizationServer::class);
        $psr17 = new Psr17Factory();

        [$verifier, $challenge] = $this->generatePkcePair();

        $authorizeRequest = $psr17->createServerRequest('GET', 'https://part-db.test/oauth/authorize?'.http_build_query([
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

        $tokenRequest = $psr17->createServerRequest('POST', 'https://part-db.test/oauth/token')
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

    private function findAccessTokenForClient(Client $httpClient, OAuthClientModel $oauthClient): AccessToken
    {
        $entityManager = $httpClient->getContainer()->get(EntityManagerInterface::class);

        /** @var ?AccessToken $accessToken */
        $accessToken = $entityManager->createQueryBuilder()
            ->select('at')
            ->from(AccessToken::class, 'at')
            ->where('at.client = :client')
            ->setParameter('client', $oauthClient->getIdentifier())
            ->getQuery()
            ->getOneOrNullResult();

        self::assertNotNull($accessToken, 'Expected exactly one access token to have been issued for this client.');

        return $accessToken;
    }

    private function findRefreshTokenForClient(Client $httpClient, OAuthClientModel $oauthClient): RefreshToken
    {
        $entityManager = $httpClient->getContainer()->get(EntityManagerInterface::class);

        /** @var ?RefreshToken $refreshToken */
        $refreshToken = $entityManager->createQueryBuilder()
            ->select('rt')
            ->from(RefreshToken::class, 'rt')
            ->join('rt.accessToken', 'at')
            ->where('at.client = :client')
            ->setParameter('client', $oauthClient->getIdentifier())
            ->getQuery()
            ->getOneOrNullResult();

        self::assertNotNull($refreshToken, 'Expected exactly one refresh token to have been issued for this client.');

        return $refreshToken;
    }

    public function testScopePreferenceNarrowsGrantedAccessToken(): void
    {
        $httpClient = static::createClient();
        $redirectUri = 'https://client.example.invalid/callback';
        $oauthClient = $this->createTestClient($httpClient, $redirectUri, ['read_only', 'edit', 'full']);

        $httpClient->getContainer()->get(OAuthClientGrantPreferenceManager::class)->save(
            'admin',
            $oauthClient->getIdentifier(),
            ApiTokenLevel::EDIT,
            null,
            null,
        );

        $data = $this->issueTokenForScopes($httpClient, $oauthClient, $redirectUri, ['read_only', 'edit', 'full']);
        self::assertArrayHasKey('access_token', $data);

        $accessToken = $this->findAccessTokenForClient($httpClient, $oauthClient);
        $scopeNames = array_map(static fn (Scope $s) => (string) $s, $accessToken->getScopes());
        sort($scopeNames);

        self::assertSame(['edit', 'read_only'], $scopeNames);
    }

    public function testNoPreferenceIsRejectedRatherThanGrantingFullScope(): void
    {
        $httpClient = static::createClient();
        $redirectUri = 'https://client.example.invalid/callback';
        $oauthClient = $this->createTestClient($httpClient, $redirectUri, ['read_only', 'edit']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('no grant preference found');

        $this->issueTokenForScopes($httpClient, $oauthClient, $redirectUri, ['read_only', 'edit']);
    }

    public function testTtlPreferenceOverridesRefreshTokenExpiry(): void
    {
        $httpClient = static::createClient();
        $redirectUri = 'https://client.example.invalid/callback';
        $oauthClient = $this->createTestClient($httpClient, $redirectUri, ['read_only']);

        $httpClient->getContainer()->get(OAuthClientGrantPreferenceManager::class)->save(
            'admin',
            $oauthClient->getIdentifier(),
            ApiTokenLevel::READ_ONLY,
            null,
            1,
        );

        $this->issueTokenForScopes($httpClient, $oauthClient, $redirectUri, ['read_only']);

        $refreshToken = $this->findRefreshTokenForClient($httpClient, $oauthClient);

        $expectedExpiry = new \DateTimeImmutable('+1 day');
        self::assertEqualsWithDelta($expectedExpiry->getTimestamp(), $refreshToken->getExpiry()->getTimestamp(), 120);
    }

    public function testDefaultRefreshTokenTtlAppliesWhenPreferenceHasNoTtlOverride(): void
    {
        $httpClient = static::createClient();
        $redirectUri = 'https://client.example.invalid/callback';
        $oauthClient = $this->createTestClient($httpClient, $redirectUri, ['read_only']);

        $httpClient->getContainer()->get(OAuthClientGrantPreferenceManager::class)->save(
            'admin',
            $oauthClient->getIdentifier(),
            ApiTokenLevel::READ_ONLY,
            null,
            null,
        );

        $this->issueTokenForScopes($httpClient, $oauthClient, $redirectUri, ['read_only']);

        $refreshToken = $this->findRefreshTokenForClient($httpClient, $oauthClient);

        // league_oauth2_server.yaml's authorization_server.refresh_token_ttl is P30D.
        $expectedExpiry = new \DateTimeImmutable('+30 days');
        self::assertEqualsWithDelta($expectedExpiry->getTimestamp(), $refreshToken->getExpiry()->getTimestamp(), 120);
    }
}
