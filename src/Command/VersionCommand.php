<?php

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

    protected $versionManager;
    protected $gitVersionInfo;

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

        $io->info('PHP version: '. phpversion());
        $io->info('Symfony version: ' . $this->getApplication()->getVersion());
        $io->info('OS: '. php_uname());
        $io->info('PHP extension: '. implode(', ', get_loaded_extensions()));

        return 0;
    }
}