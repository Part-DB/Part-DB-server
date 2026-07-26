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

use App\Entity\UserSystem\ApiToken;
use App\Entity\UserSystem\ApiTokenLevel;
use App\Entity\UserSystem\ApiTokenType;
use App\Entity\UserSystem\OAuth\OAuthClient;
use App\Entity\UserSystem\User;
use App\Security\OAuth\Entity\OAuthUserEntity;
use Doctrine\ORM\EntityManagerInterface;
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\Exception\OAuthServerException;
use Nyholm\Psr7\Factory\Psr17Factory;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Drives League\OAuth2\Server\AuthorizationServer directly (no HTTP layer yet - that's Phase 2/3 of the
 * OAuth auto-provisioning work) to verify the foundations laid in Phase 1: our Doctrine-backed
 * repositories, the opaque (non-JWT) access token response, and - most importantly - that the token
 * handed back to the client round-trips as a real App\Entity\UserSystem\ApiToken row, findable exactly
 * the way App\Security\ApiTokenAuthenticator finds any other token.
 */
final class AuthorizationServerTest extends KernelTestCase
{
    private AuthorizationServer $authServer;
    private EntityManagerInterface $entityManager;
    private Psr17Factory $psr17Factory;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->authServer = self::getContainer()->get(AuthorizationServer::class);
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->psr17Factory = new Psr17Factory();
    }

    private function createTestClient(string $redirectUri): OAuthClient
    {
        $client = new OAuthClient(
            'test-client-'.bin2hex(random_bytes(8)),
            'Test Client',
            [$redirectUri],
            bin2hex(random_bytes(16)),
        );
        $this->entityManager->persist($client);
        $this->entityManager->flush();

        return $client;
    }

    private function getAdminUser(): User
    {
        $user = $this->entityManager->getRepository(User::class)->findOneBy(['name' => 'admin']);
        self::assertInstanceOf(User::class, $user);

        return $user;
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
     * Runs the full authorize -> approve -> token exchange flow and returns the decoded token response.
     *
     * @return array<string, mixed>
     */
    private function runAuthorizationCodeFlow(OAuthClient $client, User $user, string $redirectUri, string $scope): array
    {
        [$verifier, $challenge] = $this->generatePkcePair();

        $authorizeRequest = $this->psr17Factory->createServerRequest('GET', 'https://part-db.test/oauth/authorize?'.http_build_query([
            'response_type' => 'code',
            'client_id' => $client->getIdentifier(),
            'redirect_uri' => $redirectUri,
            'code_challenge' => $challenge,
            'code_challenge_method' => 'S256',
            'scope' => $scope,
        ]));

        $authRequest = $this->authServer->validateAuthorizationRequest($authorizeRequest);
        $authRequest->setUser(OAuthUserEntity::fromUser($user));
        $authRequest->setAuthorizationApproved(true);

        $redirectResponse = $this->authServer->completeAuthorizationRequest($authRequest, $this->psr17Factory->createResponse());
        $location = $redirectResponse->getHeaderLine('Location');
        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);
        self::assertArrayHasKey('code', $query, 'Expected an authorization code in the redirect: '.$location);

        $tokenRequest = $this->psr17Factory->createServerRequest('POST', 'https://part-db.test/oauth/token')
            ->withParsedBody([
                'grant_type' => 'authorization_code',
                'client_id' => $client->getIdentifier(),
                'redirect_uri' => $redirectUri,
                'code' => $query['code'],
                'code_verifier' => $verifier,
            ]);

        $tokenResponse = $this->authServer->respondToAccessTokenRequest($tokenRequest, $this->psr17Factory->createResponse());
        $body = (string) $tokenResponse->getBody();

        return json_decode($body, true, flags: JSON_THROW_ON_ERROR);
    }

    public function testAuthorizationCodeFlowIssuesRealApiToken(): void
    {
        $redirectUri = 'https://client.example.invalid/callback';
        $client = $this->createTestClient($redirectUri);
        $user = $this->getAdminUser();

        $data = $this->runAuthorizationCodeFlow($client, $user, $redirectUri, 'edit');

        self::assertSame('Bearer', $data['token_type']);
        self::assertArrayHasKey('access_token', $data);
        self::assertArrayHasKey('refresh_token', $data);
        //The access token must NOT look like a JWT (no dots) - see OpaqueBearerTokenResponse.
        self::assertStringStartsWith(ApiTokenType::OAUTH_ACCESS_TOKEN->getTokenPrefix(), $data['access_token']);
        self::assertStringNotContainsString('.', $data['access_token']);

        $this->entityManager->clear();
        $apiToken = $this->entityManager->getRepository(ApiToken::class)->findOneBy(['token' => $data['access_token']]);

        self::assertInstanceOf(ApiToken::class, $apiToken);
        self::assertTrue($apiToken->isValid());
        self::assertSame(ApiTokenType::OAUTH_ACCESS_TOKEN, $apiToken->getTokenType());
        self::assertSame(ApiTokenLevel::EDIT, $apiToken->getLevel());
        self::assertSame($user->getID(), $apiToken->getUser()?->getID());
        self::assertSame($client->getId(), $apiToken->getOauthClient()?->getId());
    }

    public function testInvalidPkceVerifierIsRejected(): void
    {
        $redirectUri = 'https://client.example.invalid/callback';
        $client = $this->createTestClient($redirectUri);
        $user = $this->getAdminUser();

        [, $challenge] = $this->generatePkcePair();

        $authorizeRequest = $this->psr17Factory->createServerRequest('GET', 'https://part-db.test/oauth/authorize?'.http_build_query([
            'response_type' => 'code',
            'client_id' => $client->getIdentifier(),
            'redirect_uri' => $redirectUri,
            'code_challenge' => $challenge,
            'code_challenge_method' => 'S256',
        ]));

        $authRequest = $this->authServer->validateAuthorizationRequest($authorizeRequest);
        $authRequest->setUser(OAuthUserEntity::fromUser($user));
        $authRequest->setAuthorizationApproved(true);

        $redirectResponse = $this->authServer->completeAuthorizationRequest($authRequest, $this->psr17Factory->createResponse());
        parse_str((string) parse_url($redirectResponse->getHeaderLine('Location'), PHP_URL_QUERY), $query);

        $tokenRequest = $this->psr17Factory->createServerRequest('POST', 'https://part-db.test/oauth/token')
            ->withParsedBody([
                'grant_type' => 'authorization_code',
                'client_id' => $client->getIdentifier(),
                'redirect_uri' => $redirectUri,
                'code' => $query['code'],
                //Wrong verifier - does not match the code_challenge used above.
                'code_verifier' => str_repeat('a', 43),
            ]);

        $this->expectException(OAuthServerException::class);
        $this->authServer->respondToAccessTokenRequest($tokenRequest, $this->psr17Factory->createResponse());
    }

    public function testRefreshTokenRotationIssuesNewApiTokenAndRevokesOld(): void
    {
        $redirectUri = 'https://client.example.invalid/callback';
        $client = $this->createTestClient($redirectUri);
        $user = $this->getAdminUser();

        $first = $this->runAuthorizationCodeFlow($client, $user, $redirectUri, 'read_only');
        $originalAccessToken = $first['access_token'];

        $refreshRequest = $this->psr17Factory->createServerRequest('POST', 'https://part-db.test/oauth/token')
            ->withParsedBody([
                'grant_type' => 'refresh_token',
                'client_id' => $client->getIdentifier(),
                'refresh_token' => $first['refresh_token'],
            ]);

        $refreshResponse = $this->authServer->respondToAccessTokenRequest($refreshRequest, $this->psr17Factory->createResponse());
        $second = json_decode((string) $refreshResponse->getBody(), true, flags: JSON_THROW_ON_ERROR);

        self::assertNotSame($originalAccessToken, $second['access_token']);

        $this->entityManager->clear();

        //The old access token must have been revoked (deleted) as part of rotation.
        $oldToken = $this->entityManager->getRepository(ApiToken::class)->findOneBy(['token' => $originalAccessToken]);
        self::assertNull($oldToken, 'The original access token should have been revoked on refresh.');

        $newToken = $this->entityManager->getRepository(ApiToken::class)->findOneBy(['token' => $second['access_token']]);
        self::assertInstanceOf(ApiToken::class, $newToken);
        self::assertSame(ApiTokenLevel::READ_ONLY, $newToken->getLevel());

        //Reusing the now-rotated-out refresh token must fail and revoke the whole family.
        $reuseRequest = $this->psr17Factory->createServerRequest('POST', 'https://part-db.test/oauth/token')
            ->withParsedBody([
                'grant_type' => 'refresh_token',
                'client_id' => $client->getIdentifier(),
                'refresh_token' => $first['refresh_token'],
            ]);

        try {
            $this->authServer->respondToAccessTokenRequest($reuseRequest, $this->psr17Factory->createResponse());
            self::fail('Expected reuse of a rotated-out refresh token to be rejected.');
        } catch (OAuthServerException) {
            //Expected.
        }

        $this->entityManager->clear();
        $newTokenAfterReuse = $this->entityManager->getRepository(ApiToken::class)->findOneBy(['token' => $second['access_token']]);
        self::assertNull($newTokenAfterReuse, 'Reuse of a revoked refresh token should revoke the whole token family.');
    }
}
