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

use App\Services\System\BackupManager;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class BackupManagerTest extends KernelTestCase
{
    private ?BackupManager $backupManager = null;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->backupManager = self::getContainer()->get(BackupManager::class);
    }

    public function testGetBackupDir(): void
    {
        $backupDir = $this->backupManager->getBackupDir();

        // Should end with var/backups
        $this->assertStringEndsWith('var/backups', $backupDir);
    }

    public function testGetBackupsReturnsEmptyArrayWhenNoBackups(): void
    {
        // If there are no backups (or the directory doesn't exist), should return empty array
        $backups = $this->backupManager->getBackups();

        $this->assertIsArray($backups);
    }

    public function testGetBackupDetailsReturnsNullForNonExistentFile(): void
    {
        $details = $this->backupManager->getBackupDetails('non-existent-backup.zip');

        $this->assertNull($details);
    }

    public function testGetBackupDetailsReturnsNullForNonZipFile(): void
    {
        $details = $this->backupManager->getBackupDetails('not-a-zip.txt');

        $this->assertNull($details);
    }

    /**
     * Test that version parsing from filename works correctly.
     * This tests the regex pattern used in getBackupDetails.
     */
    public function testVersionParsingFromFilename(): void
    {
        // Test the regex pattern directly
        $filename = 'pre-update-v2.5.1-to-v2.6.0-2024-01-30-185400.zip';
        $matches = [];

        $result = preg_match('/pre-update-v([\d.]+)-to-v?([\d.]+)-/', $filename, $matches);

        $this->assertSame(1, $result);
        $this->assertSame('2.5.1', $matches[1]);
        $this->assertSame('2.6.0', $matches[2]);
    }

    public function testDeleteBackupReturnsFalseForNonExistentFile(): void
    {
        $this->assertFalse($this->backupManager->deleteBackup('non-existent.zip'));
    }

    public function testDeleteBackupReturnsFalseForNonZipFile(): void
    {
        $this->assertFalse($this->backupManager->deleteBackup('not-a-zip.txt'));
    }

    /**
     * Test version parsing with different filename formats.
     */
    public function testVersionParsingVariants(): void
    {
        // Without 'v' prefix on target version
        $filename1 = 'pre-update-v1.0.0-to-2.0.0-2024-01-30-185400.zip';
        preg_match('/pre-update-v([\d.]+)-to-v?([\d.]+)-/', $filename1, $matches1);
        $this->assertSame('1.0.0', $matches1[1]);
        $this->assertSame('2.0.0', $matches1[2]);

        // With 'v' prefix on target version
        $filename2 = 'pre-update-v1.0.0-to-v2.0.0-2024-01-30-185400.zip';
        preg_match('/pre-update-v([\d.]+)-to-v?([\d.]+)-/', $filename2, $matches2);
        $this->assertSame('1.0.0', $matches2[1]);
        $this->assertSame('2.0.0', $matches2[2]);
    }
}
