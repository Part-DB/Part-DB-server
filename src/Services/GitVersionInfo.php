<?php
/**
 *
 * part-db version 0.1
 * Copyright (C) 2005 Christoph Lechner
 * http://www.cl-projects.de/
 *
 * part-db version 0.2+
 * Copyright (C) 2009 K. Jacobs and others (see authors.php)
 * http://code.google.com/p/part-db/
 *
 * Part-DB Version 0.4+
 * Copyright (C) 2016 - 2019 Jan Böhmer
 * https://github.com/jbtronics
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA
 *
 */

namespace App\Services;


use Symfony\Component\HttpKernel\KernelInterface;

class GitVersionInfo
{
    protected $project_dir;

    public function __construct(KernelInterface $kernel)
    {
        $this->project_dir = $kernel->getProjectDir();
    }

    /**
     * Get the Git branch name of the installed system
     * @return  string|null       The current git branch name. Null, if this is no Git installation
     */
    public function getGitBranchName()
    {
        if (file_exists($this->project_dir . '/.git/HEAD')) {
            $git = file($this->project_dir . '/.git/HEAD');
            $head = explode('/', $git[0], 3);
            return trim($head[2]);
        }
        return null; // this is not a Git installation
    }

    /**
     * Get hash of the last git commit (on remote "origin"!)
     * @note    If this method does not work, try to make a "git pull" first!
     * @param integer $length       if this is smaller than 40, only the first $length characters will be returned
     * @return string|null       The hash of the last commit, null If this is no Git installation
     */
    public function getGitCommitHash(int $length = 7)
    {
        $filename = $this->project_dir . '/.git/refs/remotes/origin/' . $this->getGitBranchName();
        if (file_exists($filename)) {
            $head = file($filename);
            $hash = $head[0];
            return substr($hash, 0, $length);
        }
        return null; // this is not a Git installation
    }
}