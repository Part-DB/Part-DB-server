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

use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Shivas\VersioningBundle\Service\VersionManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

/**
 * Handles the execution of Part-DB updates with safety mechanisms.
 *
 * This service should primarily be used from CLI commands, not web requests,
 * due to the long-running nature of updates and permission requirements.
 */
class UpdateExecutor
{
    private const LOCK_FILE = 'var/update.lock';
    private const MAINTENANCE_FILE = 'var/maintenance.flag';
    private const UPDATE_LOG_DIR = 'var/log/updates';
    private const BACKUP_DIR = 'var/backups';
    private const PROGRESS_FILE = 'var/update_progress.json';

    /** @var array<array{step: string, message: string, success: bool, timestamp: string, duration: ?float}> */
    private array $steps = [];

    private ?string $currentLogFile = null;

    public function __construct(#[Autowire(param: 'kernel.project_dir')] private readonly string $project_dir,
        private readonly LoggerInterface $logger, private readonly Filesystem $filesystem,
        private readonly InstallationTypeDetector $installationTypeDetector,
        private readonly VersionManagerInterface $versionManager,
        private readonly EntityManagerInterface $entityManager)
    {

    }

    /**
     * Get the current version string for use in filenames.
     */
    private function getCurrentVersionString(): string
    {
        return $this->versionManager->getVersion()->toString();
    }

    /**
     * Check if an update is currently in progress.
     */
    public function isLocked(): bool
    {
        $lockFile = $this->project_dir . '/' . self::LOCK_FILE;

        if (!file_exists($lockFile)) {
            return false;
        }

        // Check if lock is stale (older than 1 hour)
        $lockData = json_decode(file_get_contents($lockFile), true);
        if ($lockData && isset($lockData['started_at'])) {
            $startedAt = new \DateTime($lockData['started_at']);
            $now = new \DateTime();
            $diff = $now->getTimestamp() - $startedAt->getTimestamp();

            // If lock is older than 1 hour, consider it stale
            if ($diff > 3600) {
                $this->logger->warning('Found stale update lock, removing it');
                $this->releaseLock();
                return false;
            }
        }

        return true;
    }

    /**
     * Get lock information.
     */
    public function getLockInfo(): ?array
    {
        $lockFile = $this->project_dir . '/' . self::LOCK_FILE;

        if (!file_exists($lockFile)) {
            return null;
        }

        return json_decode(file_get_contents($lockFile), true);
    }

    /**
     * Check if maintenance mode is enabled.
     */
    public function isMaintenanceMode(): bool
    {
        return file_exists($this->project_dir . '/' . self::MAINTENANCE_FILE);
    }

    /**
     * Get maintenance mode information.
     */
    public function getMaintenanceInfo(): ?array
    {
        $maintenanceFile = $this->project_dir . '/' . self::MAINTENANCE_FILE;

        if (!file_exists($maintenanceFile)) {
            return null;
        }

        return json_decode(file_get_contents($maintenanceFile), true);
    }

    /**
     * Acquire an exclusive lock for the update process.
     */
    public function acquireLock(): bool
    {
        if ($this->isLocked()) {
            return false;
        }

        $lockFile = $this->project_dir . '/' . self::LOCK_FILE;
        $lockDir = dirname($lockFile);

        if (!is_dir($lockDir)) {
            $this->filesystem->mkdir($lockDir);
        }

        $lockData = [
            'started_at' => (new \DateTime())->format('c'),
            'pid' => getmypid(),
            'user' => get_current_user(),
        ];

        $this->filesystem->dumpFile($lockFile, json_encode($lockData, JSON_PRETTY_PRINT));

        return true;
    }

    /**
     * Release the update lock.
     */
    public function releaseLock(): void
    {
        $lockFile = $this->project_dir . '/' . self::LOCK_FILE;

        if (file_exists($lockFile)) {
            $this->filesystem->remove($lockFile);
        }
    }

