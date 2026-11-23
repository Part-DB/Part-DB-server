<?php

declare(strict_types=1);

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
namespace App\Tests\Controller;

use App\Entity\UserSystem\User;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ScanControllerTest extends WebTestCase
{

    private ?KernelBrowser $client = null;

    public function setUp(): void
    {
        $this->client = static::createClient([], [
            'PHP_AUTH_USER' => 'admin',
            'PHP_AUTH_PW' => 'test',
        ]);
        $this->client->disableReboot();
        $this->client->catchExceptions(false);
    }

    public function testRedirectOnInputParameter(): void
    {
        $this->client->request('GET', '/en/scan?input=0000001');
        $this->assertResponseRedirects('/en/part/1');
    }

    public function testReturnToScannerCheckboxPrefilledFromQuery(): void
    {
        $this->client->request('GET', '/en/scan?return_to_scanner=1');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('#scan_dialog_return_to_scanner[checked]');

        $this->client->request('GET', '/en/scan');
        $this->assertSelectorNotExists('#scan_dialog_return_to_scanner[checked]');
    }

    public function testTurboRedirectBackToScannerRendersFullPage(): void
    {
        //Turbo keeps its turbo-stream Accept header when following the redirect after saving a new part
        $this->client->request('GET', '/en/scan?return_to_scanner=1', [], [], [
            'HTTP_ACCEPT' => 'text/vnd.turbo-stream.html, text/html, application/xhtml+xml',
        ]);
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('#scan_dialog_return_to_scanner[checked]');
    }

    public function testScanQRCode(): void
    {
        $this->client->request('GET', '/scan/part/1');
        $this->assertResponseRedirects('/en/part/1');
    }

    private function submitInfoModeScan(string $input): string
    {
        $crawler = $this->client->request('GET', '/en/scan');
        $form = $crawler->selectButton('scan_dialog[submit]')->form([
            'scan_dialog[input]' => $input,
            'scan_dialog[info_mode]' => true,
        ]);
        $this->client->submit($form);
        $this->assertResponseIsSuccessful();

        return $this->client->getResponse()->getContent();
    }

    public function testInfoModeShowsPart(): void
    {
        $content = $this->submitInfoModeScan('P0001');
        $this->assertStringContainsString('Part 1', $content);
    }

    /**
     * A user with only the label scanner permission must not be able to read parts/lots/storage locations
     * via the info mode (GHSA-4gvj-mg37-hc6p).
     */
    public function testInfoModeDoesNotLeakUnreadableEntities(): void
    {
        $em = self::getContainer()->get('doctrine')->getManager();
        /** @var User $user */
        $user = $em->getRepository(User::class)->findOneBy(['name' => 'noread']);
        $user->getPermissions()->setPermissionValue('tools', 'label_scanner', true);
        $em->flush();
        //Remove the admin basic auth credentials, so that the logged in user is used
        $this->client->setServerParameters([]);
        $this->client->loginUser($user);

        $content = $this->submitInfoModeScan('P0001');
        $this->assertStringNotContainsString('Part 1', $content);
        $this->assertSelectorNotExists('.card.border-success');

        $content = $this->submitInfoModeScan('L0001');
        $this->assertStringNotContainsString('Part 1', $content);
        $this->assertSelectorNotExists('.card.border-success');

        $this->submitInfoModeScan('S0001');
        $this->assertSelectorNotExists('.card.border-success');
    }
}
