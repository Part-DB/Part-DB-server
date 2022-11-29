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

namespace App\Command\User;

use App\Entity\UserSystem\User;
use App\Repository\UserRepository;
use App\Services\UserSystem\PermissionManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\Translation\TranslatorInterface;

class UsersPermissionsCommand extends Command
{
    protected static $defaultName = 'partdb:users:permissions|partdb:user:permissions';
    protected static $defaultDescription = 'View and edit the permissions of a given user';

    protected EntityManagerInterface $entityManager;
    protected UserRepository $userRepository;
    protected PermissionManager $permissionResolver;
    protected TranslatorInterface $translator;

    public function __construct(EntityManagerInterface $entityManager, PermissionManager $permissionResolver, TranslatorInterface $translator)
    {
        $this->entityManager = $entityManager;
        $this->userRepository = $entityManager->getRepository(User::class);
        $this->permissionResolver = $permissionResolver;
        $this->translator = $translator;

        parent::__construct(self::$defaultName);
    }

    protected function configure(): void
    {
        $this
            ->addArgument('user', InputArgument::REQUIRED, 'The username of the user to view')
            ->addOption('noInherit', null, InputOption::VALUE_NONE, 'Do not inherit permissions from groups')
            ->addOption('edit', '', InputOption::VALUE_NONE, 'Edit the permissions of the user')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $username = $input->getArgument('user');
        $edit_mode = $input->getOption('edit');
        $inherit = !$input->getOption('noInherit') && !$edit_mode; //Show the non inherited perms in edit mode

        //Find user
        $io->note('Finding user with username: ' . $username);
        $user = $this->userRepository->findByEmailOrName($username);
        if ($user === null) {
            $io->error('No user found with username: ' . $username);
            return Command::FAILURE;
        }

        $io->note(sprintf('Found user %s with ID %d', $user->getFullName(true), $user->getId()));

        $edit_mapping = $this->renderPermissionTable($output, $user, $inherit);

        while($edit_mode) {
            $index_to_edit = $io->ask('Which permission do you want to edit? Enter the index (e.g. 2-4) to edit, * for all permissions or "q" to quit', 'q');
            if ($index_to_edit === 'q') {
                break;
            }

            if (!isset($edit_mapping[$index_to_edit]) && $index_to_edit !== '*') {
                $io->error('Invalid index');
                continue;
            }

            if ($index_to_edit === '*') {
                $io->warning('You are about to edit all permissions. This will overwrite all permissions!');
            } else {
                [$perm_to_edit, $op_to_edit] = $edit_mapping[$index_to_edit];
                $io->note('Editing permission ' . $perm_to_edit . ' with operation <options=bold>' . $op_to_edit);
            }


            $new_value_str = $io->ask('Enter the new value for the permission (A = allow, D = disallow, I = inherit)');
            switch (strtolower($new_value_str)) {
                case 'a':
                case 'allow':
                    $new_value = true;
                    break;
                case 'd':
                case 'disallow':
                    $new_value = false;
                    break;
                case 'i':
                case 'inherit':
                    $new_value = null;
                    break;
                default:
                    $io->error('Invalid value');
                    continue 2;
            }

            if ($index_to_edit === '*') {
                $this->permissionResolver->setAllPermissions($user, $new_value);
                $io->success('Permission updated successfully');
                $this->entityManager->flush();

                break; //Show the new table
            }

            if (isset($op_to_edit, $perm_to_edit)) {
                $this->permissionResolver->setPermission($user, $perm_to_edit, $op_to_edit, $new_value);
            } else {
                throw new \RuntimeException('Erorr while editing permission');
            }

            //Ensure that all operations are set accordingly
            $this->permissionResolver->ensureCorrectSetOperations($user);
            $io->success('Permission updated successfully');

            //Save to DB
            $this->entityManager->flush();
        }

        if ($edit_mode) {
            $io->note('New permissions:');
            $this->renderPermissionTable($output, $user, true);
        }

        return Command::SUCCESS;
    }

    protected function renderPermissionTable(OutputInterface $output, User $user, bool $inherit): array
    {
        //We fill this with index and perm/op combination for later use
        $edit_mapping = [];

        $table = new Table($output);

        $perms = $this->permissionResolver->getPermissionStructure()['perms'];

        if ($inherit) {
            $table->setHeaderTitle('Inherited Permissions for '.$user->getFullName(true));
        } else {
            $table->setHeaderTitle('Non Inherited Permissions for '.$user->getFullName(true));
        }

        $table->setHeaders(['', 'Permission', 'Operation', 'Value']);

        $perm_index = '1';

        foreach ($perms as $perm_name => $perm_obj) {
            $op_index = 1;
            foreach ($perm_obj['operations'] as $operation_name => $operation_obj) {

                $index = sprintf('%d-%d', $perm_index, $op_index);

                $table->addRow([
                    $index,
                    $this->translator->trans($perm_obj['label']), //Permission name
                    $this->translator->trans($operation_obj['label']), //Operation name
                    $this->getPermissionValue($user, $perm_name, $operation_name, $inherit),
                ]);

                //Save index and perm/op combination for editing later
                $edit_mapping[$index] = [
                    $perm_name, $operation_name,
                ];

                $op_index++;
            }
            $table->addRow(new TableSeparator());

            $perm_index++;
        }

        $table->render();

        return $edit_mapping;
    }

    protected function getPermissionValue(User $user, string $permission, string $op, bool $inherit = true): string
    {
        if ($inherit) {
            $permission_value = $this->permissionResolver->inherit($user, $permission, $op);
        } else {
            $permission_value = $this->permissionResolver->dontInherit($user, $permission, $op);
        }

        if ($permission_value === true) {
            return '<fg=green>Allow</>';
        } else if ($permission_value === false) {
            return '<fg=red>Disallow</>';
        } else if ($permission_value === null && !$inherit) {
            return '<fg=blue>Inherit</>';
        } else if ($permission_value === null && $inherit) {
            return '<fg=red>Disallow (Inherited)</>';
        }

        return '???';
    }
}
