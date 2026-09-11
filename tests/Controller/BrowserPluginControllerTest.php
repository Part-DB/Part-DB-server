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

namespace App\Tests\Controller;

use App\Entity\UserSystem\User;
use App\Settings\InfoProviderSystem\BrowserPluginSettings;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

#[Group("slow")]
#[Group("DB")]
final class BrowserPluginControllerTest extends WebTestCase
{
    // --- GET /browser_info ---

    public function testGetInfoReturns401WhenNotAuthenticated(): void
    {
        $client = static::createClient();
        $client->request('GET', '/en/tools/info_providers/browser_info');

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testGetInfoReturnsForbiddenForUnprivilegedUser(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $this->loginAsUser($client, 'noread');
        static::getContainer()->get(BrowserPluginSettings::class)->enabled = true;

        $client->request('GET', '/en/tools/info_providers/browser_info');

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testGetInfoReturns451WhenPluginDisabled(): void
    {
        $client = static::createClient();
        $this->loginAsUser($client, 'admin');
        // BrowserPluginSettings::$enabled defaults to false

        $client->request('GET', '/en/tools/info_providers/browser_info');

        self::assertResponseStatusCodeSame(451);
    }

    public function testGetInfoReturnsJsonWithExpectedKeys(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $this->loginAsUser($client, 'admin');
        static::getContainer()->get(BrowserPluginSettings::class)->enabled = true;

        $client->request('GET', '/en/tools/info_providers/browser_info');

        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        self::assertResponseHeaderSame('Content-Type', 'application/json');

        $data = json_decode((string) $client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('username', $data);
        $this->assertArrayHasKey('instance_name', $data);
        $this->assertArrayHasKey('url_providers', $data);
        $this->assertIsString($data['username']);
        $this->assertIsString($data['instance_name']);
        $this->assertIsArray($data['url_providers']);
        $this->assertNotEmpty($data['username']);
        $this->assertNotEmpty($data['instance_name']);
    }

    public function testGetInfoUrlProvidersHaveIdAndLabel(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $this->loginAsUser($client, 'admin');
        static::getContainer()->get(BrowserPluginSettings::class)->enabled = true;

        $client->request('GET', '/en/tools/info_providers/browser_info');

        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        $data = json_decode((string) $client->getResponse()->getContent(), true);

        foreach ($data['url_providers'] as $provider) {
            $this->assertArrayHasKey('id', $provider);
            $this->assertArrayHasKey('label', $provider);
            $this->assertIsString($provider['id']);
            $this->assertIsString($provider['label']);
            $this->assertNotEmpty($provider['id']);
            $this->assertNotEmpty($provider['label']);
        }
    }

    // --- POST /browser_html ---

    public function testSubmitHtmlReturns401WhenNotAuthenticated(): void
    {
        $client = static::createClient();
        $client->request('POST', '/en/tools/info_providers/browser_html', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['url' => 'https://example.com', 'html' => '<html/>', 'title' => 'Test']));

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testSubmitHtmlReturns451WhenPluginDisabled(): void
    {
        $client = static::createClient();
        $this->loginAsUser($client, 'admin');
        // BrowserPluginSettings::$enabled defaults to false

        $client->request('POST', '/en/tools/info_providers/browser_html', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['url' => 'https://example.com', 'html' => '<html/>', 'title' => 'Test']));

        self::assertResponseStatusCodeSame(451);
    }

    public function testSubmitHtmlWithValidDataAndProvider(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $this->loginAsUser($client, 'admin');
        static::getContainer()->get(BrowserPluginSettings::class)->enabled = true;

        $client->request('POST', '/en/tools/info_providers/browser_html', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'url'      => 'https://example.com/product/123',
            'html'     => '<html><body>Product page</body></html>',
            'title'    => 'Some Product',
            'provider' => 'generic_web',
        ]));

        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        $data = json_decode((string) $client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('redirect_url', $data);
        $this->assertNotNull($data['redirect_url']);
        $this->assertStringContainsString('generic_web', (string) $data['redirect_url']);
    }

    public function testSubmitHtmlWithoutProviderReturnsNullRedirectUrl(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $this->loginAsUser($client, 'admin');
        static::getContainer()->get(BrowserPluginSettings::class)->enabled = true;

        $client->request('POST', '/en/tools/info_providers/browser_html', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'url'   => 'https://example.com/product/123',
            'html'  => '<html><body>Product page</body></html>',
            'title' => 'Some Product',
        ]));

        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        $data = json_decode((string) $client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('redirect_url', $data);
        $this->assertNull($data['redirect_url']);
    }

    public function testSubmitHtmlWithInvalidJsonReturns400(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $this->loginAsUser($client, 'admin');
        static::getContainer()->get(BrowserPluginSettings::class)->enabled = true;

        $client->request('POST', '/en/tools/info_providers/browser_html', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], 'this is not valid json {');

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    public function testSubmitHtmlWithMissingUrlReturns422(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $this->loginAsUser($client, 'admin');
        static::getContainer()->get(BrowserPluginSettings::class)->enabled = true;

        $client->request('POST', '/en/tools/info_providers/browser_html', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['html' => '<html/>', 'title' => 'Test']));

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testSubmitHtmlWithMissingHtmlReturns422(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $this->loginAsUser($client, 'admin');
        static::getContainer()->get(BrowserPluginSettings::class)->enabled = true;

        $client->request('POST', '/en/tools/info_providers/browser_html', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['url' => 'https://example.com', 'title' => 'Test']));

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testSubmitHtmlWithInvalidUrlReturns422(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $this->loginAsUser($client, 'admin');
        static::getContainer()->get(BrowserPluginSettings::class)->enabled = true;

        $client->request('POST', '/en/tools/info_providers/browser_html', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['url' => 'not-a-url', 'html' => '<html/>', 'title' => 'Test']));

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    private function loginAsUser(mixed $client, string $username): void
    {
        $entityManager = static::getContainer()->get('doctrine')->getManager();
        $user = $entityManager->getRepository(User::class)->findOneBy(['name' => $username]);
        if (!$user) {
            $this->markTestSkipped("User '{$username}' not found in fixtures");
        }
        $client->loginUser($user);
    }
}
