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

namespace App\Services\System;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Shivas\VersioningBundle\Service\VersionManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

/**
 * Manages Part-DB backups: creation, restoration, and listing.
 *
 * This service handles all backup-related operations and can be used
 * by the Update Manager, CLI commands, or other services.
 */
readonly class BackupManager
{
    private const BACKUP_DIR = 'var/backups';

    public function __construct(
        #[Autowire(param: 'kernel.project_dir')]
        private string $projectDir,
        private LoggerInterface $logger,
        private Filesystem $filesystem,
        private VersionManagerInterface $versionManager,
        private EntityManagerInterface $entityManager,
        private CommandRunHelper $commandRunHelper,
    ) {
    }

    /**
     * Get the backup directory path.
     */
    public function getBackupDir(): string
    {
        return $this->projectDir . '/' . self::BACKUP_DIR;
    }

    /**
     * Get the current version string for use in filenames.
     */
    private function getCurrentVersionString(): string
    {
        return $this->versionManager->getVersion()->toString();
    }

    /**
     * Create a backup before updating.
     *
     * @param string|null $targetVersion Optional target version for naming
     * @param string|null $prefix Optional prefix for the backup filename
     * @return string The path to the created backup file
     */
    public function createBackup(?string $targetVersion = null, ?string $prefix = 'backup'): string
    {
        $backupDir = $this->getBackupDir();

        if (!is_dir($backupDir)) {
            $this->filesystem->mkdir($backupDir, 0755);
        }

        $currentVersion = $this->getCurrentVersionString();

        // Build filename
        if ($targetVersion) {
            $targetVersionClean = preg_replace('/[^a-zA-Z0-9\.]/', '', $targetVersion);
            $backupFile = $backupDir . '/pre-update-v' . $currentVersion . '-to-' . $targetVersionClean . '-' . date('Y-m-d-His') . '.zip';
        } else {
            $backupFile = $backupDir . '/' . $prefix . '-v' . $currentVersion . '-' . date('Y-m-d-His') . '.zip';
        }

        $this->commandRunHelper->runCommand([
            'php', 'bin/console', 'partdb:backup',
            '--full',
            '--overwrite',
            $backupFile,
        ], 'Create backup', 600);

        $this->logger->info('Created backup', ['file' => $backupFile]);

        return $backupFile;
    }

    /**
     * Get list of backups, that are available, sorted by date descending.
     *
     * @return array<array{file: string, path: string, date: int, size: int}>
     */
    public function getBackups(): array
    {
        $backupDir = $this->getBackupDir();

        if (!is_dir($backupDir)) {
            return [];
        }

        $backups = [];
        foreach (glob($backupDir . '/*.zip') as $backupFile) {
            $backups[] = [
                'file' => basename($backupFile),
                'path' => $backupFile,
                'date' => filemtime($backupFile),
                'size' => filesize($backupFile),
            ];
        }

        // Sort by date descending
        usort($backups, static fn($a, $b) => $b['date'] <=> $a['date']);

        return $backups;
    }

    /**
     * Get details about a specific backup file.
     *
     * @param string $filename The backup filename
     * @return null|array{file: string, path: string, date: int, size: int, from_version: ?string, to_version: ?string, contains_database?: bool, contains_config?: bool, contains_attachments?: bool} Backup details or null if not found
     */
    public function getBackupDetails(string $filename): ?array
    {
        $backupDir = $this->getBackupDir();
        $backupPath = $backupDir . '/' . basename($filename);

        if (!file_exists($backupPath) || !str_ends_with($backupPath, '.zip')) {
            return null;
        }

        // Parse version info from filename: pre-update-v2.5.1-to-v2.5.0-2024-01-30-185400.zip
        $info = [
            'file' => basename($backupPath),
            'path' => $backupPath,
            'date' => filemtime($backupPath),
            'size' => filesize($backupPath),
            'from_version' => null,
            'to_version' => null,
        ];

        if (preg_match('/pre-update-v([\d.]+)-to-v?([\d.]+)-/', $filename, $matches)) {
            $info['from_version'] = $matches[1];
            $info['to_version'] = $matches[2];
        }

        // Check what the backup contains by reading the ZIP
        try {
            $zip = new \ZipArchive();
            if ($zip->open($backupPath) === true) {
                $info['contains_database'] = $zip->locateName('database.sql') !== false || $zip->locateName('var/app.db') !== false;
                $info['contains_config'] = $zip->locateName('.env.local') !== false || $zip->locateName('config/parameters.yaml') !== false;
                $info['contains_attachments'] = $zip->locateName('public/media/') !== false || $zip->locateName('uploads/') !== false;
                $zip->close();
            }
        } catch (\Exception $e) {
            $this->logger->warning('Could not read backup ZIP contents', ['error' => $e->getMessage()]);
        }

        return $info;
    }

    /**
     * Delete a backup file.
     *
     * @param string $filename The backup filename to delete
     * @return bool True if deleted successfully
     */
    public function deleteBackup(string $filename): bool
    {
        $backupDir = $this->getBackupDir();
        $backupPath = $backupDir . '/' . basename($filename);

        if (!file_exists($backupPath) || !str_ends_with($backupPath, '.zip')) {
            return false;
        }

        try {
            $this->filesystem->remove($backupPath);
            $this->logger->info('Deleted backup', ['file' => $filename]);
            return true;
        } catch (\Exception $e) {
            $this->logger->error('Failed to delete backup', ['file' => $filename, 'error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Restore from a backup file.
     *
     * @param string $filename The backup filename to restore
     * @param bool $restoreDatabase Whether to restore the database
     * @param bool $restoreConfig Whether to restore config files
     * @param bool $restoreAttachments Whether to restore attachments
     * @param callable|null $onProgress Callback for progress updates
     * @return array{success: bool, steps: array, error: ?string}
     */
    public function restoreBackup(
        string $filename,
        bool $restoreDatabase = true,
        bool $restoreConfig = false,
        bool $restoreAttachments = false,
        ?callable $onProgress = null
    ): array {
        $steps = [];
        $startTime = microtime(true);

        $log = function (string $step, string $message, bool $success, ?float $duration = null) use (&$steps, $onProgress): void {
            $entry = [
                'step' => $step,
                'message' => $message,
                'success' => $success,
                'timestamp' => (new \DateTime())->format('c'),
                'duration' => $duration,
            ];
            $steps[] = $entry;
            $this->logger->info('[Restore] ' . $step . ': ' . $message, ['success' => $success]);

            if ($onProgress) {
                $onProgress($entry);
            }
        };

        try {
            // Validate backup file
            $backupDir = $this->getBackupDir();
            $backupPath = $backupDir . '/' . basename($filename);

            if (!file_exists($backupPath)) {
                throw new \RuntimeException('Backup file not found: ' . $filename);
            }

            $stepStart = microtime(true);

            // Step 1: Extract backup to temp directory
            $tempDir = sys_get_temp_dir() . '/partdb_restore_' . uniqid();
            $this->filesystem->mkdir($tempDir);

            $zip = new \ZipArchive();
            if ($zip->open($backupPath) !== true) {
                throw new \RuntimeException('Could not open backup ZIP file');
            }
            $zip->extractTo($tempDir);
            $zip->close();
            $log('extract', 'Extracted backup to temporary directory', true, microtime(true) - $stepStart);

            // Step 2: Restore database if requested and present
            if ($restoreDatabase) {
                $stepStart = microtime(true);
                $this->restoreDatabaseFromBackup($tempDir);
                $log('database', 'Restored database', true, microtime(true) - $stepStart);
            }

            // Step 3: Restore config files if requested and present
            if ($restoreConfig) {
                $stepStart = microtime(true);
                $this->restoreConfigFromBackup($tempDir);
                $log('config', 'Restored configuration files', true, microtime(true) - $stepStart);
            }

            // Step 4: Restore attachments if requested and present
            if ($restoreAttachments) {
                $stepStart = microtime(true);
                $this->restoreAttachmentsFromBackup($tempDir);
                $log('attachments', 'Restored attachments', true, microtime(true) - $stepStart);
            }

            // Step 5: Clean up temp directory
            $stepStart = microtime(true);
            $this->filesystem->remove($tempDir);
            $log('cleanup', 'Cleaned up temporary files', true, microtime(true) - $stepStart);

            $totalDuration = microtime(true) - $startTime;
            $log('complete', sprintf('Restore completed successfully in %.1f seconds', $totalDuration), true);

            return [
                'success' => true,
                'steps' => $steps,
                'error' => null,
            ];

        } catch (\Throwable $e) {
            $this->logger->error('Restore failed: ' . $e->getMessage(), [
                'exception' => $e,
                'file' => $filename,
            ]);

            // Try to clean up
            try {
                if (isset($tempDir) && is_dir($tempDir)) {
                    $this->filesystem->remove($tempDir);
                }
            } catch (\Throwable $cleanupError) {
                $this->logger->error('Cleanup after failed restore also failed', ['error' => $cleanupError->getMessage()]);
            }

            return [
                'success' => false,
                'steps' => $steps,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Restore database from backup.
     */
    private function restoreDatabaseFromBackup(string $tempDir): void
    {
        // Get database connection params from Doctrine
        $connection = $this->entityManager->getConnection();
        $params = $connection->getParams();
        $platform = $connection->getDatabasePlatform();

        // Check for SQL dump (MySQL/PostgreSQL)
        $sqlFile = $tempDir . '/database.sql';
        if (file_exists($sqlFile)) {

            if ($platform instanceof AbstractMySQLPlatform) {
                // Use mysql command to import - need to use shell to handle input redirection
                $mysqlCmd = 'mysql';
                if (isset($params['host'])) {
                    $mysqlCmd .= ' -h ' . escapeshellarg($params['host']);
                }
                if (isset($params['port'])) {
                    $mysqlCmd .= ' -P ' . escapeshellarg((string)$params['port']);
                }
                if (isset($params['user'])) {
                    $mysqlCmd .= ' -u ' . escapeshellarg($params['user']);
                }
                if (isset($params['password']) && $params['password']) {
                    $mysqlCmd .= ' -p' . escapeshellarg($params['password']);
                }
                if (isset($params['dbname'])) {
                    $mysqlCmd .= ' ' . escapeshellarg($params['dbname']);
                }
                $mysqlCmd .= ' < ' . escapeshellarg($sqlFile);

                // Execute using shell
                $process = Process::fromShellCommandline($mysqlCmd, $this->projectDir, null, null, 300);
                $process->run();

                if (!$process->isSuccessful()) {
                    throw new \RuntimeException('MySQL import failed: ' . $process->getErrorOutput());
                }
            } elseif ($platform instanceof PostgreSQLPlatform) {
                // Use psql command to import
                $psqlCmd = 'psql';
                if (isset($params['host'])) {
                    $psqlCmd .= ' -h ' . escapeshellarg($params['host']);
                }
                if (isset($params['port'])) {
                    $psqlCmd .= ' -p ' . escapeshellarg((string)$params['port']);
                }
                if (isset($params['user'])) {
                    $psqlCmd .= ' -U ' . escapeshellarg($params['user']);
                }
                if (isset($params['dbname'])) {
                    $psqlCmd .= ' -d ' . escapeshellarg($params['dbname']);
                }
                $psqlCmd .= ' -f ' . escapeshellarg($sqlFile);

                // Set PGPASSWORD environment variable if password is provided
                $env = null;
                if (isset($params['password']) && $params['password']) {
                    $env = ['PGPASSWORD' => $params['password']];
                }

                // Execute using shell
                $process = Process::fromShellCommandline($psqlCmd, $this->projectDir, $env, null, 300);
                $process->run();

                if (!$process->isSuccessful()) {
                    throw new \RuntimeException('PostgreSQL import failed: ' . $process->getErrorOutput());
                }
            } else {
                throw new \RuntimeException('Unsupported database platform for restore');
            }

            return;
        }

        // Check for SQLite database file
        $sqliteFile = $tempDir . '/var/app.db';
        if (file_exists($sqliteFile)) {
            // Use the actual configured SQLite path from Doctrine, not a hardcoded path
            $targetDb = $params['path'] ?? $this->projectDir . '/var/app.db';
            $this->filesystem->copy($sqliteFile, $targetDb, true);
            return;
        }

        $this->logger->warning('No database found in backup');
    }

    /**
     * Restore config files from backup.
     */
    private function restoreConfigFromBackup(string $tempDir): void
    {
        // Restore .env.local
        $envLocal = $tempDir . '/.env.local';
        if (file_exists($envLocal)) {
            $this->filesystem->copy($envLocal, $this->projectDir . '/.env.local', true);
        }

        // Restore config/parameters.yaml
        $parametersYaml = $tempDir . '/config/parameters.yaml';
        if (file_exists($parametersYaml)) {
            $this->filesystem->copy($parametersYaml, $this->projectDir . '/config/parameters.yaml', true);
        }

        // Restore config/banner.md
        $bannerMd = $tempDir . '/config/banner.md';
        if (file_exists($bannerMd)) {
            $this->filesystem->copy($bannerMd, $this->projectDir . '/config/banner.md', true);
        }
    }

    /**
     * Restore attachments from backup.
     */
    private function restoreAttachmentsFromBackup(string $tempDir): void
    {
        // Restore public/media
        $publicMedia = $tempDir . '/public/media';
        if (is_dir($publicMedia)) {
            $this->filesystem->mirror($publicMedia, $this->projectDir . '/public/media', null, ['override' => true]);
        }

        // Restore uploads
        $uploads = $tempDir . '/uploads';
        if (is_dir($uploads)) {
            $this->filesystem->mirror($uploads, $this->projectDir . '/uploads', null, ['override' => true]);
        }
    }
}
