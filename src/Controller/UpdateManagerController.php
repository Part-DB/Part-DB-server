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


namespace App\Controller;

use App\Services\System\UpdateChecker;
use App\Services\System\UpdateExecutor;
use Shivas\VersioningBundle\Service\VersionManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Controller for the Update Manager web interface.
 *
 * This provides a read-only view of update status and instructions.
 * Actual updates should be performed via the CLI command for safety.
 */
#[Route('/admin/update-manager')]
class UpdateManagerController extends AbstractController
{
    public function __construct(
        private readonly UpdateChecker $updateChecker,
        private readonly UpdateExecutor $updateExecutor,
        private readonly VersionManagerInterface $versionManager,
        #[Autowire(env: 'bool:DISABLE_WEB_UPDATES')]
        private readonly bool $webUpdatesDisabled = false,
        #[Autowire(env: 'bool:DISABLE_BACKUP_RESTORE')]
        private readonly bool $backupRestoreDisabled = false,
    ) {
    }

    /**
     * Check if web updates are disabled and throw exception if so.
     */
    private function denyIfWebUpdatesDisabled(): void
    {
        if ($this->webUpdatesDisabled) {
            throw new AccessDeniedHttpException('Web-based updates are disabled by server configuration. Please use the CLI command instead.');
        }
    }

    /**
     * Check if backup restore is disabled and throw exception if so.
     */
    private function denyIfBackupRestoreDisabled(): void
    {
        if ($this->backupRestoreDisabled) {
            throw new AccessDeniedHttpException('Backup restore is disabled by server configuration.');
        }
    }

    /**
     * Main update manager page.
     */
    #[Route('', name: 'admin_update_manager', methods: ['GET'])]
    public function index(): Response
    {
        $this->denyAccessUnlessGranted('@system.show_updates');

        $status = $this->updateChecker->getUpdateStatus();
        $availableUpdates = $this->updateChecker->getAvailableUpdates();
        $validation = $this->updateExecutor->validateUpdatePreconditions();

        return $this->render('admin/update_manager/index.html.twig', [
            'status' => $status,
            'available_updates' => $availableUpdates,
            'all_releases' => $this->updateChecker->getAvailableReleases(10),
            'validation' => $validation,
            'is_locked' => $this->updateExecutor->isLocked(),
            'lock_info' => $this->updateExecutor->getLockInfo(),
            'is_maintenance' => $this->updateExecutor->isMaintenanceMode(),
            'maintenance_info' => $this->updateExecutor->getMaintenanceInfo(),
            'update_logs' => $this->updateExecutor->getUpdateLogs(),
            'backups' => $this->updateExecutor->getBackups(),
            'web_updates_disabled' => $this->webUpdatesDisabled,
            'backup_restore_disabled' => $this->backupRestoreDisabled,
        ]);
    }

    /**
     * AJAX endpoint to check update status.
     */
    #[Route('/status', name: 'admin_update_manager_status', methods: ['GET'])]
    public function status(): JsonResponse
    {
        $this->denyAccessUnlessGranted('@system.show_updates');

        return $this->json([
            'status' => $this->updateChecker->getUpdateStatus(),
            'is_locked' => $this->updateExecutor->isLocked(),
            'is_maintenance' => $this->updateExecutor->isMaintenanceMode(),
            'lock_info' => $this->updateExecutor->getLockInfo(),
        ]);
    }

    /**
     * AJAX endpoint to refresh version information.
     */
    #[Route('/refresh', name: 'admin_update_manager_refresh', methods: ['POST'])]
    public function refresh(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('@system.show_updates');

        // Validate CSRF token
        if (!$this->isCsrfTokenValid('update_manager_refresh', $request->request->get('_token'))) {
            return $this->json(['error' => 'Invalid CSRF token'], Response::HTTP_FORBIDDEN);
        }

        $this->updateChecker->refreshGitInfo();

        return $this->json([
            'success' => true,
            'status' => $this->updateChecker->getUpdateStatus(),
        ]);
    }

    /**
     * View release notes for a specific version.
     */
    #[Route('/release/{tag}', name: 'admin_update_manager_release', methods: ['GET'])]
    public function releaseNotes(string $tag): Response
    {
        $this->denyAccessUnlessGranted('@system.show_updates');

        $releases = $this->updateChecker->getAvailableReleases(20);
        $release = null;

        foreach ($releases as $r) {
            if ($r['tag'] === $tag) {
                $release = $r;
                break;
            }
        }

        if (!$release) {
            throw $this->createNotFoundException('Release not found');
        }

        return $this->render('admin/update_manager/release_notes.html.twig', [
            'release' => $release,
            'current_version' => $this->updateChecker->getCurrentVersionString(),
        ]);
    }

    /**
     * View an update log file.
     */
    #[Route('/log/{filename}', name: 'admin_update_manager_log', methods: ['GET'])]
    public function viewLog(string $filename): Response
    {
        $this->denyAccessUnlessGranted('@system.show_updates');

        // Security: Only allow viewing files from the update logs directory
        $logs = $this->updateExecutor->getUpdateLogs();
        $logPath = null;

        foreach ($logs as $log) {
            if ($log['file'] === $filename) {
                $logPath = $log['path'];
                break;
            }
        }

        if (!$logPath || !file_exists($logPath)) {
            throw $this->createNotFoundException('Log file not found');
        }

        $content = file_get_contents($logPath);

        return $this->render('admin/update_manager/log_viewer.html.twig', [
            'filename' => $filename,
            'content' => $content,
        ]);
    }

