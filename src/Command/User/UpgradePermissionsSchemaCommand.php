<?php

declare(strict_types=1);

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

use Symfony\Component\Console\Attribute\AsCommand;
use App\Entity\UserSystem\Group;
use App\Entity\UserSystem\PermissionData;
use App\Entity\UserSystem\User;
use App\Services\LogSystem\EventCommentHelper;
use App\Services\UserSystem\PermissionSchemaUpdater;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand('partdb:users:upgrade-permissions-schema', '(Manually) upgrades the permissions schema of all users to the latest version.')]
final class UpgradePermissionsSchemaCommand extends Command
{
    public function __construct(private readonly PermissionSchemaUpdater $permissionSchemaUpdater, private readonly EntityManagerInterface $em, private readonly EventCommentHelper $eventCommentHelper)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->info('Target schema version number: '. PermissionData::CURRENT_SCHEMA_VERSION);

        //Retrieve all users and groups
        $users = $this->em->getRepository(User::class)->findAll();
        $groups = $this->em->getRepository(Group::class)->findAll();

        //Check which users and groups need an update
        $groups_to_upgrade = [];
        $users_to_upgrade = [];
        foreach ($groups as $group) {
            if ($this->permissionSchemaUpdater->isSchemaUpdateNeeded($group)) {
                $groups_to_upgrade[] = $group;
            }
        }
        foreach ($users as $user) {
            if ($this->permissionSchemaUpdater->isSchemaUpdateNeeded($user)) {
                $users_to_upgrade[] = $user;
            }
        }

        $io->info('Found '. count($groups_to_upgrade) .' groups and '. count($users_to_upgrade) .' users that need an update.');
        if ($groups_to_upgrade === [] && $users_to_upgrade === []) {
            $io->success('All users and group permissions schemas are up-to-date. No update needed.');

            return Command::SUCCESS;
        }

        //List all users and groups that need an update
        $io->section('Groups that need an update:');
        $io->listing(array_map(static fn(Group $group): string => $group->getName() . ' (ID: '. $group->getID() .', Current version: ' . $group->getPermissions()->getSchemaVersion() . ')', $groups_to_upgrade));

        $io->section('Users that need an update:');
        $io->listing(array_map(static fn(User $user): string => $user->getUsername() . ' (ID: '. $user->getID() .', Current version: ' . $user->getPermissions()->getSchemaVersion() . ')', $users_to_upgrade));

        if(!$io->confirm('Continue with the update?', false)) {
            $io->warning('Update aborted.');
            return Command::SUCCESS;
        }

        //Update all users and groups
        foreach ($groups_to_upgrade as $group) {
            $io->writeln('Updating group '. $group->getName() .' (ID: '. $group->getID() .') to schema version '. PermissionData::CURRENT_SCHEMA_VERSION .'...', OutputInterface::VERBOSITY_VERBOSE);
            $this->permissionSchemaUpdater->upgradeSchema($group);
        }
        foreach ($users_to_upgrade as $user) {
            $io->writeln('Updating user '. $user->getUsername() .' (ID: '. $user->getID() .') to schema version '. PermissionData::CURRENT_SCHEMA_VERSION .'...', OutputInterface::VERBOSITY_VERBOSE);
            $this->permissionSchemaUpdater->upgradeSchema($user);
        }

        $this->eventCommentHelper->setMessage('Manual permissions schema update via CLI');

        //Write changes to database
        $this->em->flush();

        $io->success('All users and groups have been updated to the latest permissions schema version.');

        return Command::SUCCESS;
    }
}
