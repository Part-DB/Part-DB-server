<?php
/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2024 Jan Böhmer (https://github.com/jbtronics)
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
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

#[Group('slow')]
#[Group('DB')]
final class KicadListEditorControllerTest extends WebTestCase
{
    private string $footprintsPath;
    private string $symbolsPath;
    private string $originalFootprints;
    private string $originalSymbols;

    protected function setUp(): void
    {
        parent::setUp();

        $projectDir = dirname(__DIR__, 2);
        $this->footprintsPath = $projectDir . '/public/kicad/footprints.txt';
        $this->symbolsPath = $projectDir . '/public/kicad/symbols.txt';
        $this->originalFootprints = (string) file_get_contents($this->footprintsPath);
        $this->originalSymbols = (string) file_get_contents($this->symbolsPath);
    }

    protected function tearDown(): void
    {
        file_put_contents($this->footprintsPath, $this->originalFootprints);
        file_put_contents($this->symbolsPath, $this->originalSymbols);

        parent::tearDown();
    }

    public function testEditorRequiresAuthentication(): void
    {
        $client = static::createClient();
        $client->request('GET', '/en/settings/misc/kicad-lists');

        $this->assertResponseStatusCodeSame(401);
    }

    public function testEditorAccessibleByAdmin(): void
    {
        $client = static::createClient();
        $this->loginAsUser($client, 'admin');

        $client->request('GET', '/en/settings/misc/kicad-lists');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('form[name="kicad_list_editor"]');
    }

    public function testEditorSavesFiles(): void
    {
        $client = static::createClient();
        $this->loginAsUser($client, 'admin');

        $crawler = $client->request('GET', '/en/settings/misc/kicad-lists');
        $form = $crawler->filter('form[name="kicad_list_editor"]')->form();
        $form['kicad_list_editor[footprints]'] = "Package_DIP:DIP-8_W7.62mm\n";
        $form['kicad_list_editor[symbols]'] = "Device:R\n";

        $client->submit($form);

        $this->assertResponseRedirects('/en/settings/misc/kicad-lists');
        $this->assertSame("Package_DIP:DIP-8_W7.62mm\n", (string) file_get_contents($this->footprintsPath));
        $this->assertSame("Device:R\n", (string) file_get_contents($this->symbolsPath));
    }

    private function loginAsUser($client, string $username): void
    {
        $entityManager = $client->getContainer()->get('doctrine')->getManager();
        $userRepository = $entityManager->getRepository(User::class);
        $user = $userRepository->findOneBy(['name' => $username]);

        if (!$user) {
            $this->markTestSkipped(sprintf('User "%s" not found in fixtures', $username));
        }

        $client->loginUser($user);
    }
}
