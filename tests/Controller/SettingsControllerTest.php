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

use App\Entity\OAuthToken;
use App\Entity\UserSystem\User;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

#[Group('slow')]
#[Group('DB')]
final class SettingsControllerTest extends WebTestCase
{
    public function testSystemSettingsLinksProviderSettingsInsteadOfEmbeddingThem(): void
    {
        $client = static::createClient();
        $this->loginAsAdmin($client);

        $client->request('GET', '/en/settings');
        $this->assertResponseIsSuccessful();

        //General info provider settings are still rendered inline
        $this->assertSelectorExists('[name^="form[infoProviders][general]"]');
        //Provider specific settings are not embedded anymore...
        $this->assertSelectorNotExists('[name^="form[infoProviders][digikey]"]');
        $this->assertSelectorNotExists('[name^="form[infoProviders][lcsc]"]');
        //...but linked to their own settings pages
        $this->assertSelectorExists('a[href$="/tools/info_providers/provider/digikey/settings"]');
        $this->assertSelectorExists('a[href$="/tools/info_providers/provider/lcsc/settings"]');
        //Providers needed for the "create from URL/file" features are marked with a badge
        $this->assertSelectorTextContains('a[href$="/provider/generic_web/settings"] .badge.text-bg-info', 'Create part from URL');
        $this->assertSelectorTextContains('a[href$="/provider/ai_document/settings"] .badge.text-bg-info', 'Create part from file');
        $this->assertSelectorNotExists('a[href$="/provider/lcsc/settings"] .badge.text-bg-info');
        //The info providers tab must be selectable via the fragment used by the back link of the provider settings pages
        $this->assertSelectorExists('[data-bs-target="#settings-form[infoProviders]-pane"]');
    }

    public function testProviderSettingsPageLinksBackToSystemSettings(): void
    {
        $client = static::createClient();
        $this->loginAsAdmin($client);

        $client->request('GET', '/en/tools/info_providers/provider/lcsc/settings');
        $this->assertResponseIsSuccessful();

        $this->assertSelectorExists('a[href="/en/settings#settings-form[infoProviders]-pane"]');
    }

    public function testProviderSettingsPageShowsOAuthConnectButtonWithoutToken(): void
    {
        $client = static::createClient();
        $this->loginAsAdmin($client);

        $em = $client->getContainer()->get('doctrine')->getManager();
        $this->assertNull($em->getRepository(OAuthToken::class)->findOneBy(['name' => 'ip_digikey_oauth']),
            'Test expects no stored Digikey token');

        $client->request('GET', '/en/tools/info_providers/provider/digikey/settings');
        $this->assertResponseIsSuccessful();

        $this->assertSelectorExists('a[href="/en/oauth/client/ip_digikey_oauth/connect"]');
        $this->assertSelectorTextContains('fieldset .badge.text-bg-warning', 'Not connected');
    }

    public function testProviderSettingsPageShowsStoredOAuthTokenInfo(): void
    {
        $client = static::createClient();
        $this->loginAsAdmin($client);

        $em = $client->getContainer()->get('doctrine')->getManager();
        $token = new OAuthToken('ip_digikey_oauth', 'secret-refresh-token', 'secret-access-token',
            new \DateTimeImmutable('-1 hour'));
        $em->persist($token);
        $em->flush();

        try {
            $client->request('GET', '/en/tools/info_providers/provider/digikey/settings');
            $this->assertResponseIsSuccessful();

            $this->assertSelectorTextContains('fieldset .badge.text-bg-success', 'Connected');
            $this->assertSelectorTextContains('fieldset', 'Authorization code');
            $this->assertSelectorTextContains('fieldset', 'Expired');
            $this->assertSelectorTextContains('a[href="/en/oauth/client/ip_digikey_oauth/connect"]', 'Reconnect');
            //The token values must never be shown
            $this->assertStringNotContainsString('secret-', (string) $client->getResponse()->getContent());
        } finally {
            $em = $client->getContainer()->get('doctrine')->getManager();
            $em->remove($em->getRepository(OAuthToken::class)->findOneBy(['name' => 'ip_digikey_oauth']));
            $em->flush();
        }
    }

    public function testProviderSettingsPageWithoutOAuthHasNoOAuthSection(): void
    {
        $client = static::createClient();
        $this->loginAsAdmin($client);

        $client->request('GET', '/en/tools/info_providers/provider/lcsc/settings');
        $this->assertResponseIsSuccessful();

        $this->assertSelectorNotExists('a[href*="/oauth/client/"]');
    }

    private function loginAsAdmin($client): void
    {
        $user = $client->getContainer()->get('doctrine')->getManager()
            ->getRepository(User::class)->findOneBy(['name' => 'admin']);
        if (!$user) {
            $this->markTestSkipped('User "admin" not found in fixtures');
        }
        $client->loginUser($user);
    }
}
