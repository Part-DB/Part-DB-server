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

use App\Settings\SystemSettings\PrivacySettings;
use Psr\Log\LoggerInterface;
use Shivas\VersioningBundle\Service\VersionManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Process\Process;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Version\Version;

/**
 * Enhanced update checker that fetches release information including changelogs.
 */
class UpdateChecker
{
    private const GITHUB_API_BASE = 'https://api.github.com/repos/Part-DB/Part-DB-server';
    private const CACHE_KEY_RELEASES = 'update_checker_releases';
    private const CACHE_KEY_COMMITS = 'update_checker_commits_behind';
    private const CACHE_TTL = 60 * 60 * 6; // 6 hours
    private const CACHE_TTL_ERROR = 60 * 60; // 1 hour on error

    public function __construct(private readonly HttpClientInterface $httpClient,
        private readonly CacheInterface $updateCache, private readonly VersionManagerInterface $versionManager,
        private readonly PrivacySettings $privacySettings, private readonly LoggerInterface $logger,
        private readonly InstallationTypeDetector $installationTypeDetector,
        private readonly GitVersionInfoProvider $gitVersionInfoProvider,
        #[Autowire(param: 'kernel.debug')] private readonly bool $is_dev_mode,
        #[Autowire(param: 'kernel.project_dir')] private readonly string $project_dir)
    {

    }

    /**
     * Get the current installed version.
     */
    public function getCurrentVersion(): Version
    {
        return $this->versionManager->getVersion();
    }

    /**
     * Get the current version as string.
     */
    public function getCurrentVersionString(): string
    {
        return $this->getCurrentVersion()->toString();
    }

    /**
     * Get Git repository information.
     * @return array{branch: ?string, commit: ?string, has_local_changes: bool, commits_behind: int, is_git_install: bool}
     */
    public function getGitInfo(): array
    {
        $info = [
            'branch' => null,
            'commit' => null,
            'has_local_changes' => false,
            'commits_behind' => 0,
            'is_git_install' => false,
        ];

        if (!$this->gitVersionInfoProvider->isGitRepo()) {
            return $info;
        }

        $info['is_git_install'] = true;

        $info['branch'] = $this->gitVersionInfoProvider->getBranchName();
        $info['commit'] = $this->gitVersionInfoProvider->getCommitHash(8);
        $info['has_local_changes'] = $this->gitVersionInfoProvider->hasLocalChanges();

        // Get commits behind (fetch first)
        if ($info['branch']) {
            // Try to get cached commits behind count
            $info['commits_behind'] = $this->getCommitsBehind($info['branch']);
        }

        return $info;
    }

    /**
     * Get number of commits behind the remote branch (cached).
     */
    private function getCommitsBehind(string $branch): int
    {
        if (!$this->privacySettings->checkForUpdates) {
            return 0;
        }

        $cacheKey = self::CACHE_KEY_COMMITS . '_' . md5($branch);

        return $this->updateCache->get($cacheKey, function (ItemInterface $item) use ($branch) {
            $item->expiresAfter(self::CACHE_TTL);

            // Fetch from remote first
            $process = new Process(['git', 'fetch', '--tags', 'origin'], $this->project_dir);
            $process->run();

            // Count commits behind
            $process = new Process(['git', 'rev-list', 'HEAD..origin/' . $branch, '--count'], $this->project_dir);
            $process->run();

            return $process->isSuccessful() ? (int) trim($process->getOutput()) : 0;
        });
    }

    /**
     * Force refresh git information by invalidating cache.
     */
    public function refreshVersionInfo(): void
    {
        $gitInfo = $this->getGitInfo();
        if ($gitInfo['branch']) {
            $this->updateCache->delete(self::CACHE_KEY_COMMITS . '_' . md5($gitInfo['branch']));
        }
        $this->updateCache->delete(self::CACHE_KEY_RELEASES);
    }

    /**
     * Get all available releases from GitHub (cached).
     *
     * @return array<array{version: string, tag: string, name: string, url: string, published_at: string, body: string, prerelease: bool, assets: array}>
     */
    public function getAvailableReleases(int $limit = 10): array
    {
        if (!$this->privacySettings->checkForUpdates) {
            return [];
        }

        return $this->updateCache->get(self::CACHE_KEY_RELEASES, function (ItemInterface $item) use ($limit) {
            $item->expiresAfter(self::CACHE_TTL);

            try {
                $response = $this->httpClient->request('GET', self::GITHUB_API_BASE . '/releases', [
                    'query' => ['per_page' => $limit],
                    'headers' => [
                        'Accept' => 'application/vnd.github.v3+json',
                        'User-Agent' => 'Part-DB-Update-Checker',
                    ],
                ]);

                $releases = [];
                foreach ($response->toArray() as $release) {
                    // Extract assets (for ZIP download)
                    $assets = [];
                    foreach ($release['assets'] ?? [] as $asset) {
                        if (str_ends_with($asset['name'], '.zip') || str_ends_with($asset['name'], '.tar.gz')) {
                            $assets[] = [
                                'name' => $asset['name'],
                                'url' => $asset['browser_download_url'],
                                'size' => $asset['size'],
                            ];
                        }
                    }

                    $releases[] = [
                        'version' => ltrim($release['tag_name'], 'v'),
                        'tag' => $release['tag_name'],
                        'name' => $release['name'] ?? $release['tag_name'],
                        'url' => $release['html_url'],
                        'published_at' => $release['published_at'],
                        'body' => $release['body'] ?? '',
                        'prerelease' => $release['prerelease'] ?? false,
                        'draft' => $release['draft'] ?? false,
                        'assets' => $assets,
                        'tarball_url' => $release['tarball_url'] ?? null,
                        'zipball_url' => $release['zipball_url'] ?? null,
                    ];
                }

                return $releases;
            } catch (\Exception $e) {
                $this->logger->error('Failed to fetch releases from GitHub: ' . $e->getMessage());
                $item->expiresAfter(self::CACHE_TTL_ERROR);

                if ($this->is_dev_mode) {
                    throw $e;
                }

                return [];
            }
        });
    }

    /**
     * Get the latest stable release.
     * @return array{version: string, tag: string, name: string, url: string, published_at: string, body: string, prerelease: bool, assets: array}|null
     */
    public function getLatestRelease(bool $includePrerelease = false): ?array
    {
        $releases = $this->getAvailableReleases();

        foreach ($releases as $release) {
            // Skip drafts always
            if ($release['draft']) {
                continue;
            }

            // Skip prereleases unless explicitly included
            if (!$includePrerelease && $release['prerelease']) {
                continue;
            }

            return $release;
        }

        return null;
    }

    /**
     * Check if a specific version is newer than current.
     */
    public function isNewerVersion(string $version): bool
    {
        try {
            $targetVersion = Version::fromString(ltrim($version, 'v'));
            return $targetVersion->isGreaterThan($this->getCurrentVersion());
        } catch (\Exception) {
            return false;
        }
    }

    /**
     * Get comprehensive update status.
     * @return array{current_version: string, latest_version: ?string, latest_tag: ?string, update_available: bool, release_notes: ?string, release_url: ?string,
     *      published_at: ?string, git: array, installation: array, can_auto_update: bool, update_blockers: array, check_enabled: bool}
     */
    public function getUpdateStatus(): array
    {
        $current = $this->getCurrentVersion();
        $latest = $this->getLatestRelease();
        $gitInfo = $this->getGitInfo();
        $installInfo = $this->installationTypeDetector->getInstallationInfo();

        $updateAvailable = false;
        $latestVersion = null;
        $latestTag = null;

        if ($latest) {
            try {
                $latestVersionObj = Version::fromString($latest['version']);
                $updateAvailable = $latestVersionObj->isGreaterThan($current);
                $latestVersion = $latest['version'];
                $latestTag = $latest['tag'];
            } catch (\Exception) {
                // Invalid version string
            }
        }

        // Determine if we can auto-update
        $canAutoUpdate = $installInfo['supports_auto_update'];
        $updateBlockers = [];

        if ($gitInfo['has_local_changes']) {
            $canAutoUpdate = false;
            $updateBlockers[] = 'local_changes';
        }

        if ($installInfo['type'] === InstallationType::DOCKER) {
            $updateBlockers[] = 'docker_installation';
        }

        return [
            'current_version' => $current->toString(),
            'latest_version' => $latestVersion,
            'latest_tag' => $latestTag,
            'update_available' => $updateAvailable,
            'release_notes' => $latest['body'] ?? null,
            'release_url' => $latest['url'] ?? null,
            'published_at' => $latest['published_at'] ?? null,
            'git' => $gitInfo,
            'installation' => $installInfo,
            'can_auto_update' => $canAutoUpdate,
            'update_blockers' => $updateBlockers,
            'check_enabled' => $this->privacySettings->checkForUpdates,
        ];
    }

    /**
     * Get releases newer than the current version.
     * @return array<array{version: string, tag: string, name: string, url: string, published_at: string, body: string, prerelease: bool, assets: array}>
     */
    public function getAvailableUpdates(bool $includePrerelease = false): array
    {
        $releases = $this->getAvailableReleases();
        $current = $this->getCurrentVersion();
        $updates = [];

        foreach ($releases as $release) {
            if ($release['draft']) {
                continue;
            }

            if (!$includePrerelease && $release['prerelease']) {
                continue;
            }

            try {
                $releaseVersion = Version::fromString($release['version']);
                if ($releaseVersion->isGreaterThan($current)) {
                    $updates[] = $release;
                }
            } catch (\Exception) {
                continue;
            }
        }

        return $updates;
    }
}
