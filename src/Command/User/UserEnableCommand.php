<?php

declare(strict_types=1);

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
namespace App\Command\User;

use Symfony\Component\Console\Attribute\AsCommand;
use App\Entity\UserSystem\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand('partdb:users:enable|partdb:user:enable', 'Enables/Disable the login of one or more users')]
class UserEnableCommand extends Command
{
    public function __construct(protected EntityManagerInterface $entityManager, string $name = null)
    {
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this->setHelp('This allows you to allow or prevent the login of certain user. Use the --disable option to disable the login for the given users')
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
                if (!$user instanceof User) {
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
            array_map(static fn(User $user) => [$user->getFullName(true), $user->isDisabled() ? 'Disabled' : 'Enabled'], $users));

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
