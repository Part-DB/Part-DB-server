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

        // Request with part IDs as comma-separated string (controller uses getString)
        $client->request('GET', '/en/tools/batch_eda_edit', ['ids' => '1,2,3']);

        self::assertResponseIsSuccessful();
    }

    public function testBatchEdaPageWithoutPartsRedirects(): void
    {
        $client = static::createClient();
        $this->loginAsUser($client, 'admin');

        // Request without part IDs should redirect
        $client->request('GET', '/en/tools/batch_eda_edit');

        self::assertResponseRedirects();
    }

    public function testBatchEdaFormSubmission(): void
    {
        $client = static::createClient();
        $this->loginAsUser($client, 'admin');

        // Load the form page first
        $crawler = $client->request('GET', '/en/tools/batch_eda_edit', ['ids' => '1,2']);

        self::assertResponseIsSuccessful();

        // Find the form and submit it with reference prefix applied
        $form = $crawler->selectButton('batch_eda[submit]')->form();
        $form['batch_eda[apply_reference_prefix]'] = true;
        $form['batch_eda[reference_prefix]'] = 'R';

        $client->submit($form);

        // Should redirect after successful submission
        self::assertResponseRedirects();
    }
}
