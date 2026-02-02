<?php
/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2026 Jan Böhmer (https://github.com/jbtronics)
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

class CommandRunHelper
{
    private UpdateExecutor $updateExecutor;

    public function __construct(
        #[Autowire(param: 'kernel.project_dir')] private readonly string $project_dir
    )
    {
    }

    /**
     * Run a shell command with proper error handling.
     */
    public function runCommand(array $command, string $description, int $timeout = 120): string
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
            'HOME' => $this->project_dir.'/var/www-data-home',
            'COMPOSER_HOME' => $this->project_dir.'/var/composer',
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
}
