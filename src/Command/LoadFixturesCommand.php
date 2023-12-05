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

declare(strict_types=1);


namespace App\Command;

use App\Doctrine\Purger\ResetAutoIncrementORMPurger;
use App\Doctrine\Purger\DoNotUsePurgerFactory;
use App\Doctrine\Purger\ResetAutoIncrementPurgerFactory;
use Doctrine\Bundle\FixturesBundle\Purger\ORMPurgerFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * This command does basically the same as doctrine:fixtures:load, but it purges the database before loading the fixtures.
 * It does so in another transaction, so we can modify the purger to reset the autoincrement, which would not be possible
 * because the implicit commit otherwise.
 */
#[AsCommand(name: 'partdb:fixtures:load', description: 'Load test fixtures into the database and allows to reset the autoincrement before loading the fixtures.', hidden: true)]
class LoadFixturesCommand extends Command
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $ui = new SymfonyStyle($input, $output);

        $ui->warning('This command is for development and testing purposes only. It will purge the database and load fixtures afterwards. Do not use in production!');

        if (! $ui->confirm(sprintf('Careful, database "%s" will be purged. Do you want to continue?', $this->entityManager->getConnection()->getDatabase()), ! $input->isInteractive())) {
            return 0;
        }

        $factory = new ResetAutoIncrementPurgerFactory();
        $purger = $factory->createForEntityManager(null, $this->entityManager);

        $purger->purge();

        //Afterwards run the load fixtures command as normal, but with the --append option
        $new_input = new ArrayInput([
            'command' => 'doctrine:fixtures:load',
            '--append' => true,
        ]);

        $returnCode = $this->getApplication()?->doRun($new_input, $output);

        return $returnCode ?? Command::FAILURE;
    }
}