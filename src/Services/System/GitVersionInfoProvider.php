<?php
/**
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 * Copyright (C) 2019 - 2022 Jan Böhmer (https://github.com/jbtronics)
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

declare(strict_types=1);

namespace App\Services\System;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Process\Process;

/**
 * This service provides information about the current Git installation (if any).
 */
final readonly class GitVersionInfoProvider
{
    public function __construct(#[Autowire(param: 'kernel.project_dir')] private string $project_dir)
    {
    }

    /**
     * Check if the project directory is a Git repository.
     * @return bool
     */
    public function isGitRepo(): bool
    {
        return is_dir($this->getGitDirectory());
    }

    /**
     * Get the path to the Git directory of the installed system without a trailing slash.
     * Even if this is no Git installation, the path is returned.
     * @return string The path to the Git directory of the installed system
     */
    public function getGitDirectory(): string
    {
        return $this->project_dir . '/.git';
    }

    /**
     * Get the Git branch name of the installed system.
     *
     * @return string|null The current git branch name. Null, if this is no Git installation
     */
    public function getBranchName(): ?string
    {
        if (is_file($this->getGitDirectory() . '/HEAD')) {
            $git = file($this->getGitDirectory() . '/HEAD');
            $head = explode('/', $git[0], 3);

            if (!isset($head[2])) {
                return null;
            }

            return trim($head[2]);
        }

        return null; // this is not a Git installation
    }

    /**
     * Get hash of the last git commit (on remote "origin"!).
     *
     * If this method does not work, try to make a "git pull" first!
     *
     * @param int $length if this is smaller than 40, only the first $length characters will be returned
     *
     * @return string|null The hash of the last commit, null If this is no Git installation
     */
    public function getCommitHash(int $length = 8): ?string
    {
        $filename = $this->getGitDirectory() . '/refs/remotes/origin/'.$this->getBranchName();
        if (is_file($filename)) {
            $head = file($filename);

            if (!isset($head[0])) {
                return null;
            }

            $hash = $head[0];

            return substr($hash, 0, $length);
        }

        return null; // this is not a Git installation
    }

    /**
     * Get the Git remote URL of the installed system.
     */
    public function getRemoteURL(): ?string
    {
        // Get remote URL
        $configFile = $this->getGitDirectory() . '/config';
        if (file_exists($configFile)) {
            $config = file_get_contents($configFile);
            if (preg_match('#url = (.+)#', $config, $matches)) {
                return trim($matches[1]);
            }
        }

        return null; // this is not a Git installation
    }

    /**
     * Check if there are local changes in the Git repository.
     * Attention: This runs a git command, which might be slow!
     * @return bool|null True if there are local changes, false if not, null if this is not a Git installation
     */
    public function hasLocalChanges(): ?bool
    {
        $process = new Process(['git', 'status', '--porcelain'], $this->project_dir);
        $process->run();
        if (!$process->isSuccessful()) {
            return null; // this is not a Git installation
        }
        return !empty(trim($process->getOutput()));
    }
}
