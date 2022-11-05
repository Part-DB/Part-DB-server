<?php

namespace App\Command\User;

use App\Entity\UserSystem\User;
use App\Repository\UserRepository;
use App\Services\PermissionResolver;
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
    protected static $defaultName = 'partdb:users:permissions';
    protected static $defaultDescription = 'View the permissions of a given user';

    protected EntityManagerInterface $entityManager;
    protected UserRepository $userRepository;
    protected PermissionResolver $permissionResolver;
    protected TranslatorInterface $translator;

    public function __construct(EntityManagerInterface $entityManager, PermissionResolver $permissionResolver, TranslatorInterface $translator)
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
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $username = $input->getArgument('user');
        $inherit = !$input->getOption('noInherit');

        //Find user
        $io->note('Finding user with username: ' . $username);
        $user = $this->userRepository->findByEmailOrName($username);
        if ($user === null) {
            $io->error('No user found with username: ' . $username);
            return Command::FAILURE;
        }

        $io->note(sprintf('Found user %s with ID %d', $user->getFullName(true), $user->getId()));

        $this->renderPermissionTable($output, $user, $inherit);

        return Command::SUCCESS;
    }

    protected function renderPermissionTable(OutputInterface $output, User $user, bool $inherit): array
    {
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
                $table->addRow([
                    sprintf('%d-%d', $perm_index, $op_index),
                    $this->translator->trans($perm_obj['label']), //Permission name
                    $this->translator->trans($operation_obj['label']), //Operation name
                    $this->getPermissionValue($user, $perm_name, $operation_name, $inherit),
                ]);

                $op_index++;
            }
            $table->addRow(new TableSeparator());

            $perm_index++;
        }

        $table->render();
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
