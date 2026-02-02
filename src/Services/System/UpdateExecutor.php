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
    private const PROGRESS_FILE = 'var/update_progress.json';

    /** @var array<array{step: string, message: string, success: bool, timestamp: string, duration: ?float}> */
    private array $steps = [];

    private ?string $currentLogFile = null;
    private CommandRunHelper $commandRunHelper;

    public function __construct(
        #[Autowire(param: 'kernel.project_dir')]
        private readonly string $project_dir,
        private readonly LoggerInterface $logger,
        private readonly Filesystem $filesystem,
        private readonly InstallationTypeDetector $installationTypeDetector,
        private readonly VersionManagerInterface $versionManager,
        private readonly BackupManager $backupManager,
        #[Autowire(param: 'app.debug_mode')]
        private readonly bool $debugMode = false,
    ) {
        $this->commandRunHelper = new CommandRunHelper($this);
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

        // Check if yarn is available (for frontend assets)
        $process = new Process(['yarn', '--version']);
        $process->run();
        if (!$process->isSuccessful()) {
            $errors[] = 'Yarn command not found. Please ensure Yarn is installed and in PATH for frontend asset compilation.';
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
                $backupFile = $this->backupManager->createBackup($targetVersion);
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

            // Step 7: Install PHP dependencies
            $stepStart = microtime(true);
            if ($this->debugMode) {
                $this->runCommand([ // Install with dev dependencies in debug mode
                    'composer',
                    'install',
                    '--no-interaction',
                    '--no-progress',
                ], 'Install PHP dependencies', 600);
            } else {
                $this->runCommand([
                    'composer',
                    'install',
                    '--no-dev',
                    '--optimize-autoloader',
                    '--no-interaction',
                    '--no-progress',
                ], 'Install PHP dependencies', 600);
            }
            $log('composer', 'Installed/updated PHP dependencies', true, microtime(true) - $stepStart);

            // Step 8: Install frontend dependencies
            $stepStart = microtime(true);
            $this->runCommand([
                'yarn', 'install',
                '--frozen-lockfile',
                '--non-interactive',
            ], 'Install frontend dependencies', 600);
            $log('yarn_install', 'Installed frontend dependencies', true, microtime(true) - $stepStart);

            // Step 9: Build frontend assets
            $stepStart = microtime(true);
            $this->runCommand([
                'yarn', 'build',
            ], 'Build frontend assets', 600);
            $log('yarn_build', 'Built frontend assets', true, microtime(true) - $stepStart);

            // Step 10: Run database migrations
            $stepStart = microtime(true);
            $this->runCommand([
                'php', 'bin/console', 'doctrine:migrations:migrate',
                '--no-interaction',
                '--allow-no-migration',
            ], 'Run migrations', 300);
            $log('migrations', 'Database migrations completed', true, microtime(true) - $stepStart);

            // Step 11: Clear cache
            $stepStart = microtime(true);
            $this->runCommand([
                'php', 'bin/console', 'cache:clear',
                '--env=prod',
                '--no-interaction',
            ], 'Clear cache', 120);
            $log('cache_clear', 'Cleared application cache', true, microtime(true) - $stepStart);

            // Step 12: Warm up cache
            $stepStart = microtime(true);
            $this->runCommand([
                'php', 'bin/console', 'cache:warmup',
                '--env=prod',
            ], 'Warmup cache', 120);
            $log('cache_warmup', 'Warmed up application cache', true, microtime(true) - $stepStart);

            // Step 13: Disable maintenance mode
            $stepStart = microtime(true);
            $this->disableMaintenanceMode();
            $log('maintenance_off', 'Disabled maintenance mode', true, microtime(true) - $stepStart);

            // Step 14: Release lock
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
                    $log('rollback_composer', 'Reinstalled PHP dependencies after rollback', true);

                    // Re-run yarn install after rollback
                    $this->runCommand([
                        'yarn', 'install',
                        '--frozen-lockfile',
                        '--non-interactive',
                    ], 'Reinstall frontend dependencies after rollback', 600);
                    $log('rollback_yarn_install', 'Reinstalled frontend dependencies after rollback', true);

                    // Re-run yarn build after rollback
                    $this->runCommand([
                        'yarn', 'build',
                    ], 'Rebuild frontend assets after rollback', 600);
                    $log('rollback_yarn_build', 'Rebuilt frontend assets after rollback', true);

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
     * Run a shell command with proper error handling.
     */
    private function runCommand(array $command, string $description, int $timeout = 120): string
    {
        return $this->commandRunHelper->runCommand($command, $description, $timeout);
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
     * @deprecated Use BackupManager::getBackups() directly
     */
    public function getBackups(): array
    {
        return $this->backupManager->getBackups();
    }

    /**
     * Get details about a specific backup file.
     * @deprecated Use BackupManager::getBackupDetails() directly
     */
    public function getBackupDetails(string $filename): ?array
    {
        return $this->backupManager->getBackupDetails($filename);
    }

    /**
     * Restore from a backup file with maintenance mode and cache clearing.
     *
     * This wraps BackupManager::restoreBackup with additional safety measures
     * like lock acquisition, maintenance mode, and cache operations.
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
            $stepStart = microtime(true);

            // Step 1: Acquire lock
            if (!$this->acquireLock()) {
                throw new \RuntimeException('Could not acquire lock. Another operation may be in progress.');
            }
            $log('lock', 'Acquired exclusive restore lock', true, microtime(true) - $stepStart);

            // Step 2: Enable maintenance mode
            $stepStart = microtime(true);
            $this->enableMaintenanceMode('Restoring from backup...');
            $log('maintenance', 'Enabled maintenance mode', true, microtime(true) - $stepStart);

            // Step 3: Delegate to BackupManager for core restoration
            $stepStart = microtime(true);
            $result = $this->backupManager->restoreBackup(
                $filename,
                $restoreDatabase,
                $restoreConfig,
                $restoreAttachments,
                function ($entry) use ($log) {
                    // Forward progress from BackupManager
                    $log($entry['step'], $entry['message'], $entry['success'], $entry['duration'] ?? null);
                }
            );

            if (!$result['success']) {
                throw new \RuntimeException($result['error'] ?? 'Restore failed');
            }

            // Step 4: Clear cache
            $stepStart = microtime(true);
            $this->runCommand(['php', 'bin/console', 'cache:clear', '--no-warmup'], 'Clear cache');
            $log('cache_clear', 'Cleared application cache', true, microtime(true) - $stepStart);

            // Step 5: Warm up cache
            $stepStart = microtime(true);
            $this->runCommand(['php', 'bin/console', 'cache:warmup'], 'Warm up cache');
            $log('cache_warmup', 'Warmed up application cache', true, microtime(true) - $stepStart);

            // Step 6: Disable maintenance mode
            $stepStart = microtime(true);
            $this->disableMaintenanceMode();
            $log('maintenance_off', 'Disabled maintenance mode', true, microtime(true) - $stepStart);

            // Step 7: Release lock
            $this->releaseLock();

            $totalDuration = microtime(true) - $startTime;
            $log('complete', sprintf('Restore completed successfully in %.1f seconds', $totalDuration), true);

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
            'total_steps' => 14,
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
