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


namespace App\Command;

use App\Services\System\InstallationType;
use App\Services\System\UpdateChecker;
use App\Services\System\UpdateExecutor;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'partdb:update', description: 'Check for and install Part-DB updates', aliases: ['app:update'])]
class UpdateCommand extends Command
{
    public function __construct(private readonly UpdateChecker $updateChecker,
        private readonly UpdateExecutor $updateExecutor)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setHelp(<<<'HELP'
The <info>%command.name%</info> command checks for Part-DB updates and can install them.

<comment>Check for updates:</comment>
  <info>php %command.full_name% --check</info>

<comment>List available versions:</comment>
  <info>php %command.full_name% --list</info>

<comment>Update to the latest version:</comment>
  <info>php %command.full_name%</info>

<comment>Update to a specific version:</comment>
  <info>php %command.full_name% v2.6.0</info>

<comment>Update without creating a backup (faster but riskier):</comment>
  <info>php %command.full_name% --no-backup</info>

<comment>Non-interactive update for scripts:</comment>
  <info>php %command.full_name% --force</info>

<comment>View update logs:</comment>
  <info>php %command.full_name% --logs</info>
HELP
            )
            ->addArgument(
                'version',
                InputArgument::OPTIONAL,
                'Target version to update to (e.g., v2.6.0). If not specified, updates to the latest stable version.'
            )
            ->addOption(
                'check',
                'c',
                InputOption::VALUE_NONE,
                'Only check for updates without installing'
            )
            ->addOption(
                'list',
                'l',
                InputOption::VALUE_NONE,
                'List all available versions'
            )
            ->addOption(
                'no-backup',
                null,
                InputOption::VALUE_NONE,
                'Skip creating a backup before updating (not recommended)'
            )
            ->addOption(
                'force',
                'f',
                InputOption::VALUE_NONE,
                'Skip confirmation prompts'
            )
            ->addOption(
                'include-prerelease',
                null,
                InputOption::VALUE_NONE,
                'Include pre-release versions'
            )
            ->addOption(
                'logs',
                null,
                InputOption::VALUE_NONE,
                'Show recent update logs'
            )
            ->addOption(
                'refresh',
                'r',
                InputOption::VALUE_NONE,
                'Force refresh of cached version information'
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Handle --logs option
        if ($input->getOption('logs')) {
            return $this->showLogs($io);
        }

        // Handle --refresh option
        if ($input->getOption('refresh')) {
            $io->text('Refreshing version information...');
            $this->updateChecker->refreshGitInfo();
            $io->success('Version cache cleared.');
        }

        // Handle --list option
        if ($input->getOption('list')) {
            return $this->listVersions($io, $input->getOption('include-prerelease'));
        }

        // Get update status
        $status = $this->updateChecker->getUpdateStatus();

        // Display current status
        $io->title('Part-DB Update Manager');

        $this->displayStatus($io, $status);

        // Handle --check option
        if ($input->getOption('check')) {
            return $this->checkOnly($io, $status);
        }

        // Validate we can update
        $validationResult = $this->validateUpdate($io, $status);
        if ($validationResult !== null) {
            return $validationResult;
        }

        // Determine target version
        $targetVersion = $input->getArgument('version');
        $includePrerelease = $input->getOption('include-prerelease');

        if (!$targetVersion) {
            $latest = $this->updateChecker->getLatestRelease($includePrerelease);
            if (!$latest) {
                $io->error('Could not determine the latest version. Please specify a version manually.');
                return Command::FAILURE;
            }
            $targetVersion = $latest['tag'];
        }

        // Validate target version
        if (!$this->updateChecker->isNewerVersion($targetVersion)) {
            $io->warning(sprintf(
                'Version %s is not newer than the current version %s.',
                $targetVersion,
                $status['current_version']
            ));

            if (!$input->getOption('force')) {
                if (!$io->confirm('Do you want to proceed anyway?', false)) {
                    $io->info('Update cancelled.');
                    return Command::SUCCESS;
                }
            }
        }

        // Confirm update
        if (!$input->getOption('force')) {
            $io->section('Update Plan');

            $io->listing([
                sprintf('Target version: <info>%s</info>', $targetVersion),
                $input->getOption('no-backup')
                    ? '<fg=yellow>Backup will be SKIPPED</>'
                    : 'A full backup will be created before updating',
                'Maintenance mode will be enabled during update',
                'Database migrations will be run automatically',
                'Cache will be cleared and rebuilt',
            ]);

            $io->warning('The update process may take several minutes. Do not interrupt it.');

            if (!$io->confirm('Do you want to proceed with the update?', false)) {
                $io->info('Update cancelled.');
                return Command::SUCCESS;
            }
        }

        // Execute update
        return $this->executeUpdate($io, $targetVersion, !$input->getOption('no-backup'));
    }

