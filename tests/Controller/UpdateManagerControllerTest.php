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
use App\Services\System\BackupManager;
use App\Services\System\UpdateExecutor;
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

    public function testCreateBackupWithValidCsrf(): void
    {
        $client = static::createClient();
        $this->loginAsAdmin($client);

        // Get a valid CSRF token
        $csrfToken = $client->getContainer()->get('security.csrf.token_manager')
            ->getToken('update_manager_backup')->getValue();

        $client->request('POST', '/en/system/update-manager/backup', [
            '_token' => $csrfToken,
        ]);

        $this->assertResponseRedirects();

        // Clean up: delete the backup that was just created
        $backupManager = $client->getContainer()->get(BackupManager::class);
        $backups = $backupManager->getBackups();
        foreach ($backups as $backup) {
            if (str_contains($backup['file'], 'manual')) {
                $backupManager->deleteBackup($backup['file']);
            }
        }
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

    public function testDeleteBackupWithValidCsrf(): void
    {
        $client = static::createClient();
        $this->loginAsAdmin($client);

        // Create a temporary backup file to delete
        $backupManager = $client->getContainer()->get(BackupManager::class);
        $backupDir = $backupManager->getBackupDir();
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }
        $testFile = 'test-delete-' . uniqid() . '.zip';
        file_put_contents($backupDir . '/' . $testFile, 'test');

        $csrfToken = $client->getContainer()->get('security.csrf.token_manager')
            ->getToken('update_manager_delete')->getValue();

        $client->request('POST', '/en/system/update-manager/backup/delete', [
            '_token' => $csrfToken,
            'filename' => $testFile,
        ]);

        $this->assertResponseRedirects();
        $this->assertFileDoesNotExist($backupDir . '/' . $testFile);
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

    public function testDeleteLogWithValidCsrf(): void
    {
        $client = static::createClient();
        $this->loginAsAdmin($client);

        // Create a temporary log file to delete
        $projectDir = $client->getContainer()->getParameter('kernel.project_dir');
        $logDir = $projectDir . '/var/log/updates';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        $testFile = 'update-test-delete-' . uniqid() . '.log';
        file_put_contents($logDir . '/' . $testFile, 'test log content');

        $csrfToken = $client->getContainer()->get('security.csrf.token_manager')
            ->getToken('update_manager_delete')->getValue();

        $client->request('POST', '/en/system/update-manager/log/delete', [
            '_token' => $csrfToken,
            'filename' => $testFile,
        ]);

        $this->assertResponseRedirects();
        $this->assertFileDoesNotExist($logDir . '/' . $testFile);
    }

    public function testDownloadBackupReturns404ForNonExistent(): void
    {
        $client = static::createClient();
        $this->loginAsAdmin($client);

        $client->request('GET', '/en/system/update-manager/backup/download/nonexistent.zip');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testDownloadBackupSuccess(): void
    {
        $client = static::createClient();
        $this->loginAsAdmin($client);

        // Create a temporary backup file to download
        $backupManager = $client->getContainer()->get(BackupManager::class);
        $backupDir = $backupManager->getBackupDir();
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }
        $testFile = 'test-download-' . uniqid() . '.zip';
        file_put_contents($backupDir . '/' . $testFile, 'fake zip content');

        $client->request('GET', '/en/system/update-manager/backup/download/' . $testFile);

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-disposition', 'attachment; filename=' . $testFile);

        // Clean up
        @unlink($backupDir . '/' . $testFile);
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

    public function testCreateBackupBlockedWhenLocked(): void
    {
        $client = static::createClient();
        $this->loginAsAdmin($client);

        // Acquire lock to simulate update in progress
        $updateExecutor = $client->getContainer()->get(UpdateExecutor::class);
        $updateExecutor->acquireLock();

        try {
            $csrfToken = $client->getContainer()->get('security.csrf.token_manager')
                ->getToken('update_manager_backup')->getValue();

            $client->request('POST', '/en/system/update-manager/backup', [
                '_token' => $csrfToken,
            ]);

            $this->assertResponseRedirects();
        } finally {
            // Always release lock
            $updateExecutor->releaseLock();
        }
    }
}