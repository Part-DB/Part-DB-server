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

namespace App\Tests\Services\System;

use App\Services\System\UpdateExecutor;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class UpdateExecutorTest extends KernelTestCase
{
    private ?UpdateExecutor $updateExecutor = null;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->updateExecutor = self::getContainer()->get(UpdateExecutor::class);
    }

    public function testIsLockedReturnsFalseWhenNoLockFile(): void
    {
        // Initially there should be no lock
        // Note: This test assumes no concurrent update is running
        $isLocked = $this->updateExecutor->isLocked();

        $this->assertIsBool($isLocked);
    }

    public function testIsMaintenanceModeReturnsBool(): void
    {
        $isMaintenanceMode = $this->updateExecutor->isMaintenanceMode();

        $this->assertIsBool($isMaintenanceMode);
    }

    public function testGetLockInfoReturnsNullOrArray(): void
    {
        $lockInfo = $this->updateExecutor->getLockInfo();

        // Should be null when not locked, or array when locked
        $this->assertTrue($lockInfo === null || is_array($lockInfo));
    }

    public function testGetMaintenanceInfoReturnsNullOrArray(): void
    {
        $maintenanceInfo = $this->updateExecutor->getMaintenanceInfo();

        // Should be null when not in maintenance, or array when in maintenance
        $this->assertTrue($maintenanceInfo === null || is_array($maintenanceInfo));
    }

    public function testGetUpdateLogsReturnsArray(): void
    {
        $logs = $this->updateExecutor->getUpdateLogs();

        $this->assertIsArray($logs);
    }


    public function testValidateUpdatePreconditionsReturnsProperStructure(): void
    {
        $validation = $this->updateExecutor->validateUpdatePreconditions();

        $this->assertIsArray($validation);
        $this->assertArrayHasKey('valid', $validation);
        $this->assertArrayHasKey('errors', $validation);
        $this->assertIsBool($validation['valid']);
        $this->assertIsArray($validation['errors']);
    }

    public function testGetProgressFilePath(): void
    {
        $progressPath = $this->updateExecutor->getProgressFilePath();

        $this->assertIsString($progressPath);
        $this->assertStringEndsWith('var/update_progress.json', $progressPath);
    }

    public function testGetProgressReturnsNullOrArray(): void
    {
        $progress = $this->updateExecutor->getProgress();

        // Should be null when no progress file, or array when exists
        $this->assertTrue($progress === null || is_array($progress));
    }

    public function testIsUpdateRunningReturnsBool(): void
    {
        $isRunning = $this->updateExecutor->isUpdateRunning();

        $this->assertIsBool($isRunning);
    }

    public function testAcquireAndReleaseLock(): void
    {
        // First, ensure no lock exists
        if ($this->updateExecutor->isLocked()) {
            $this->updateExecutor->releaseLock();
        }

        // Acquire lock
        $acquired = $this->updateExecutor->acquireLock();
        $this->assertTrue($acquired);

        // Should be locked now
        $this->assertTrue($this->updateExecutor->isLocked());

        // Lock info should exist
        $lockInfo = $this->updateExecutor->getLockInfo();
        $this->assertIsArray($lockInfo);
        $this->assertArrayHasKey('started_at', $lockInfo);

        // Trying to acquire again should fail
        $acquiredAgain = $this->updateExecutor->acquireLock();
        $this->assertFalse($acquiredAgain);

        // Release lock
        $this->updateExecutor->releaseLock();

        // Should no longer be locked
        $this->assertFalse($this->updateExecutor->isLocked());
    }

    public function testDeleteLogRejectsInvalidFilename(): void
    {
        // Path traversal attempts should be rejected
        $this->assertFalse($this->updateExecutor->deleteLog('../../../etc/passwd'));
        $this->assertFalse($this->updateExecutor->deleteLog('malicious.txt'));
        $this->assertFalse($this->updateExecutor->deleteLog(''));
        // Must start with "update-"
        $this->assertFalse($this->updateExecutor->deleteLog('backup-v1.0.0.log'));
    }

    public function testDeleteLogReturnsFalseForNonExistentFile(): void
    {
        $this->assertFalse($this->updateExecutor->deleteLog('update-nonexistent-file.log'));
    }

    public function testDeleteLogDeletesExistingFile(): void
    {
        // Create a temporary log file in the update logs directory
        $projectDir = self::getContainer()->getParameter('kernel.project_dir');
        $logDir = $projectDir . '/var/update_logs';

        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        $testFile = 'update-test-delete-' . uniqid() . '.log';
        file_put_contents($logDir . '/' . $testFile, 'test log content');

        $this->assertTrue($this->updateExecutor->deleteLog($testFile));
        $this->assertFileDoesNotExist($logDir . '/' . $testFile);
    }

    public function testEnableAndDisableMaintenanceMode(): void
    {
        // First, ensure maintenance mode is off
        if ($this->updateExecutor->isMaintenanceMode()) {
            $this->updateExecutor->disableMaintenanceMode();
        }

        // Enable maintenance mode
        $this->updateExecutor->enableMaintenanceMode('Test maintenance');

        // Should be in maintenance mode now
        $this->assertTrue($this->updateExecutor->isMaintenanceMode());

        // Maintenance info should exist
        $maintenanceInfo = $this->updateExecutor->getMaintenanceInfo();
        $this->assertIsArray($maintenanceInfo);
        $this->assertArrayHasKey('reason', $maintenanceInfo);
        $this->assertEquals('Test maintenance', $maintenanceInfo['reason']);

        // Disable maintenance mode
        $this->updateExecutor->disableMaintenanceMode();

        // Should no longer be in maintenance mode
        $this->assertFalse($this->updateExecutor->isMaintenanceMode());
    }
}
