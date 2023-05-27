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

namespace App\Command\User;

use App\Entity\UserSystem\User;
use App\Events\SecurityEvent;
use App\Events\SecurityEvents;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[\Symfony\Component\Console\Attribute\AsCommand('partdb:users:set-password|app:set-password|users:set-password|partdb:user:set-password', 'Sets the password of a user')]
class SetPasswordCommand extends Command
{
    protected EntityManagerInterface $entityManager;
    protected UserPasswordHasherInterface $encoder;
    protected EventDispatcherInterface $eventDispatcher;

    public function __construct(EntityManagerInterface $entityManager, UserPasswordHasherInterface $passwordEncoder, EventDispatcherInterface $eventDispatcher)
    {
        $this->entityManager = $entityManager;
        $this->encoder = $passwordEncoder;
        $this->eventDispatcher = $eventDispatcher;

        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setHelp('This password allows you to set the password of a user, without knowing the old password.')
            ->addArgument('user', InputArgument::REQUIRED, 'The username or email of the user')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $user_name = $input->getArgument('user');

        $user = $this->entityManager->getRepository(User::class)->findByEmailOrName($user_name);

        if (!$user) {
            $io->error(sprintf('No user with the given username %s found in the database!', $user_name));

            return \Symfony\Component\Console\Command\Command::FAILURE;
        }

        $io->note('User found!');

        if ($user->isSamlUser()) {
            $io->error('This user is a SAML user, so you can not change the password!');
            return \Symfony\Component\Console\Command\Command::FAILURE;
        }

        $proceed = $io->confirm(
            sprintf('You are going to change the password of %s with ID %d. Proceed?',
                $user->getFullName(true), $user->getID()));

        if (!$proceed) {
            return \Symfony\Component\Console\Command\Command::FAILURE;
        }

        $success = false;
        $new_password = '';

        while (!$success) {
            $pw1 = $io->askHidden('Please enter new password:');
            $pw2 = $io->askHidden('Please confirm:');
            if ($pw1 !== $pw2) {
                $io->error('The entered password did not match! Please try again.');
            } else {
                //Exit loop
                $success = true;
                $new_password = $pw1;
            }
        }

        //Encode password
        $hash = $this->encoder->hashPassword($user, $new_password);
        $user->setPassword($hash);

        //And save it to databae
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $io->success('Password was set successful! You can now log in using the new password.');

        $security_event = new SecurityEvent($user);
        $this->eventDispatcher->dispatch($security_event, SecurityEvents::PASSWORD_CHANGED);

        return \Symfony\Component\Console\Command\Command::SUCCESS;
    }
}
