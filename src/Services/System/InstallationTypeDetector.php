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

/**
 * Detects the installation type of Part-DB to determine the appropriate update strategy.
 */
enum InstallationType: string
{
    case GIT = 'git';
    case DOCKER = 'docker';
    case ZIP_RELEASE = 'zip_release';
    case UNKNOWN = 'unknown';

    public function getLabel(): string
    {
        return match($this) {
            self::GIT => 'Git Clone',
            self::DOCKER => 'Docker',
            self::ZIP_RELEASE => 'Release Archive',
            self::UNKNOWN => 'Unknown',
        };
    }

    public function supportsAutoUpdate(): bool
    {
        return match($this) {
            self::GIT => true,
            self::DOCKER => false,
            // ZIP_RELEASE auto-update not yet implemented
            self::ZIP_RELEASE => false,
            self::UNKNOWN => false,
        };
    }

    public function getUpdateInstructions(): string
    {
        return match($this) {
            self::GIT => 'Run: php bin/console partdb:update',
            self::DOCKER => 'Pull the new Docker image and recreate the container: docker-compose pull && docker-compose up -d',
            self::ZIP_RELEASE => 'Download the new release ZIP from GitHub, extract it over your installation, and run: php bin/console doctrine:migrations:migrate && php bin/console cache:clear',
            self::UNKNOWN => 'Unable to determine installation type. Please update manually.',
        };
    }
}

class InstallationTypeDetector
{
    public function __construct(#[Autowire(param: 'kernel.project_dir')] private readonly string $project_dir)
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
        return is_dir($this->project_dir . '/.git');
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
     */
    private function getGitInfo(): array
    {
        $info = [
            'branch' => null,
            'commit' => null,
            'remote_url' => null,
            'has_local_changes' => false,
        ];

        // Get branch
        $headFile = $this->project_dir . '/.git/HEAD';
        if (file_exists($headFile)) {
            $head = file_get_contents($headFile);
            if (preg_match('#ref: refs/heads/(.+)#', $head, $matches)) {
                $info['branch'] = trim($matches[1]);
            }
        }

        // Get remote URL
        $configFile = $this->project_dir . '/.git/config';
        if (file_exists($configFile)) {
            $config = file_get_contents($configFile);
            if (preg_match('#url = (.+)#', $config, $matches)) {
                $info['remote_url'] = trim($matches[1]);
            }
        }

        // Get commit hash
        $process = new Process(['git', 'rev-parse', '--short', 'HEAD'], $this->project_dir);
        $process->run();
        if ($process->isSuccessful()) {
            $info['commit'] = trim($process->getOutput());
        }

        // Check for local changes
        $process = new Process(['git', 'status', '--porcelain'], $this->project_dir);
        $process->run();
        $info['has_local_changes'] = !empty(trim($process->getOutput()));

        return $info;
    }

    /**
     * Get Docker-specific information.
     */
    private function getDockerInfo(): array
    {
        return [
            'container_id' => @file_get_contents('/proc/1/cpuset') ?: null,
            'image' => getenv('DOCKER_IMAGE') ?: null,
        ];
    }
}