    /**
     * Start an update process.
     */
    #[Route('/start', name: 'admin_update_manager_start', methods: ['POST'])]
    public function startUpdate(Request $request): Response
    {
        $this->denyAccessUnlessGranted('@system.manage_updates');
        $this->denyIfWebUpdatesDisabled();

        // Validate CSRF token
        if (!$this->isCsrfTokenValid('update_manager_start', $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid CSRF token');
            return $this->redirectToRoute('admin_update_manager');
        }

        // Check if update is already running
        if ($this->updateExecutor->isLocked() || $this->updateExecutor->isUpdateRunning()) {
            $this->addFlash('error', 'An update is already in progress.');
            return $this->redirectToRoute('admin_update_manager');
        }

        $targetVersion = $request->request->get('version');
        $createBackup = $request->request->getBoolean('backup', true);

        if (!$targetVersion) {
            // Get latest version if not specified
            $latest = $this->updateChecker->getLatestRelease();
            if (!$latest) {
                $this->addFlash('error', 'Could not determine target version.');
                return $this->redirectToRoute('admin_update_manager');
            }
            $targetVersion = $latest['tag'];
        }

        // Validate preconditions
        $validation = $this->updateExecutor->validateUpdatePreconditions();
        if (!$validation['valid']) {
            $this->addFlash('error', implode(' ', $validation['errors']));
            return $this->redirectToRoute('admin_update_manager');
        }

        // Start the background update
        $pid = $this->updateExecutor->startBackgroundUpdate($targetVersion, $createBackup);

        if (!$pid) {
            $this->addFlash('error', 'Failed to start update process.');
            return $this->redirectToRoute('admin_update_manager');
        }

        // Redirect to progress page
        return $this->redirectToRoute('admin_update_manager_progress');
    }

    /**
     * Update progress page.
     */
    #[Route('/progress', name: 'admin_update_manager_progress', methods: ['GET'])]
    public function progress(): Response
    {
        $this->denyAccessUnlessGranted('@system.show_updates');

        $progress = $this->updateExecutor->getProgress();
        $currentVersion = $this->versionManager->getVersion()->toString();

        // Determine if this is a downgrade
        $isDowngrade = false;
        if ($progress && isset($progress['target_version'])) {
            $targetVersion = ltrim($progress['target_version'], 'v');
            $isDowngrade = version_compare($targetVersion, $currentVersion, '<');
        }

        return $this->render('admin/update_manager/progress.html.twig', [
            'progress' => $progress,
            'is_locked' => $this->updateExecutor->isLocked(),
            'is_maintenance' => $this->updateExecutor->isMaintenanceMode(),
            'is_downgrade' => $isDowngrade,
            'current_version' => $currentVersion,
        ]);
    }

    /**
     * AJAX endpoint to get update progress.
     */
    #[Route('/progress/status', name: 'admin_update_manager_progress_status', methods: ['GET'])]
    public function progressStatus(): JsonResponse
    {
        $this->denyAccessUnlessGranted('@system.show_updates');

        $progress = $this->updateExecutor->getProgress();

        return $this->json([
            'progress' => $progress,
            'is_locked' => $this->updateExecutor->isLocked(),
            'is_maintenance' => $this->updateExecutor->isMaintenanceMode(),
        ]);
    }

    /**
     * Get backup details for restore confirmation.
     */
    #[Route('/backup/{filename}', name: 'admin_update_manager_backup_details', methods: ['GET'])]
    public function backupDetails(string $filename): JsonResponse
    {
        $this->denyAccessUnlessGranted('@system.manage_updates');

        $details = $this->updateExecutor->getBackupDetails($filename);

        if (!$details) {
            return $this->json(['error' => 'Backup not found'], 404);
        }

        return $this->json($details);
    }

    /**
     * Restore from a backup.
     */
    #[Route('/restore', name: 'admin_update_manager_restore', methods: ['POST'])]
    public function restore(Request $request): Response
    {
        $this->denyAccessUnlessGranted('@system.manage_updates');
        $this->denyIfBackupRestoreDisabled();

        // Validate CSRF token
        if (!$this->isCsrfTokenValid('update_manager_restore', $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid CSRF token.');
            return $this->redirectToRoute('admin_update_manager');
        }

        // Check if already locked
        if ($this->updateExecutor->isLocked()) {
            $this->addFlash('error', 'An update or restore is already in progress.');
            return $this->redirectToRoute('admin_update_manager');
        }

        $filename = $request->request->get('filename');
        $restoreDatabase = $request->request->getBoolean('restore_database', true);
        $restoreConfig = $request->request->getBoolean('restore_config', false);
        $restoreAttachments = $request->request->getBoolean('restore_attachments', false);

        if (!$filename) {
            $this->addFlash('error', 'No backup file specified.');
            return $this->redirectToRoute('admin_update_manager');
        }

        // Verify the backup exists
        $backupDetails = $this->updateExecutor->getBackupDetails($filename);
        if (!$backupDetails) {
            $this->addFlash('error', 'Backup file not found.');
            return $this->redirectToRoute('admin_update_manager');
        }

        // Execute restore (this is a synchronous operation for now - could be made async later)
        $result = $this->updateExecutor->restoreBackup(
            $filename,
            $restoreDatabase,
            $restoreConfig,
            $restoreAttachments
        );

        if ($result['success']) {
            $this->addFlash('success', 'Backup restored successfully.');
        } else {
            $this->addFlash('error', 'Restore failed: ' . ($result['error'] ?? 'Unknown error'));
        }

        return $this->redirectToRoute('admin_update_manager');
    }
}
