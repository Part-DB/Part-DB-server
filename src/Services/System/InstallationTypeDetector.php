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

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Process\Process;

readonly class InstallationTypeDetector
{
    public function __construct(#[Autowire(param: 'kernel.project_dir')] private string $project_dir, private GitVersionInfoProvider $gitVersionInfoProvider)
    {

    }

    /**
     * Detect the installation type based on filesystem markers.
     */
    public function detect(): InstallationType
    {
        // Check for Docker environment first
        if ($this->isDocker()) {
            return InstallationType::DOCKER;
        }

        // Check for Git installation
        if ($this->isGitInstall()) {
            return InstallationType::GIT;
        }

        // Check for ZIP release (has VERSION file but no .git)
        if ($this->isZipRelease()) {
            return InstallationType::ZIP_RELEASE;
        }

        return InstallationType::UNKNOWN;
    }

    /**
     * Check if running inside a Docker container.
     */
    public function isDocker(): bool
    {
        // Check for /.dockerenv file
        if (file_exists('/.dockerenv')) {
            return true;
        }

        // Check for DOCKER environment variable
        if (getenv('DOCKER') !== false) {
            return true;
        }

        // Check for container runtime in cgroup
        if (file_exists('/proc/1/cgroup')) {
            $cgroup = @file_get_contents('/proc/1/cgroup');
            if ($cgroup !== false && (str_contains($cgroup, 'docker') || str_contains($cgroup, 'containerd'))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if this is a Git-based installation.
     */
    public function isGitInstall(): bool
    {
        return $this->gitVersionInfoProvider->isGitRepo();
    }

    /**
     * Check if this appears to be a ZIP release installation.
     */
    public function isZipRelease(): bool
    {
        // Has VERSION file but no .git directory
        return file_exists($this->project_dir . '/VERSION') && !$this->isGitInstall();
    }

    /**
     * Get detailed information about the installation.
     */
    public function getInstallationInfo(): array
    {
        $type = $this->detect();

        $info = [
            'type' => $type,
            'type_name' => $type->getLabel(),
            'supports_auto_update' => $type->supportsAutoUpdate(),
            'update_instructions' => $type->getUpdateInstructions(),
            'project_dir' => $this->project_dir,
        ];

        if ($type === InstallationType::GIT) {
            $info['git'] = $this->getGitInfo();
        }

        if ($type === InstallationType::DOCKER) {
            $info['docker'] = $this->getDockerInfo();
        }

        return $info;
    }

    /**
     * Get Git-specific information.
     * @return array{branch: string|null, commit: string|null, remote_url: string|null, has_local_changes: bool}
     */
    private function getGitInfo(): array
    {
        return [
            'branch' => $this->gitVersionInfoProvider->getBranchName(),
            'commit' => $this->gitVersionInfoProvider->getCommitHash(8),
            'remote_url' => $this->gitVersionInfoProvider->getRemoteURL(),
            'has_local_changes' => $this->gitVersionInfoProvider->hasLocalChanges() ?? false,
        ];
    }

    /**
     * Get Docker-specific information.
     * @return array{container_id: string|null, image: string|null}
     */
    private function getDockerInfo(): array
    {
        return [
            'container_id' => @file_get_contents('/proc/1/cpuset') ?: null,
            'image' => getenv('DOCKER_IMAGE') ?: null,
        ];
    }
}
