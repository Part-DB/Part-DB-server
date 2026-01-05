<?php
/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2026 Jan Böhmer (https://github.com/jbtronics)
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


namespace App\Command\Migrations;

use App\DataTables\Helpers\ColumnSortHelper;
use App\Entity\Parts\Manufacturer;
use App\Services\ImportExportSystem\PartKeeprImporter\PKImportHelper;
use Doctrine\ORM\EntityManagerInterface;

use Doctrine\ORM\Id\AssignedGenerator;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand('partdb:migrate-db', 'Migrate the database to a different platform')]
class DBMigrationCommand extends Command
{
    private readonly EntityManagerInterface $sourceEM;
    private readonly EntityManagerInterface $targetEM;

    public function __construct(private readonly ManagerRegistry $managerRegistry,
        private readonly PKImportHelper $importHelper,
    )
    {
        $this->sourceEM = $this->managerRegistry->getManager('migration_source');
        $this->targetEM = $this->managerRegistry->getManager('default');

        parent::__construct();
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Example migration logic (to be replaced with actual migration code)
        $io->info('Starting database migration...');

        //Disable all event listeners on target EM to avoid unwanted side effects
        $eventManager = $this->targetEM->getEventManager();
        foreach ($eventManager->getAllListeners() as $event => $listeners) {
            foreach ($listeners as $listener) {
                $eventManager->removeEventListener($event, $listener);
            }
        }

        $io->info('Clear target database...');
        $this->importHelper->purgeDatabaseForImport($this->targetEM, ['internal', 'migration_versions']);

        $metadata = $this->targetEM->getMetadataFactory()->getAllMetadata();

        $io->info('Modifying entity metadata for migration...');
        //First we modify each entity metadata to have an persist cascade on all relations
        foreach ($metadata as $metadatum) {
            $entityClass = $metadatum->getName();
            $io->writeln('Modifying cascade and ID settings for entity: ' . $entityClass, OutputInterface::VERBOSITY_VERBOSE);

            foreach ($metadatum->getAssociationNames() as $fieldName) {
                $mapping = $metadatum->getAssociationMapping($fieldName);
                $mapping->cascade = array_unique(array_merge($mapping->cascade, ['persist']));

                $metadatum->setIdGeneratorType(ClassMetadata::GENERATOR_TYPE_NONE);
                $metadatum->setIdGenerator(new AssignedGenerator());
            }
        }


        $io->progressStart(count($metadata));

        //Afterwards we migrate all entities
        foreach ($metadata as $metadatum) {
            //skip all superclasses
            if ($metadatum->isMappedSuperclass) {
                continue;
            }

            $entityClass = $metadatum->getName();

            $io->note('Migrating entity: ' . $entityClass);

            $repo = $this->sourceEM->getRepository($entityClass);
            $items = $repo->findAll();
            foreach ($items as $index => $item) {
                $this->targetEM->persist($item);
            }
            $this->targetEM->flush();
        }

        $io->progressFinish();

        //Migrate all manufacturers from source to target
        /*$manufacturerRepo = $this->sourceEM->getRepository(Manufacturer::class);
        $manufacturers = $manufacturerRepo->findAll();
        foreach ($manufacturers as $manufacturer) {
            $this->targetEM->persist($manufacturer);
        }
        $this->targetEM->flush();
        */

        $output->writeln('Database migration completed successfully.');

        if ($io->isVerbose()) {
            $io->info('Process took peak memory: ' . round(memory_get_peak_usage(true) / 1024 / 1024, 2) . ' MB');
        }

        return Command::SUCCESS;
    }
}