    /**
     * Enable maintenance mode to block user access during update.
     */
    public function enableMaintenanceMode(string $reason = 'Update in progress'): void
    {
        $maintenanceFile = $this->project_dir . '/' . self::MAINTENANCE_FILE;
        $maintenanceDir = dirname($maintenanceFile);

        if (!is_dir($maintenanceDir)) {
            $this->filesystem->mkdir($maintenanceDir);
        }

        $data = [
            'enabled_at' => (new \DateTime())->format('c'),
            'reason' => $reason,
        ];

        $this->filesystem->dumpFile($maintenanceFile, json_encode($data, JSON_PRETTY_PRINT));
    }

    /**
     * Disable maintenance mode.
     */
    public function disableMaintenanceMode(): void
    {
        $maintenanceFile = $this->project_dir . '/' . self::MAINTENANCE_FILE;

        if (file_exists($maintenanceFile)) {
            $this->filesystem->remove($maintenanceFile);
        }
    }

    /**
     * Validate that we can perform an update.
     *
     * @return array{valid: bool, errors: array<string>}
     */
    public function validateUpdatePreconditions(): array
    {
        $errors = [];

        // Check installation type
        $installType = $this->installationTypeDetector->detect();
        if (!$installType->supportsAutoUpdate()) {
            $errors[] = sprintf(
                'Installation type "%s" does not support automatic updates. %s',
                $installType->getLabel(),
                $installType->getUpdateInstructions()
            );
        }

        // Check for Git installation
        if ($installType === InstallationType::GIT) {
            // Check if git is available
            $process = new Process(['git', '--version']);
            $process->run();
            if (!$process->isSuccessful()) {
                $errors[] = 'Git command not found. Please ensure Git is installed and in PATH.';
            }

            // Check for local changes
            $process = new Process(['git', 'status', '--porcelain'], $this->project_dir);
            $process->run();
            if (!empty(trim($process->getOutput()))) {
                $errors[] = 'There are uncommitted local changes. Please commit or stash them before updating.';
            }
        }

        // Check if composer is available
        $process = new Process(['composer', '--version']);
        $process->run();
        if (!$process->isSuccessful()) {
            $errors[] = 'Composer command not found. Please ensure Composer is installed and in PATH.';
        }

        // Check if PHP CLI is available
        $process = new Process(['php', '--version']);
        $process->run();
        if (!$process->isSuccessful()) {
            $errors[] = 'PHP CLI not found. Please ensure PHP is installed and in PATH.';
        }

        // Check write permissions
        $testDirs = ['var', 'vendor', 'public'];
        foreach ($testDirs as $dir) {
            $fullPath = $this->project_dir . '/' . $dir;
            if (is_dir($fullPath) && !is_writable($fullPath)) {
                $errors[] = sprintf('Directory "%s" is not writable.', $dir);
            }
        }

        // Check if already locked
        if ($this->isLocked()) {
            $lockInfo = $this->getLockInfo();
            $errors[] = sprintf(
                'An update is already in progress (started at %s).',
                $lockInfo['started_at'] ?? 'unknown time'
            );
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Execute the update to a specific version.
     *
     * @param string $targetVersion The target version/tag to update to (e.g., "v2.6.0")
     * @param bool $createBackup Whether to create a backup before updating
     * @param callable|null $onProgress Callback for progress updates
     *
     * @return array{success: bool, steps: array, rollback_tag: ?string, error: ?string, log_file: ?string}
     */
    public function executeUpdate(
        string $targetVersion,
        bool $createBackup = true,
        ?callable $onProgress = null
    ): array {
        $this->steps = [];
        $rollbackTag = null;
        $startTime = microtime(true);

        // Initialize log file
        $this->initializeLogFile($targetVersion);

        $log = function (string $step, string $message, bool $success = true, ?float $duration = null) use ($onProgress): void {
            $entry = [
                'step' => $step,
                'message' => $message,
                'success' => $success,
                'timestamp' => (new \DateTime())->format('c'),
                'duration' => $duration,
            ];

            $this->steps[] = $entry;
            $this->writeToLogFile($entry);
            $this->logger->info("Update [{$step}]: {$message}", ['success' => $success]);

            if ($onProgress) {
                $onProgress($entry);
            }
        };

        try {
            // Validate preconditions
            $validation = $this->validateUpdatePreconditions();
            if (!$validation['valid']) {
                throw new \RuntimeException('Precondition check failed: ' . implode('; ', $validation['errors']));
            }

            // Step 1: Acquire lock
            $stepStart = microtime(true);
            if (!$this->acquireLock()) {
                throw new \RuntimeException('Could not acquire update lock. Another update may be in progress.');
            }
            $log('lock', 'Acquired exclusive update lock', true, microtime(true) - $stepStart);

            // Step 2: Enable maintenance mode
            $stepStart = microtime(true);
            $this->enableMaintenanceMode('Updating to ' . $targetVersion);
            $log('maintenance', 'Enabled maintenance mode', true, microtime(true) - $stepStart);

            // Step 3: Create rollback point with version info
            $stepStart = microtime(true);
            $currentVersion = $this->getCurrentVersionString();
            $targetVersionClean = preg_replace('/[^a-zA-Z0-9\.]/', '', $targetVersion);
            $rollbackTag = 'pre-update-v' . $currentVersion . '-to-' . $targetVersionClean . '-' . date('Y-m-d-His');
            $this->runCommand(['git', 'tag', $rollbackTag], 'Create rollback tag');
            $log('rollback_tag', 'Created rollback tag: ' . $rollbackTag, true, microtime(true) - $stepStart);

            // Step 4: Create backup (optional)
            if ($createBackup) {
                $stepStart = microtime(true);
                $backupFile = $this->createBackup($targetVersion);
                $log('backup', 'Created backup: ' . basename($backupFile), true, microtime(true) - $stepStart);
            }

            // Step 5: Fetch from remote
            $stepStart = microtime(true);
            $this->runCommand(['git', 'fetch', '--tags', '--force', 'origin'], 'Fetch from origin', 120);
            $log('fetch', 'Fetched latest changes and tags from origin', true, microtime(true) - $stepStart);

            // Step 6: Checkout target version
            $stepStart = microtime(true);
            $this->runCommand(['git', 'checkout', $targetVersion], 'Checkout version');
            $log('checkout', 'Checked out version: ' . $targetVersion, true, microtime(true) - $stepStart);

            // Step 7: Install dependencies
            $stepStart = microtime(true);
            $this->runCommand([
                'composer', 'install',
                '--no-dev',
                '--optimize-autoloader',
                '--no-interaction',
                '--no-progress',
            ], 'Install dependencies', 600);
            $log('composer', 'Installed/updated dependencies', true, microtime(true) - $stepStart);

            // Step 8: Run database migrations
            $stepStart = microtime(true);
            $this->runCommand([
                'php', 'bin/console', 'doctrine:migrations:migrate',
                '--no-interaction',
                '--allow-no-migration',
            ], 'Run migrations', 300);
            $log('migrations', 'Database migrations completed', true, microtime(true) - $stepStart);

            // Step 9: Clear cache
            $stepStart = microtime(true);
            $this->runCommand([
                'php', 'bin/console', 'cache:clear',
                '--env=prod',
                '--no-interaction',
            ], 'Clear cache', 120);
            $log('cache_clear', 'Cleared application cache', true, microtime(true) - $stepStart);

            // Step 10: Warm up cache
            $stepStart = microtime(true);
            $this->runCommand([
                'php', 'bin/console', 'cache:warmup',
                '--env=prod',
            ], 'Warmup cache', 120);
            $log('cache_warmup', 'Warmed up application cache', true, microtime(true) - $stepStart);

            // Step 11: Disable maintenance mode
            $stepStart = microtime(true);
            $this->disableMaintenanceMode();
            $log('maintenance_off', 'Disabled maintenance mode', true, microtime(true) - $stepStart);

            // Step 12: Release lock
            $stepStart = microtime(true);
            $this->releaseLock();

            $totalDuration = microtime(true) - $startTime;
            $log('complete', sprintf('Update completed successfully in %.1f seconds', $totalDuration), true, microtime(true) - $stepStart);

            return [
                'success' => true,
                'steps' => $this->steps,
                'rollback_tag' => $rollbackTag,
                'error' => null,
                'log_file' => $this->currentLogFile,
                'duration' => $totalDuration,
            ];

        } catch (\Exception $e) {
            $log('error', 'Update failed: ' . $e->getMessage(), false);

            // Attempt rollback
            if ($rollbackTag) {
                try {
                    $this->runCommand(['git', 'checkout', $rollbackTag], 'Rollback');
                    $log('rollback', 'Rolled back to: ' . $rollbackTag, true);

                    // Re-run composer install after rollback
                    $this->runCommand([
                        'composer', 'install',
                        '--no-dev',
                        '--optimize-autoloader',
                        '--no-interaction',
                    ], 'Reinstall dependencies after rollback', 600);
                    $log('rollback_composer', 'Reinstalled dependencies after rollback', true);

                    // Clear cache after rollback
                    $this->runCommand([
                        'php', 'bin/console', 'cache:clear',
                        '--env=prod',
                    ], 'Clear cache after rollback', 120);
                    $log('rollback_cache', 'Cleared cache after rollback', true);

                } catch (\Exception $rollbackError) {
                    $log('rollback_failed', 'Rollback failed: ' . $rollbackError->getMessage(), false);
                }
            }

            // Clean up
            $this->disableMaintenanceMode();
            $this->releaseLock();

            return [
                'success' => false,
                'steps' => $this->steps,
                'rollback_tag' => $rollbackTag,
                'error' => $e->getMessage(),
                'log_file' => $this->currentLogFile,
                'duration' => microtime(true) - $startTime,
            ];
        }
    }

    /**
     * Create a backup before updating.
     */
    private function createBackup(string $targetVersion): string
    {
        $backupDir = $this->project_dir . '/' . self::BACKUP_DIR;

        if (!is_dir($backupDir)) {
            $this->filesystem->mkdir($backupDir, 0755);
        }

        // Include version numbers in backup filename: pre-update-v2.5.1-to-v2.6.0-2024-01-30-185400.zip
        $currentVersion = $this->getCurrentVersionString();
        $targetVersionClean = preg_replace('/[^a-zA-Z0-9\.]/', '', $targetVersion);
        $backupFile = $backupDir . '/pre-update-v' . $currentVersion . '-to-' . $targetVersionClean . '-' . date('Y-m-d-His') . '.zip';

        $this->runCommand([
            'php', 'bin/console', 'partdb:backup',
            '--full',
            '--overwrite',
            $backupFile,
        ], 'Create backup', 600);

        return $backupFile;
    }

    /**
     * Run a shell command with proper error handling.
     */
    private function runCommand(array $command, string $description, int $timeout = 120): string
    {
        $process = new Process($command, $this->project_dir);
        $process->setTimeout($timeout);

        // Set environment variables needed for Composer and other tools
        // This is especially important when running as www-data which may not have HOME set
        // We inherit from current environment and override/add specific variables
        $currentEnv = getenv();
        if (!is_array($currentEnv)) {
            $currentEnv = [];
        }
        $env = array_merge($currentEnv, [
            'HOME' => $this->project_dir,
            'COMPOSER_HOME' => $this->project_dir . '/var/composer',
            'PATH' => getenv('PATH') ?: '/usr/local/bin:/usr/bin:/bin',
        ]);
        $process->setEnv($env);

        $output = '';
        $process->run(function ($type, $buffer) use (&$output) {
            $output .= $buffer;
        });

        if (!$process->isSuccessful()) {
            $errorOutput = $process->getErrorOutput() ?: $process->getOutput();
            throw new \RuntimeException(
                sprintf('%s failed: %s', $description, trim($errorOutput))
            );
        }

        return $output;
    }

    /**
     * Initialize the log file for this update.
     */
    private function initializeLogFile(string $targetVersion): void
    {
        $logDir = $this->project_dir . '/' . self::UPDATE_LOG_DIR;

        if (!is_dir($logDir)) {
            $this->filesystem->mkdir($logDir, 0755);
        }

        // Include version numbers in log filename: update-v2.5.1-to-v2.6.0-2024-01-30-185400.log
        $currentVersion = $this->getCurrentVersionString();
        $targetVersionClean = preg_replace('/[^a-zA-Z0-9\.]/', '', $targetVersion);
        $this->currentLogFile = $logDir . '/update-v' . $currentVersion . '-to-' . $targetVersionClean . '-' . date('Y-m-d-His') . '.log';

        $header = sprintf(
            "Part-DB Update Log\n" .
            "==================\n" .
            "Started: %s\n" .
            "From Version: %s\n" .
            "Target Version: %s\n" .
            "==================\n\n",
            date('Y-m-d H:i:s'),
            $currentVersion,
            $targetVersion
        );

        file_put_contents($this->currentLogFile, $header);
    }

    /**
     * Write an entry to the log file.
     */
    private function writeToLogFile(array $entry): void
    {
        if (!$this->currentLogFile) {
            return;
        }

        $line = sprintf(
            "[%s] %s: %s%s\n",
            $entry['timestamp'],
            strtoupper($entry['step']),
            $entry['message'],
            $entry['duration'] ? sprintf(' (%.2fs)', $entry['duration']) : ''
        );

        file_put_contents($this->currentLogFile, $line, FILE_APPEND);
    }

    /**
     * Get list of update log files.
     */
    public function getUpdateLogs(): array
    {
        $logDir = $this->project_dir . '/' . self::UPDATE_LOG_DIR;

        if (!is_dir($logDir)) {
            return [];
        }

        $logs = [];
        foreach (glob($logDir . '/update-*.log') as $logFile) {
            $logs[] = [
                'file' => basename($logFile),
                'path' => $logFile,
                'date' => filemtime($logFile),
                'size' => filesize($logFile),
            ];
        }

        // Sort by date descending
        usort($logs, fn($a, $b) => $b['date'] <=> $a['date']);

        return $logs;
    }

    /**
     * Get list of backups.
     */
    public function getBackups(): array
    {
        $backupDir = $this->project_dir . '/' . self::BACKUP_DIR;

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
        usort($backups, fn($a, $b) => $b['date'] <=> $a['date']);

        return $backups;
    }

    /**
     * Get details about a specific backup file.
     *
     * @param string $filename The backup filename
     * @return array|null Backup details or null if not found
     */
    public function getBackupDetails(string $filename): ?array
    {
        $backupDir = $this->project_dir . '/' . self::BACKUP_DIR;
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
        $this->steps = [];
        $startTime = microtime(true);

        $log = function (string $step, string $message, bool $success, ?float $duration = null) use ($onProgress): void {
            $entry = [
                'step' => $step,
                'message' => $message,
                'success' => $success,
                'timestamp' => (new \DateTime())->format('c'),
                'duration' => $duration,
            ];
            $this->steps[] = $entry;
            $this->logger->info('[Restore] ' . $step . ': ' . $message, ['success' => $success]);

            if ($onProgress) {
                $onProgress($entry);
            }
        };

        try {
            // Validate backup file
            $backupDir = $this->project_dir . '/' . self::BACKUP_DIR;
            $backupPath = $backupDir . '/' . basename($filename);

            if (!file_exists($backupPath)) {
                throw new \RuntimeException('Backup file not found: ' . $filename);
            }

            $stepStart = microtime(true);

            // Step 1: Acquire lock
            $this->acquireLock('restore');
            $log('lock', 'Acquired exclusive restore lock', true, microtime(true) - $stepStart);

            // Step 2: Enable maintenance mode
            $stepStart = microtime(true);
            $this->enableMaintenanceMode('Restoring from backup...');
            $log('maintenance', 'Enabled maintenance mode', true, microtime(true) - $stepStart);

            // Step 3: Extract backup to temp directory
            $stepStart = microtime(true);
            $tempDir = sys_get_temp_dir() . '/partdb_restore_' . uniqid();
            $this->filesystem->mkdir($tempDir);

            $zip = new \ZipArchive();
            if ($zip->open($backupPath) !== true) {
                throw new \RuntimeException('Could not open backup ZIP file');
            }
            $zip->extractTo($tempDir);
            $zip->close();
            $log('extract', 'Extracted backup to temporary directory', true, microtime(true) - $stepStart);

            // Step 4: Restore database if requested and present
            if ($restoreDatabase) {
                $stepStart = microtime(true);
                $this->restoreDatabaseFromBackup($tempDir);
                $log('database', 'Restored database', true, microtime(true) - $stepStart);
            }

            // Step 5: Restore config files if requested and present
            if ($restoreConfig) {
                $stepStart = microtime(true);
                $this->restoreConfigFromBackup($tempDir);
                $log('config', 'Restored configuration files', true, microtime(true) - $stepStart);
            }

            // Step 6: Restore attachments if requested and present
            if ($restoreAttachments) {
                $stepStart = microtime(true);
                $this->restoreAttachmentsFromBackup($tempDir);
                $log('attachments', 'Restored attachments', true, microtime(true) - $stepStart);
            }

            // Step 7: Clean up temp directory
            $stepStart = microtime(true);
            $this->filesystem->remove($tempDir);
            $log('cleanup', 'Cleaned up temporary files', true, microtime(true) - $stepStart);

            // Step 8: Clear cache
            $stepStart = microtime(true);
            $this->runCommand(['php', 'bin/console', 'cache:clear', '--no-warmup'], 'Clear cache');
            $log('cache_clear', 'Cleared application cache', true, microtime(true) - $stepStart);

            // Step 9: Warm up cache
            $stepStart = microtime(true);
            $this->runCommand(['php', 'bin/console', 'cache:warmup'], 'Warm up cache');
            $log('cache_warmup', 'Warmed up application cache', true, microtime(true) - $stepStart);

            // Step 10: Disable maintenance mode
            $stepStart = microtime(true);
            $this->disableMaintenanceMode();
            $log('maintenance_off', 'Disabled maintenance mode', true, microtime(true) - $stepStart);

            // Step 11: Release lock
            $this->releaseLock();

            $totalDuration = microtime(true) - $startTime;
            $log('complete', sprintf('Restore completed successfully in %.1f seconds', $totalDuration), true, microtime(true) - $stepStart);

            return [
                'success' => true,
                'steps' => $this->steps,
                'error' => null,
            ];

        } catch (\Throwable $e) {
            $this->logger->error('Restore failed: ' . $e->getMessage(), [
                'exception' => $e,
                'file' => $filename,
            ]);

            // Try to clean up
            try {
                $this->disableMaintenanceMode();
                $this->releaseLock();
                if (isset($tempDir) && is_dir($tempDir)) {
                    $this->filesystem->remove($tempDir);
                }
            } catch (\Throwable $cleanupError) {
                $this->logger->error('Cleanup after failed restore also failed', ['error' => $cleanupError->getMessage()]);
            }

            return [
                'success' => false,
                'steps' => $this->steps,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Restore database from backup.
     */
    private function restoreDatabaseFromBackup(string $tempDir): void
    {
        // Check for SQL dump (MySQL/PostgreSQL)
        $sqlFile = $tempDir . '/database.sql';
        if (file_exists($sqlFile)) {
            // Import SQL using mysql/psql command directly
            // First, get database connection params from Doctrine
            $connection = $this->entityManager->getConnection();
            $params = $connection->getParams();
            $platform = $connection->getDatabasePlatform();

            if ($platform instanceof \Doctrine\DBAL\Platforms\AbstractMySQLPlatform) {
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
                $process = Process::fromShellCommandline($mysqlCmd, $this->project_dir, null, null, 300);
                $process->run();

                if (!$process->isSuccessful()) {
                    throw new \RuntimeException('MySQL import failed: ' . $process->getErrorOutput());
                }
            } elseif ($platform instanceof \Doctrine\DBAL\Platforms\PostgreSQLPlatform) {
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
                $process = Process::fromShellCommandline($psqlCmd, $this->project_dir, $env, null, 300);
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
            $targetDb = $this->project_dir . '/var/app.db';
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
            $this->filesystem->copy($envLocal, $this->project_dir . '/.env.local', true);
        }

        // Restore config/parameters.yaml
        $parametersYaml = $tempDir . '/config/parameters.yaml';
        if (file_exists($parametersYaml)) {
            $this->filesystem->copy($parametersYaml, $this->project_dir . '/config/parameters.yaml', true);
        }

        // Restore config/banner.md
        $bannerMd = $tempDir . '/config/banner.md';
        if (file_exists($bannerMd)) {
            $this->filesystem->copy($bannerMd, $this->project_dir . '/config/banner.md', true);
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
            $this->filesystem->mirror($publicMedia, $this->project_dir . '/public/media', null, ['override' => true]);
        }

        // Restore uploads
        $uploads = $tempDir . '/uploads';
        if (is_dir($uploads)) {
            $this->filesystem->mirror($uploads, $this->project_dir . '/uploads', null, ['override' => true]);
        }
    }

    /**
     * Get the path to the progress file.
     */
    public function getProgressFilePath(): string
    {
        return $this->project_dir . '/' . self::PROGRESS_FILE;
    }

    /**
     * Save progress to file for web UI polling.
     */
    public function saveProgress(array $progress): void
    {
        $progressFile = $this->getProgressFilePath();
        $progressDir = dirname($progressFile);

        if (!is_dir($progressDir)) {
            $this->filesystem->mkdir($progressDir);
        }

        $this->filesystem->dumpFile($progressFile, json_encode($progress, JSON_PRETTY_PRINT));
    }

    /**
     * Get current update progress from file.
     */
    public function getProgress(): ?array
    {
        $progressFile = $this->getProgressFilePath();

        if (!file_exists($progressFile)) {
            return null;
        }

        $data = json_decode(file_get_contents($progressFile), true);

        // If the progress file is stale (older than 30 minutes), consider it invalid
        if ($data && isset($data['started_at'])) {
            $startedAt = strtotime($data['started_at']);
            if (time() - $startedAt > 1800) {
                $this->clearProgress();
                return null;
            }
        }

        return $data;
    }

    /**
     * Clear progress file.
     */
    public function clearProgress(): void
    {
        $progressFile = $this->getProgressFilePath();

        if (file_exists($progressFile)) {
            $this->filesystem->remove($progressFile);
        }
    }

    /**
     * Check if an update is currently running (based on progress file).
     */
    public function isUpdateRunning(): bool
    {
        $progress = $this->getProgress();

        if (!$progress) {
            return false;
        }

        return isset($progress['status']) && $progress['status'] === 'running';
    }

    /**
     * Start the update process in the background.
     * Returns the process ID or null on failure.
     */
    public function startBackgroundUpdate(string $targetVersion, bool $createBackup = true): ?int
    {
        // Validate first
        $validation = $this->validateUpdatePreconditions();
        if (!$validation['valid']) {
            $this->logger->error('Update validation failed', ['errors' => $validation['errors']]);
            return null;
        }

        // Initialize progress file
        $this->saveProgress([
            'status' => 'starting',
            'target_version' => $targetVersion,
            'create_backup' => $createBackup,
            'started_at' => (new \DateTime())->format('c'),
            'current_step' => 0,
            'total_steps' => 12,
            'step_name' => 'initializing',
            'step_message' => 'Starting update process...',
            'steps' => [],
            'error' => null,
        ]);

        // Build the command to run in background
        // Use 'php' from PATH as PHP_BINARY might point to php-fpm
        $consolePath = $this->project_dir . '/bin/console';
        $logFile = $this->project_dir . '/var/log/update-background.log';

        // Ensure log directory exists
        $logDir = dirname($logFile);
        if (!is_dir($logDir)) {
            $this->filesystem->mkdir($logDir, 0755);
        }

        // Use nohup to properly detach the process from the web request
        // The process will continue running even after the PHP request ends
        $command = sprintf(
            'nohup php %s partdb:update %s %s --force --no-interaction >> %s 2>&1 &',
            escapeshellarg($consolePath),
            escapeshellarg($targetVersion),
            $createBackup ? '' : '--no-backup',
            escapeshellarg($logFile)
        );

        $this->logger->info('Starting background update', [
            'command' => $command,
            'target_version' => $targetVersion,
        ]);

        // Execute in background using shell_exec for proper detachment
        // shell_exec with & runs the command in background
        $output = shell_exec($command);

        // Give it a moment to start
        usleep(500000); // 500ms

        // Check if progress file was updated (indicates process started)
        $progress = $this->getProgress();
        if ($progress && isset($progress['status'])) {
            $this->logger->info('Background update started successfully');
            return 1; // Return a non-null value to indicate success
        }

        $this->logger->error('Background update may not have started', ['output' => $output]);
        return 1; // Still return success as the process might just be slow to start
    }

    /**
     * Execute update with progress file updates for web UI.
     * This is called by the CLI command and updates the progress file.
     */
    public function executeUpdateWithProgress(
        string $targetVersion,
        bool $createBackup = true,
        ?callable $onProgress = null
    ): array {
        $totalSteps = 12;
        $currentStep = 0;

        $updateProgress = function (string $stepName, string $message, bool $success = true) use (&$currentStep, $totalSteps, $targetVersion, $createBackup): void {
            $currentStep++;
            $progress = $this->getProgress() ?? [
                'status' => 'running',
                'target_version' => $targetVersion,
                'create_backup' => $createBackup,
                'started_at' => (new \DateTime())->format('c'),
                'steps' => [],
            ];

            $progress['current_step'] = $currentStep;
            $progress['total_steps'] = $totalSteps;
            $progress['step_name'] = $stepName;
            $progress['step_message'] = $message;
            $progress['status'] = 'running';
            $progress['steps'][] = [
                'step' => $stepName,
                'message' => $message,
                'success' => $success,
                'timestamp' => (new \DateTime())->format('c'),
            ];

            $this->saveProgress($progress);
        };

        // Wrap the existing executeUpdate with progress tracking
        $result = $this->executeUpdate($targetVersion, $createBackup, function ($entry) use ($updateProgress, $onProgress) {
            $updateProgress($entry['step'], $entry['message'], $entry['success']);

            if ($onProgress) {
                $onProgress($entry);
            }
        });

        // Update final status
        $finalProgress = $this->getProgress() ?? [];
        $finalProgress['status'] = $result['success'] ? 'completed' : 'failed';
        $finalProgress['completed_at'] = (new \DateTime())->format('c');
        $finalProgress['result'] = $result;
        $finalProgress['error'] = $result['error'];
        $this->saveProgress($finalProgress);

        return $result;
    }
}
