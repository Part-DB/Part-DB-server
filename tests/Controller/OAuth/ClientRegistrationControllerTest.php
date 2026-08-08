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

use League\Bundle\OAuth2ServerBundle\Manager\ClientManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\RateLimiter\RateLimiterFactory;

/**
 * Drives the real unauthenticated POST /oauth/register HTTP flow
 * (App\Controller\OAuth\ClientRegistrationController).
 */
final class ClientRegistrationControllerTest extends WebTestCase
{
    public function testMinimalRegistrationUsesDefaults(): void
    {
        $httpClient = static::createClient();
        $httpClient->jsonRequest('POST', '/oauth/register', [
            'redirect_uris' => ['https://client.example.invalid/callback'],
        ]);

        self::assertResponseStatusCodeSame(201);
        $data = json_decode((string) $httpClient->getResponse()->getContent(), true);

        self::assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $data['client_id']);
        self::assertIsInt($data['client_id_issued_at']);
        self::assertSame(['https://client.example.invalid/callback'], $data['redirect_uris']);
        self::assertSame(['authorization_code', 'refresh_token'], $data['grant_types']);
        self::assertSame(['code'], $data['response_types']);
        self::assertSame('none', $data['token_endpoint_auth_method']);
        self::assertSame('read_only', $data['scope']);

