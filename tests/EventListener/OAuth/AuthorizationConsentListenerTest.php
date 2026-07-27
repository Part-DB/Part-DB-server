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

use App\Entity\UserSystem\ApiTokenLevel;
use App\Entity\UserSystem\User;
use App\Services\OAuth\OAuthClientGrantPreferenceManager;
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
    /**
     * @param string[] $scopes
     */
    private function createTestClient(\Symfony\Bundle\FrameworkBundle\KernelBrowser $httpClient, string $redirectUri, array $scopes = ['read_only']): Client
    {
        $client = new Client('Test Client', 'test-client-'.bin2hex(random_bytes(8)), null);
        $client->setRedirectUris(new RedirectUri($redirectUri));
        $client->setGrants(new Grant('authorization_code'), new Grant('refresh_token'));
        $client->setScopes(...array_map(static fn (string $s) => new Scope($s), $scopes));
        $client->setActive(true);

        static::getContainer()->get(ClientManagerInterface::class)->save($client);

        return $client;
    }

    /**
     * @param string[] $scopes
     */
    private function authorizeUrl(Client $client, string $redirectUri, array $scopes = ['read_only']): string
    {
        $verifier = rtrim(strtr(base64_encode(random_bytes(40)), '+/', '-_'), '=');
        $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

        return '/authorize?'.http_build_query([
            'response_type' => 'code',
            'client_id' => $client->getIdentifier(),
            'redirect_uri' => $redirectUri,
            'code_challenge' => $challenge,
            'code_challenge_method' => 'S256',
            'scope' => implode(' ', $scopes),
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

    public function testNarrowerScopeLevelFriendlyNameAndTtlArePersisted(): void
    {
        $httpClient = static::createClient();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $admin = $entityManager->getRepository(User::class)->findOneBy(['name' => 'admin']);
        self::assertInstanceOf(User::class, $admin);
        $httpClient->loginUser($admin);

        $redirectUri = 'https://client.example.invalid/callback';
        $client = $this->createTestClient($httpClient, $redirectUri, ['read_only', 'edit', 'full']);

        $crawler = $httpClient->request('GET', $this->authorizeUrl($client, $redirectUri, ['read_only', 'edit', 'full']));
        self::assertResponseIsSuccessful();

        // Cumulative choices up to the highest requested level (full) are offered - read_only, edit,
        // admin, full - even though "admin" itself wasn't individually requested (see
        // App\EventListener\OAuth\AuthorizationConsentListener's cumulative-level design).
        self::assertCount(4, $crawler->filter('input[name="oauth_scope_level"]'));

        $form = $crawler->selectButton('Approve')->form([
            'oauth_scope_level' => 'edit',
            'oauth_friendly_name' => 'My Laptop',
            'oauth_ttl_days' => '7',
        ]);
        $httpClient->submit($form);

        self::assertResponseRedirects();

        $preferences = static::getContainer()->get(OAuthClientGrantPreferenceManager::class);
        $preference = $preferences->find($admin->getUserIdentifier(), $client->getIdentifier());
        self::assertNotNull($preference);
        self::assertSame(ApiTokenLevel::EDIT, $preference->getScopeLevel());
        self::assertSame('My Laptop', $preference->getFriendlyName());
        self::assertSame(7, $preference->getRefreshTokenTtlDays());
    }

    public function testConsentScreenPrefillsPreviouslySavedPreference(): void
    {
        $httpClient = static::createClient();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $admin = $entityManager->getRepository(User::class)->findOneBy(['name' => 'admin']);
        self::assertInstanceOf(User::class, $admin);
        $httpClient->loginUser($admin);

        $redirectUri = 'https://client.example.invalid/callback';
        $client = $this->createTestClient($httpClient, $redirectUri, ['read_only', 'edit']);

        static::getContainer()->get(OAuthClientGrantPreferenceManager::class)->save(
            $admin->getUserIdentifier(),
            $client->getIdentifier(),
            ApiTokenLevel::READ_ONLY,
            'Existing Name',
            30,
        );

        $crawler = $httpClient->request('GET', $this->authorizeUrl($client, $redirectUri, ['read_only', 'edit']));
        self::assertResponseIsSuccessful();

        self::assertSame('Existing Name', $crawler->filter('input[name="oauth_friendly_name"]')->attr('value'));
        self::assertCount(1, $crawler->filter('input[name="oauth_scope_level"][value="read_only"][checked]'));
        self::assertCount(1, $crawler->filter('select[name="oauth_ttl_days"] option[value="30"][selected]'));
    }

    public function testTamperedScopeLevelIsRejected(): void
    {
        $httpClient = static::createClient();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $admin = $entityManager->getRepository(User::class)->findOneBy(['name' => 'admin']);
        self::assertInstanceOf(User::class, $admin);
        $httpClient->loginUser($admin);

        $redirectUri = 'https://client.example.invalid/callback';
        // Client only registered/requested "read_only" - "full" must not be an acceptable choice.
        $client = $this->createTestClient($httpClient, $redirectUri, ['read_only']);
        $url = $this->authorizeUrl($client, $redirectUri, ['read_only']);

        $crawler = $httpClient->request('GET', $url);
        self::assertResponseIsSuccessful();
        $token = $crawler->filter('input[name="_csrf_token"]')->attr('value');

        $httpClient->request('POST', $url, [
            'oauth_decision' => 'approve',
            '_csrf_token' => $token,
            'oauth_scope_level' => 'full',
            'oauth_friendly_name' => '',
            'oauth_ttl_days' => '',
        ]);
        self::assertResponseStatusCodeSame(400);
    }

    public function testTamperedTtlIsRejected(): void
    {
        $httpClient = static::createClient();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $admin = $entityManager->getRepository(User::class)->findOneBy(['name' => 'admin']);
        self::assertInstanceOf(User::class, $admin);
        $httpClient->loginUser($admin);

        $redirectUri = 'https://client.example.invalid/callback';
        $client = $this->createTestClient($httpClient, $redirectUri, ['read_only']);
        $url = $this->authorizeUrl($client, $redirectUri, ['read_only']);

        $crawler = $httpClient->request('GET', $url);
        self::assertResponseIsSuccessful();
        $token = $crawler->filter('input[name="_csrf_token"]')->attr('value');

        $httpClient->request('POST', $url, [
            'oauth_decision' => 'approve',
            '_csrf_token' => $token,
            'oauth_scope_level' => 'read_only',
            'oauth_friendly_name' => '',
            'oauth_ttl_days' => '5000',
        ]);
        self::assertResponseStatusCodeSame(400);
    }
}