    private function displayStatus(SymfonyStyle $io, array $status): void
    {
        $io->definitionList(
            ['Current Version' => sprintf('<info>%s</info>', $status['current_version'])],
            ['Latest Version' => $status['latest_version']
                ? sprintf('<info>%s</info>', $status['latest_version'])
                : '<fg=yellow>Unknown</>'],
            ['Installation Type' => $status['installation']['type_name']],
            ['Git Branch' => $status['git']['branch'] ?? '<fg=gray>N/A</>'],
            ['Git Commit' => $status['git']['commit'] ?? '<fg=gray>N/A</>'],
            ['Local Changes' => $status['git']['has_local_changes']
                ? '<fg=yellow>Yes (update blocked)</>'
                : '<fg=green>No</>'],
            ['Commits Behind' => $status['git']['commits_behind'] > 0
                ? sprintf('<fg=yellow>%d</>', $status['git']['commits_behind'])
                : '<fg=green>0</>'],
            ['Update Available' => $status['update_available']
                ? '<fg=green>Yes</>'
                : 'No'],
            ['Can Auto-Update' => $status['can_auto_update']
                ? '<fg=green>Yes</>'
                : '<fg=yellow>No</>'],
        );

        if (!empty($status['update_blockers'])) {
            $io->warning('Update blockers: ' . implode(', ', $status['update_blockers']));
        }
    }

    private function checkOnly(SymfonyStyle $io, array $status): int
    {
        if (!$status['check_enabled']) {
            $io->warning('Update checking is disabled in privacy settings.');
            return Command::SUCCESS;
        }

        if ($status['update_available']) {
            $io->success(sprintf(
                'A new version is available: %s (current: %s)',
                $status['latest_version'],
                $status['current_version']
            ));

            if ($status['release_url']) {
                $io->text(sprintf('Release notes: <href=%s>%s</>', $status['release_url'], $status['release_url']));
            }

            if ($status['can_auto_update']) {
                $io->text('');
                $io->text('Run <info>php bin/console partdb:update</info> to update.');
            } else {
                $io->text('');
                $io->text($status['installation']['update_instructions']);
            }

            return Command::SUCCESS;
        }

        $io->success('You are running the latest version.');
        return Command::SUCCESS;
    }

    private function validateUpdate(SymfonyStyle $io, array $status): ?int
    {
        // Check if update checking is enabled
        if (!$status['check_enabled']) {
            $io->error('Update checking is disabled in privacy settings. Enable it to use automatic updates.');
            return Command::FAILURE;
        }

        // Check installation type
        if (!$status['can_auto_update']) {
            $io->error('Automatic updates are not supported for this installation type.');
            $io->text($status['installation']['update_instructions']);
            return Command::FAILURE;
        }

        // Validate preconditions
        $validation = $this->updateExecutor->validateUpdatePreconditions();
        if (!$validation['valid']) {
            $io->error('Cannot proceed with update:');
            $io->listing($validation['errors']);
            return Command::FAILURE;
        }

        return null;
    }

