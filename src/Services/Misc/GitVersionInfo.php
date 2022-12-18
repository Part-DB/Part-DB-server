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

namespace App\Services\Misc;

use Symfony\Component\HttpKernel\KernelInterface;

class GitVersionInfo
{
    protected string $project_dir;

    public function __construct(KernelInterface $kernel)
    {
        $this->project_dir = $kernel->getProjectDir();
    }

    /**
     * Get the Git branch name of the installed system.
     *
     * @return string|null The current git branch name. Null, if this is no Git installation
     */
    public function getGitBranchName(): ?string
    {
        if (is_file($this->project_dir.'/.git/HEAD')) {
            $git = file($this->project_dir.'/.git/HEAD');
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
    public function getGitCommitHash(int $length = 7): ?string
    {
        $filename = $this->project_dir.'/.git/refs/remotes/origin/'.$this->getGitBranchName();
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
}
