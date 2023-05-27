<?php
/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2023 Jan Böhmer (https://github.com/jbtronics)
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

use App\Entity\UserSystem\User;
use App\Security\SamlUserFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[\Symfony\Component\Console\Attribute\AsCommand('partdb:user:convert-to-saml-user|partdb:users:convert-to-saml-user', 'Converts a local user to a SAML user (and vice versa)')]
class ConvertToSAMLUserCommand extends Command
{
    protected static $defaultDescription = 'Converts a local user to a SAML user (and vice versa)';

    protected EntityManagerInterface $entityManager;
    protected bool $saml_enabled;

    public function __construct(EntityManagerInterface $entityManager, bool $saml_enabled)
    {
        parent::__construct();
        $this->entityManager = $entityManager;
        $this->saml_enabled = $saml_enabled;
    }

    protected function configure(): void
    {
        $this->setHelp('This converts a local user, which can login via the login form, to a SAML user, which can only login via SAML. This is useful if you want to migrate from a local user system to a SAML user system.')
            ->addArgument('user', InputArgument::REQUIRED, 'The username (or email) of the user')
            ->addOption('to-local', null, InputOption::VALUE_NONE, 'Converts a SAML user to a local user')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $user_name = $input->getArgument('user');
        $to_local = $input->getOption('to-local');

        if (!$this->saml_enabled && !$to_local) {
            $io->confirm('SAML login is not configured. You will not be able to login with this user anymore, when SSO is not configured. Do you really want to continue?');
        }

        /** @var User $user */
        $user = $this->entityManager->getRepository(User::class)->findByEmailOrName($user_name);

        if (!$user) {
            $io->error('User not found!');

            return \Symfony\Component\Console\Command\Command::FAILURE;
        }

        $io->info('User found: '.$user->getFullName(true) . ': '.$user->getEmail().' [ID: ' . $user->getID() . ']');

        if ($to_local) {
            return $this->toLocal($user, $io);
        }

        return $this->toSAML($user, $io);
    }

    public function toLocal(User $user, SymfonyStyle $io): int
    {
        $io->confirm('You are going to convert a SAML user to a local user. This means, that the user can only login via the login form. '
            . 'The permissions and groups settings of the user will remain unchanged. Do you really want to continue?');

        $user->setSAMLUser(false);
        $user->setPassword(SamlUserFactory::SAML_PASSWORD_PLACEHOLDER);

        $this->entityManager->flush();

        $io->success('User converted to local user! You will need to set a password for this user, before you can login with it.');

        return 0;
    }

    public function toSAML(User $user, SymfonyStyle $io): int
    {
        $io->confirm('You are going to convert a local user to a SAML user. This means, that the user can only login via SAML afterwards. The password in the DB will be removed. '
            . 'The permissions and groups settings of the user will remain unchanged. Do you really want to continue?');

        $user->setSAMLUser(true);
        $user->setPassword(SamlUserFactory::SAML_PASSWORD_PLACEHOLDER);

        $this->entityManager->flush();

        $io->success('User converted to SAML user! You can now login with this user via SAML.');

        return 0;
    }

}