        $client = $httpClient->getContainer()->get(ClientManagerInterface::class)->find($data['client_id']);
        self::assertNotNull($client);
        self::assertNull($client->getSecret());
        self::assertTrue($client->isActive());
    }

    public function testFullRegistrationHonoursRequestedFields(): void
    {
        $httpClient = static::createClient();
        $httpClient->jsonRequest('POST', '/oauth/register', [
            'redirect_uris' => ['https://client.example.invalid/callback', 'https://client.example.invalid/callback2'],
            'client_name' => 'My MCP Client',
            'grant_types' => ['authorization_code'],
            'response_types' => ['code'],
            'token_endpoint_auth_method' => 'none',
            'scope' => 'read_only edit',
        ]);

        self::assertResponseStatusCodeSame(201);
        $data = json_decode((string) $httpClient->getResponse()->getContent(), true);

        self::assertSame('My MCP Client', $data['client_name']);
        self::assertSame(['authorization_code'], $data['grant_types']);
        self::assertSame('read_only edit', $data['scope']);

        $client = $httpClient->getContainer()->get(ClientManagerInterface::class)->find($data['client_id']);
        self::assertNotNull($client);
        self::assertSame('My MCP Client', $client->getName());
        self::assertCount(2, $client->getRedirectUris());
        self::assertCount(2, $client->getScopes());
    }

    /**
     * Self-registration is unattended (no administrator reviews it before the client exists), so it must
     * not be able to reach for "admin"/"full" scope on its own - only a manually-registered client can.
     */
    public function testElevatedScopeIsRejected(): void
    {
        $httpClient = static::createClient();
        $httpClient->jsonRequest('POST', '/oauth/register', [
            'redirect_uris' => ['https://client.example.invalid/callback'],
            'scope' => 'edit admin',
        ]);

        self::assertResponseStatusCodeSame(400);
        $data = json_decode((string) $httpClient->getResponse()->getContent(), true);
        self::assertSame('invalid_scope', $data['error']);
    }

    public function testFullScopeIsRejected(): void
    {
        $httpClient = static::createClient();
        $httpClient->jsonRequest('POST', '/oauth/register', [
            'redirect_uris' => ['https://client.example.invalid/callback'],
            'scope' => 'full',
        ]);

        self::assertResponseStatusCodeSame(400);
        $data = json_decode((string) $httpClient->getResponse()->getContent(), true);
        self::assertSame('invalid_scope', $data['error']);
    }

    public function testLoopbackHttpRedirectUriIsAllowed(): void
    {
        $httpClient = static::createClient();
        $httpClient->jsonRequest('POST', '/oauth/register', [
            'redirect_uris' => ['http://127.0.0.1:51234/callback'],
        ]);

        self::assertResponseStatusCodeSame(201);
    }

    public function testPrivateUseSchemeRedirectUriIsAllowed(): void
    {
        $httpClient = static::createClient();
        $httpClient->jsonRequest('POST', '/oauth/register', [
            'redirect_uris' => ['com.example.myapp:/callback'],
        ]);

        self::assertResponseStatusCodeSame(201);
    }

    public function testNonLoopbackHttpRedirectUriIsRejected(): void
    {
        $httpClient = static::createClient();
        $httpClient->jsonRequest('POST', '/oauth/register', [
            'redirect_uris' => ['http://evil.example.invalid/callback'],
        ]);

        self::assertResponseStatusCodeSame(400);
        $data = json_decode((string) $httpClient->getResponse()->getContent(), true);
        self::assertSame('invalid_redirect_uri', $data['error']);
    }

    public function testRedirectUriWithFragmentIsRejected(): void
    {
        $httpClient = static::createClient();
        $httpClient->jsonRequest('POST', '/oauth/register', [
            'redirect_uris' => ['https://client.example.invalid/callback#fragment'],
        ]);

        self::assertResponseStatusCodeSame(400);
    }

    public function testMissingRedirectUrisIsRejected(): void
    {
        $httpClient = static::createClient();
        $httpClient->jsonRequest('POST', '/oauth/register', []);

        self::assertResponseStatusCodeSame(400);
        $data = json_decode((string) $httpClient->getResponse()->getContent(), true);
        self::assertSame('invalid_redirect_uri', $data['error']);
    }

    public function testConfidentialAuthMethodIsRejected(): void
    {
        $httpClient = static::createClient();
        $httpClient->jsonRequest('POST', '/oauth/register', [
            'redirect_uris' => ['https://client.example.invalid/callback'],
            'token_endpoint_auth_method' => 'client_secret_basic',
        ]);

        self::assertResponseStatusCodeSame(400);
        $data = json_decode((string) $httpClient->getResponse()->getContent(), true);
        self::assertSame('invalid_client_metadata', $data['error']);
    }

    public function testUnsupportedGrantTypeIsRejected(): void
    {
        $httpClient = static::createClient();
        $httpClient->jsonRequest('POST', '/oauth/register', [
            'redirect_uris' => ['https://client.example.invalid/callback'],
            'grant_types' => ['client_credentials'],
        ]);

        self::assertResponseStatusCodeSame(400);
    }

    public function testUnsupportedResponseTypeIsRejected(): void
    {
        $httpClient = static::createClient();
        $httpClient->jsonRequest('POST', '/oauth/register', [
            'redirect_uris' => ['https://client.example.invalid/callback'],
            'response_types' => ['token'],
        ]);

        self::assertResponseStatusCodeSame(400);
    }

    public function testUnknownScopeIsRejected(): void
    {
        $httpClient = static::createClient();
        $httpClient->jsonRequest('POST', '/oauth/register', [
            'redirect_uris' => ['https://client.example.invalid/callback'],
            'scope' => 'superadmin',
        ]);

        self::assertResponseStatusCodeSame(400);
        $data = json_decode((string) $httpClient->getResponse()->getContent(), true);
        self::assertSame('invalid_client_metadata', $data['error']);
    }

    public function testMalformedJsonBodyIsRejected(): void
    {
        $httpClient = static::createClient();
        $httpClient->request('POST', '/oauth/register', [], [], ['CONTENT_TYPE' => 'application/json'], '{not json');

        self::assertResponseStatusCodeSame(400);
    }

    public function testRateLimiterRejectsAfterConfiguredLimit(): void
    {
        // Exercises the actual "oauth_client_registration" limiter config (config/packages/rate_limiter.yaml)
        // directly, with a random per-test key, rather than firing 21 real HTTP requests against a shared
        // IP-keyed bucket - that would make this test (and any other test hitting the endpoint in the same
        // run/hour) flaky, since the limiter's storage persists across requests and test runs.
        $httpClient = static::createClient();
        $factory = $httpClient->getContainer()->get('limiter.oauth_client_registration');
        self::assertInstanceOf(RateLimiterFactory::class, $factory);

        $limiter = $factory->create('test-'.bin2hex(random_bytes(8)));
        self::assertTrue($limiter->consume(20)->isAccepted());
        self::assertFalse($limiter->consume(1)->isAccepted());
    }
}
