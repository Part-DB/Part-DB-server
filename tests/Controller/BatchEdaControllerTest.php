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

namespace App\Tests\Controller;

use App\Entity\UserSystem\User;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

#[Group("slow")]
#[Group("DB")]
final class BatchEdaControllerTest extends WebTestCase
{
    private function loginAsUser($client, string $username): void
    {
        $entityManager = $client->getContainer()->get('doctrine')->getManager();
        $userRepository = $entityManager->getRepository(User::class);
        $user = $userRepository->findOneBy(['name' => $username]);

        if (!$user) {
            $this->markTestSkipped("User {$username} not found");
        }

        $client->loginUser($user);
    }

    public function testBatchEdaPageLoads(): void
    {
        $client = static::createClient();
        $this->loginAsUser($client, 'admin');

        $client->request('GET', '/en/tools/batch_eda_edit', ['ids' => '1,2,3']);

        self::assertResponseIsSuccessful();
    }

    public function testBatchEdaPageWithoutPartsRedirects(): void
    {
        $client = static::createClient();
        $this->loginAsUser($client, 'admin');

        $client->request('GET', '/en/tools/batch_eda_edit');

        self::assertResponseRedirects();
    }

    public function testBatchEdaPageWithoutPartsRedirectsToCustomUrl(): void
    {
        $client = static::createClient();
        $this->loginAsUser($client, 'admin');

        // Empty IDs with a custom redirect URL
        $client->request('GET', '/en/tools/batch_eda_edit', [
            'ids' => '',
            '_redirect' => '/en/parts',
        ]);

        self::assertResponseRedirects('/en/parts');
    }

    public function testBatchEdaFormSubmission(): void
    {
        $client = static::createClient();
        $this->loginAsUser($client, 'admin');

        $crawler = $client->request('GET', '/en/tools/batch_eda_edit', ['ids' => '1,2']);

        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton('batch_eda[submit]')->form();
        $form['batch_eda[apply_reference_prefix]'] = true;
        $form['batch_eda[reference_prefix]'] = 'R';

        $client->submit($form);

        self::assertResponseRedirects();
    }

    public function testBatchEdaFormSubmissionAppliesAllFields(): void
    {
        $client = static::createClient();
        $this->loginAsUser($client, 'admin');

        $crawler = $client->request('GET', '/en/tools/batch_eda_edit', ['ids' => '1,2']);
        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton('batch_eda[submit]')->form();

        // Apply all text fields
        $form['batch_eda[apply_reference_prefix]'] = true;
        $form['batch_eda[reference_prefix]'] = 'C';
        $form['batch_eda[apply_value]'] = true;
        $form['batch_eda[value]'] = '100nF';
        $form['batch_eda[apply_kicad_symbol]'] = true;
        $form['batch_eda[kicad_symbol]'] = 'Device:C';
        $form['batch_eda[apply_kicad_footprint]'] = true;
        $form['batch_eda[kicad_footprint]'] = 'Capacitor_SMD:C_0402';

        // Apply all tri-state checkboxes
        $form['batch_eda[apply_visibility]'] = true;
        $form['batch_eda[apply_exclude_from_bom]'] = true;
        $form['batch_eda[apply_exclude_from_board]'] = true;
        $form['batch_eda[apply_exclude_from_sim]'] = true;

        $client->submit($form);

        // All field branches in the controller are now exercised; redirect confirms success
        self::assertResponseRedirects();
    }

    public function testBatchEdaFormSubmissionWithRedirectUrl(): void
    {
        $client = static::createClient();
        $this->loginAsUser($client, 'admin');

        $crawler = $client->request('GET', '/en/tools/batch_eda_edit', [
            'ids' => '1',
            '_redirect' => '/en/parts',
        ]);
        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton('batch_eda[submit]')->form();
        $form['batch_eda[apply_reference_prefix]'] = true;
        $form['batch_eda[reference_prefix]'] = 'U';

        $client->submit($form);

        // Should redirect to the custom URL, not the default route
        self::assertResponseRedirects('/en/parts');
    }

    public function testBatchEdaFormWithPartialFields(): void
    {
        $client = static::createClient();
        $this->loginAsUser($client, 'admin');

        $crawler = $client->request('GET', '/en/tools/batch_eda_edit', ['ids' => '3']);
        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton('batch_eda[submit]')->form();
        // Only apply value and kicad_footprint, leave other apply checkboxes unchecked
        $form['batch_eda[apply_value]'] = true;
        $form['batch_eda[value]'] = 'TestValue';
        $form['batch_eda[apply_kicad_footprint]'] = true;
        $form['batch_eda[kicad_footprint]'] = 'Package_SO:SOIC-8';

        $client->submit($form);

        // Redirect confirms the partial submission was processed
        self::assertResponseRedirects();
    }
}
