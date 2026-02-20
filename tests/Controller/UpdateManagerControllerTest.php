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

#[Group("slow")]
#[Group("DB")]
final class UpdateManagerControllerTest extends WebTestCase
{
    private function loginAsAdmin($client): void
    {
        $entityManager = $client->getContainer()->get('doctrine')->getManager();
        $userRepository = $entityManager->getRepository(User::class);
        $user = $userRepository->findOneBy(['name' => 'admin']);

        if (!$user) {
            $this->markTestSkipped('Admin user not found');
        }

        $client->loginUser($user);
    }

    public function testIndexPageRequiresAuth(): void
    {
        $client = static::createClient();

        $client->request('GET', '/en/system/update-manager');

        // Should deny access (401 with HTTP Basic auth in test env)
        $this->assertResponseStatusCodeSame(401);
    }

    public function testIndexPageAccessibleByAdmin(): void
    {
        $client = static::createClient();
        $this->loginAsAdmin($client);

        $client->request('GET', '/en/system/update-manager');

        $this->assertResponseIsSuccessful();
    }

    public function testCreateBackupRequiresCsrf(): void
    {
        $client = static::createClient();
        $this->loginAsAdmin($client);

        $client->request('POST', '/en/system/update-manager/backup', [
            '_token' => 'invalid',
        ]);

        // Should redirect with error flash
        $this->assertResponseRedirects();
    }

    public function testDeleteBackupRequiresCsrf(): void
    {
        $client = static::createClient();
        $this->loginAsAdmin($client);

        $client->request('POST', '/en/system/update-manager/backup/delete', [
            '_token' => 'invalid',
            'filename' => 'test.zip',
        ]);

        $this->assertResponseRedirects();
    }

    public function testDeleteLogRequiresCsrf(): void
    {
        $client = static::createClient();
        $this->loginAsAdmin($client);

        $client->request('POST', '/en/system/update-manager/log/delete', [
            '_token' => 'invalid',
            'filename' => 'test.log',
        ]);

        $this->assertResponseRedirects();
    }

    public function testDownloadBackupReturns404ForNonExistent(): void
    {
        $client = static::createClient();
        $this->loginAsAdmin($client);

        $client->request('GET', '/en/system/update-manager/backup/download/nonexistent.zip');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testBackupDetailsReturns404ForNonExistent(): void
    {
        $client = static::createClient();
        $this->loginAsAdmin($client);

        $client->request('GET', '/en/system/update-manager/backup/nonexistent.zip');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testRestoreBlockedWhenDisabled(): void
    {
        $client = static::createClient();
        $this->loginAsAdmin($client);

        // DISABLE_BACKUP_RESTORE=1 is the default in .env, so this should return 403
        $client->request('POST', '/en/system/update-manager/restore', [
            '_token' => 'invalid',
            'filename' => 'test.zip',
        ]);

        $this->assertResponseStatusCodeSame(403);
    }
}
