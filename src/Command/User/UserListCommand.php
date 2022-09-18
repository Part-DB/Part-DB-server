<?php

namespace App\Command\User;

use App\Entity\UserSystem\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class UserListCommand extends Command
{
    protected static $defaultName = 'partdb:users:list|users:list';

    protected EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;

        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Lists all users')
            ->setHelp('This command lists all users in the database.')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        //Get all users from database
        $users = $this->entityManager->getRepository(User::class)->findAll();

        $io->info(sprintf("Found %d users in database.", count($users)));

        $io->title('Users:');

        $table = new Table($output);
        $table->setHeaders(['ID', 'Username', 'Name', 'Email', 'Group']);

        foreach ($users as $user) {
            $table->addRow([
                $user->getId(),
                $user->getUsername(),
                $user->getFullName(),
                $user->getEmail(),
                $user->getGroup() !== null ? $user->getGroup()->getName() . ' (ID: ' . $user->getGroup()->getID() . ')' : 'No group',
            ]);
        }

        $table->render();


        return self::SUCCESS;
    }

}