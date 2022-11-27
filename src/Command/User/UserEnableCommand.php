<?php

namespace App\Command\User;

use App\Entity\UserSystem\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class UserEnableCommand extends Command
{
    protected static $defaultName = 'partdb:users:enable|partdb:user:enable';

    protected EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager, string $name = null)
    {
        $this->entityManager = $entityManager;

        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Enables/Disable the login of one or more users')
            ->setHelp('This allows you to allow or prevent the login of certain user. Use the --disable option to disable the login for the given users')
            ->addArgument('users', InputArgument::IS_ARRAY, 'The usernames of the users to use')
            ->addOption('all', 'a', InputOption::VALUE_NONE, 'Enable/Disable all users')
            ->addOption('disable', 'd', InputOption::VALUE_NONE, 'Disable the login of the given users')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $usernames = $input->getArgument('users');
        $all_users = $input->getOption('all');
        $disabling = $input->getOption('disable');

        if(!$all_users && empty($usernames)) {
            $io->error('No users given! You have to pass atleast one username or use the --all option to use all users!');
            return self::FAILURE;
        }

        $repo = $this->entityManager->getRepository(User::class);

        $users = [];
        if($all_users) { //If we requested to change all users at once, then get all users from repo
            $users = $repo->findAll();
        } else { //Otherwise, fetch the users from DB
            foreach ($usernames as $username) {
                $user = $repo->findByEmailOrName($username);
                if ($user === null) {
                    $io->error('No user found with username: '.$username);
                    return self::FAILURE;
                }
                $users[] = $user;
            }
        }

        if ($disabling) {
            $io->note('The following users will be disabled:');
        } else {
            $io->note('The following users will be enabled:');
        }
        $io->table(['Username', 'Enabled/Disabled'],
            array_map(function(User $user) {
            return [$user->getFullName(true), $user->isDisabled() ? 'Disabled' : 'Enabled'];
        }, $users));

        if(!$io->confirm('Do you want to continue?')) {
            $io->warning('Aborting!');
            return self::SUCCESS;
        }

        foreach ($users as $user) {
            $user->setDisabled($disabling);
        }

        //Save the results
        $this->entityManager->flush();

        $io->success('Successfully changed the state of the users!');

        return self::SUCCESS;
    }
}