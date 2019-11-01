<?php
/**
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony)
 *
 * Copyright (C) 2019 Jan Böhmer (https://github.com/jbtronics)
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

namespace App\Command;

use App\Entity\UserSystem\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

class SetPasswordCommand extends Command
{
    protected static $defaultName = 'app:set-password';

    protected $entityManager;
    protected $encoder;

    public function __construct(EntityManagerInterface $entityManager, UserPasswordEncoderInterface $passwordEncoder)
    {
        $this->entityManager = $entityManager;
        $this->encoder = $passwordEncoder;

        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setDescription('Sets the password of a user')
            ->setHelp('This password allows you to set the password of a user, without knowing the old password.')
            ->addArgument('user', InputArgument::REQUIRED, 'The name of the user')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        $user_name = $input->getArgument('user');

        /**
         * @var User
         */
        $users = $this->entityManager->getRepository(User::class)->findBy(['name' => $user_name]);
        $user = $users[0];

        if (null == $user) {
            $io->error(sprintf('No user with the given username %s found in the database!', $user_name));

            return;
        }

        $io->note('User found!');

        $proceed = $io->confirm(
            sprintf('You are going to change the password of %s with ID %d. Proceed?',
                $user->getFullName(true), $user->getID()));

        if (!$proceed) {
            return;
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
        $hash = $this->encoder->encodePassword($user, $new_password);
        $user->setPassword($hash);

        //And save it to databae
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $io->success('Password was set successful! You can now log in using the new password.');
    }
}
