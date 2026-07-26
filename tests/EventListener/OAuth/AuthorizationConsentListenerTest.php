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

namespace App\Tests\EventListener\OAuth;

use App\Entity\UserSystem\User;
use Doctrine\ORM\EntityManagerInterface;
use League\Bundle\OAuth2ServerBundle\Manager\ClientManagerInterface;
use League\Bundle\OAuth2ServerBundle\Model\Client;
use League\Bundle\OAuth2ServerBundle\ValueObject\Grant;
use League\Bundle\OAuth2ServerBundle\ValueObject\RedirectUri;
use League\Bundle\OAuth2ServerBundle\ValueObject\Scope;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Drives the real GET/POST /authorize HTTP flow (routes/controller provided by
 * league/oauth2-server-bundle, consent screen provided by
 * App\EventListener\OAuth\AuthorizationConsentListener), rather than calling AuthorizationServer
 * directly - this is the only test covering the actual consent screen and its CSRF handling.
 */
final class AuthorizationConsentListenerTest extends WebTestCase
{
    private function createTestClient(\Symfony\Bundle\FrameworkBundle\KernelBrowser $httpClient, string $redirectUri): Client
    {
        $client = new Client('Test Client', 'test-client-'.bin2hex(random_bytes(8)), null);
        $client->setRedirectUris(new RedirectUri($redirectUri));
        $client->setGrants(new Grant('authorization_code'), new Grant('refresh_token'));
        $client->setScopes(new Scope('read_only'));
        $client->setActive(true);

        static::getContainer()->get(ClientManagerInterface::class)->save($client);

        return $client;
    }

    private function authorizeUrl(Client $client, string $redirectUri): string
    {
        $verifier = rtrim(strtr(base64_encode(random_bytes(40)), '+/', '-_'), '=');
        $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

        return '/authorize?'.http_build_query([
            'response_type' => 'code',
            'client_id' => $client->getIdentifier(),
            'redirect_uri' => $redirectUri,
            'code_challenge' => $challenge,
            'code_challenge_method' => 'S256',
            'scope' => 'read_only',
        ]);
    }

    public function testUnauthenticatedRequestIsRejected(): void
    {
        // Note: config/packages/test/security.yaml overrides the "main" firewall's entry_point to
        // "http_basic" for the whole test environment, so unauthenticated requests to any
        // IS_AUTHENTICATED_FULLY-protected route (including /authorize) get a 401 "Basic realm"
        // challenge here, not the production redirect-to-/login behaviour of
        // App\Security\AuthenticationEntryPoint. Same convention as
        // App\Tests\Controller\AuthorizationTest::testUnauthenticatedIsUnauthorizedOnWriteRoutes.
        $httpClient = static::createClient();
        $redirectUri = 'https://client.example.invalid/callback';
        $client = $this->createTestClient($httpClient, $redirectUri);

        $httpClient->request('GET', $this->authorizeUrl($client, $redirectUri));
        self::assertResponseStatusCodeSame(401);
    }

    public function testApproveRedirectsWithAuthorizationCode(): void
    {
        $httpClient = static::createClient();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $admin = $entityManager->getRepository(User::class)->findOneBy(['name' => 'admin']);
        self::assertInstanceOf(User::class, $admin);
        $httpClient->loginUser($admin);

        $redirectUri = 'https://client.example.invalid/callback';
        $client = $this->createTestClient($httpClient, $redirectUri);

        $crawler = $httpClient->request('GET', $this->authorizeUrl($client, $redirectUri));
        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton('Approve')->form();
        $httpClient->submit($form);

        self::assertResponseRedirects();
        $location = $httpClient->getResponse()->headers->get('Location');
        self::assertNotNull($location);
        self::assertStringStartsWith($redirectUri, $location);
        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);
        self::assertArrayHasKey('code', $query);
    }

    public function testDenyRedirectsWithAccessDeniedError(): void
    {
        $httpClient = static::createClient();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $admin = $entityManager->getRepository(User::class)->findOneBy(['name' => 'admin']);
        self::assertInstanceOf(User::class, $admin);
        $httpClient->loginUser($admin);

        $redirectUri = 'https://client.example.invalid/callback';
        $client = $this->createTestClient($httpClient, $redirectUri);

        $crawler = $httpClient->request('GET', $this->authorizeUrl($client, $redirectUri));
        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton('Deny')->form();
        $httpClient->submit($form);

        self::assertResponseRedirects();
        $location = $httpClient->getResponse()->headers->get('Location');
        self::assertNotNull($location);
        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);
        self::assertSame('access_denied', $query['error'] ?? null);
    }

    public function testMissingCsrfTokenIsRejected(): void
    {
        $httpClient = static::createClient();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $admin = $entityManager->getRepository(User::class)->findOneBy(['name' => 'admin']);
        self::assertInstanceOf(User::class, $admin);
        $httpClient->loginUser($admin);

        $redirectUri = 'https://client.example.invalid/callback';
        $client = $this->createTestClient($httpClient, $redirectUri);
        $url = $this->authorizeUrl($client, $redirectUri);

        $httpClient->request('GET', $url);
        self::assertResponseIsSuccessful();

        $httpClient->request('POST', $url, ['oauth_decision' => 'approve', '_csrf_token' => 'invalid']);
        self::assertResponseStatusCodeSame(403);
    }
}
