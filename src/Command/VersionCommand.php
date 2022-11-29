<?php
/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2022 Jan Böhmer (https://github.com/jbtronics)
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

namespace App\Command;

use App\Services\GitVersionInfo;
use Shivas\VersioningBundle\Service\VersionManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class VersionCommand extends Command
{
    protected static $defaultName = 'partdb:version|app:version';

    protected VersionManagerInterface $versionManager;
    protected GitVersionInfo $gitVersionInfo;

    public function __construct(VersionManagerInterface $versionManager, GitVersionInfo $gitVersionInfo)
    {
        $this->versionManager = $versionManager;
        $this->gitVersionInfo = $gitVersionInfo;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Shows the currently installed version of Part-DB.')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $message = 'Part-DB version: '. $this->versionManager->getVersion()->toString();

        if ($this->gitVersionInfo->getGitBranchName() !== null) {
            $message .= ' Git branch: '. $this->gitVersionInfo->getGitBranchName();
            $message .= ', Git commit: '. $this->gitVersionInfo->getGitCommitHash();
        }

        $io->success($message);

        $io->info('PHP version: '.PHP_VERSION);
        $io->info('Symfony version: ' . $this->getApplication()->getVersion());
        $io->info('OS: '. php_uname());
        $io->info('PHP extension: '. implode(', ', get_loaded_extensions()));

        return 0;
    }
}