    private function executeUpdate(SymfonyStyle $io, string $targetVersion, bool $createBackup): int
    {
        $io->section('Executing Update');
        $io->text(sprintf('Updating to version: <info>%s</info>', $targetVersion));
        $io->text('');

        $progressCallback = function (array $step) use ($io): void {
            $icon = $step['success'] ? '<fg=green>✓</>' : '<fg=red>✗</>';
            $duration = $step['duration'] ? sprintf(' <fg=gray>(%.1fs)</>', $step['duration']) : '';
            $io->text(sprintf('  %s <info>%s</info>: %s%s', $icon, $step['step'], $step['message'], $duration));
        };

        // Use executeUpdateWithProgress to update the progress file for web UI
        $result = $this->updateExecutor->executeUpdateWithProgress($targetVersion, $createBackup, $progressCallback);

        $io->text('');

        if ($result['success']) {
            $io->success(sprintf(
                'Successfully updated to %s in %.1f seconds!',
                $targetVersion,
                $result['duration']
            ));

            $io->text([
                sprintf('Rollback tag: <info>%s</info>', $result['rollback_tag']),
                sprintf('Log file: <info>%s</info>', $result['log_file']),
            ]);

            $io->note('If you encounter any issues, you can rollback using: git checkout ' . $result['rollback_tag']);

            return Command::SUCCESS;
        }

        $io->error('Update failed: ' . $result['error']);

        if ($result['rollback_tag']) {
            $io->warning(sprintf('System was rolled back to: %s', $result['rollback_tag']));
        }

        if ($result['log_file']) {
            $io->text(sprintf('See log file for details: %s', $result['log_file']));
        }

        return Command::FAILURE;
    }

    private function listVersions(SymfonyStyle $io, bool $includePrerelease): int
    {
        $releases = $this->updateChecker->getAvailableReleases(15);
        $currentVersion = $this->updateChecker->getCurrentVersionString();

        if (empty($releases)) {
            $io->warning('Could not fetch available versions. Check your internet connection.');
            return Command::FAILURE;
        }

        $io->title('Available Part-DB Versions');

        $table = new Table($io);
        $table->setHeaders(['Tag', 'Version', 'Released', 'Status']);

        foreach ($releases as $release) {
            if (!$includePrerelease && $release['prerelease']) {
                continue;
            }

            $version = $release['version'];
            $status = [];

            if (version_compare($version, $currentVersion, '=')) {
                $status[] = '<fg=cyan>current</>';
            } elseif (version_compare($version, $currentVersion, '>')) {
                $status[] = '<fg=green>newer</>';
            }

            if ($release['prerelease']) {
                $status[] = '<fg=yellow>pre-release</>';
            }

            $table->addRow([
                $release['tag'],
                $version,
                (new \DateTime($release['published_at']))->format('Y-m-d'),
                implode(' ', $status) ?: '-',
            ]);
        }

        $table->render();

        $io->text('');
        $io->text('Use <info>php bin/console partdb:update [tag]</info> to update to a specific version.');

        return Command::SUCCESS;
    }

    private function showLogs(SymfonyStyle $io): int
    {
        $logs = $this->updateExecutor->getUpdateLogs();

        if (empty($logs)) {
            $io->info('No update logs found.');
            return Command::SUCCESS;
        }

        $io->title('Recent Update Logs');

        $table = new Table($io);
        $table->setHeaders(['Date', 'File', 'Size']);

        foreach (array_slice($logs, 0, 10) as $log) {
            $table->addRow([
                date('Y-m-d H:i:s', $log['date']),
                $log['file'],
                $this->formatBytes($log['size']),
            ]);
        }

        $table->render();

        $io->text('');
        $io->text('Log files are stored in: <info>var/log/updates/</info>');

        return Command::SUCCESS;
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $unitIndex = 0;

        while ($bytes >= 1024 && $unitIndex < count($units) - 1) {
            $bytes /= 1024;
            $unitIndex++;
        }

        return sprintf('%.1f %s', $bytes, $units[$unitIndex]);
    }
}
