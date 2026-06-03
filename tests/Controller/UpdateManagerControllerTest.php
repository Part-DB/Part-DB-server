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

    /**
     * Extract a CSRF token from the rendered update manager page.
     */
    private function getCsrfTokenFromPage($crawler, string $formAction): string
    {
        $form = $crawler->filter('form[action*="' . $formAction . '"]');
        if ($form->count() === 0) {
            $this->fail('Form with action containing "' . $formAction . '" not found on page');
        }

        return $form->filter('input[name="_token"]')->attr('value');
    }

    // ---- Authentication tests ----

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

    // ---- Backup creation tests ----

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

        // Load the page and extract CSRF token from the backup form
        $crawler = $client->request('GET', '/en/system/update-manager');
        $csrfToken = $this->getCsrfTokenFromPage($crawler, 'backup');

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

    public function testCreateBackupBlockedWhenLocked(): void
    {
        $client = static::createClient();
        $this->loginAsAdmin($client);

        // Load the page first to get CSRF token before locking
        $crawler = $client->request('GET', '/en/system/update-manager');
        $csrfToken = $this->getCsrfTokenFromPage($crawler, 'backup');

        // Acquire lock to simulate update in progress
        $updateExecutor = $client->getContainer()->get(UpdateExecutor::class);
        $updateExecutor->acquireLock();

        try {
            $client->request('POST', '/en/system/update-manager/backup', [
                '_token' => $csrfToken,
            ]);

            $this->assertResponseRedirects();
        } finally {
            // Always release lock
            $updateExecutor->releaseLock();
        }
    }

    // ---- Backup deletion tests ----

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

        // Create a temporary backup file so the page shows the delete form
        $backupManager = $client->getContainer()->get(BackupManager::class);
        $backupDir = $backupManager->getBackupDir();
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }
        $testFile = 'test-delete-' . uniqid() . '.zip';
        file_put_contents($backupDir . '/' . $testFile, 'test');

        // Load the page and extract CSRF token from the delete form
        $crawler = $client->request('GET', '/en/system/update-manager');
        $csrfToken = $this->getCsrfTokenFromPage($crawler, 'backup/delete');

        $client->request('POST', '/en/system/update-manager/backup/delete', [
            '_token' => $csrfToken,
            'filename' => $testFile,
        ]);

        $this->assertResponseRedirects();
        $this->assertFileDoesNotExist($backupDir . '/' . $testFile);
    }

    // ---- Log deletion tests ----

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

        // Create a temporary log file so the page shows the delete form
        $projectDir = $client->getContainer()->getParameter('kernel.project_dir');
        $logDir = $projectDir . '/var/log/updates';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        $testFile = 'update-test-delete-' . uniqid() . '.log';
        file_put_contents($logDir . '/' . $testFile, 'test log content');

        // Load the page and extract CSRF token from the log delete form
        $crawler = $client->request('GET', '/en/system/update-manager');
        $csrfToken = $this->getCsrfTokenFromPage($crawler, 'log/delete');

        $client->request('POST', '/en/system/update-manager/log/delete', [
            '_token' => $csrfToken,
            'filename' => $testFile,
        ]);

        $this->assertResponseRedirects();
        $this->assertFileDoesNotExist($logDir . '/' . $testFile);
    }

    // ---- Backup download tests ----

    public function testDownloadBackupBlockedByDefault(): void
    {
        $client = static::createClient();
        $this->loginAsAdmin($client);

        // DISABLE_BACKUP_DOWNLOAD=1 is the default in .env, so this should return 403
        $client->request('POST', '/en/system/update-manager/backup/download', [
            '_token' => 'any',
            'filename' => 'test.zip',
            'password' => 'test',
        ]);

        $this->assertResponseStatusCodeSame(403);
    }

    public function testDownloadBackupRequiresPost(): void
    {
        $client = static::createClient();
        $this->loginAsAdmin($client);

        // GET returns 404 since no GET route exists for this path
        $client->request('GET', '/en/system/update-manager/backup/download');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testDownloadBackupRequiresAuth(): void
    {
        $client = static::createClient();

        $client->request('POST', '/en/system/update-manager/backup/download', [
            '_token' => 'any',
            'filename' => 'test.zip',
            'password' => 'test',
        ]);

        // Should deny access (401 with HTTP Basic auth in test env)
        $this->assertResponseStatusCodeSame(401);
    }

    // ---- Backup details tests ----

    public function testBackupDetailsReturns404ForNonExistent(): void
    {
        $client = static::createClient();
        $this->loginAsAdmin($client);

        $client->request('GET', '/en/system/update-manager/backup/nonexistent.zip');

        $this->assertResponseStatusCodeSame(404);
    }

    // ---- Restore tests ----

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

    public function testRestoreRequiresAuth(): void
    {
        $client = static::createClient();

        $client->request('POST', '/en/system/update-manager/restore', [
            '_token' => 'invalid',
            'filename' => 'test.zip',
        ]);

        $this->assertResponseStatusCodeSame(401);
    }

    // ---- Start update tests ----

    public function testStartUpdateRequiresAuth(): void
    {
        $client = static::createClient();

        $client->request('POST', '/en/system/update-manager/start', [
            '_token' => 'invalid',
            'version' => 'v1.0.0',
        ]);

        $this->assertResponseStatusCodeSame(401);
    }

    public function testStartUpdateBlockedWhenWebUpdatesDisabled(): void
    {
        $client = static::createClient();
        $this->loginAsAdmin($client);

        // DISABLE_WEB_UPDATES=1 is the default in .env
        $client->request('POST', '/en/system/update-manager/start', [
            '_token' => 'invalid',
            'version' => 'v1.0.0',
        ]);

        $this->assertResponseStatusCodeSame(403);
    }

    // ---- Status and progress tests ----

    public function testStatusEndpointRequiresAuth(): void
    {
        $client = static::createClient();

        $client->request('GET', '/en/system/update-manager/status');

        $this->assertResponseStatusCodeSame(401);
    }

    public function testStatusEndpointAccessibleByAdmin(): void
    {
        $client = static::createClient();
        $this->loginAsAdmin($client);

        $client->request('GET', '/en/system/update-manager/status');

        $this->assertResponseIsSuccessful();
    }

    public function testProgressStatusEndpointRequiresAuth(): void
    {
        $client = static::createClient();

        $client->request('GET', '/en/system/update-manager/progress/status');

        $this->assertResponseStatusCodeSame(401);
    }

    public function testProgressStatusEndpointAccessibleByAdmin(): void
    {
        $client = static::createClient();
        $this->loginAsAdmin($client);

        $client->request('GET', '/en/system/update-manager/progress/status');

        $this->assertResponseIsSuccessful();
    }
}
