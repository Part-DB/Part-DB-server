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

use App\Services\ImportExportSystem\PartKeeprImporter\PKImportHelper;
use Doctrine\Bundle\DoctrineBundle\ConnectionFactory;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\Migrations\Configuration\EntityManager\ExistingEntityManager;
use Doctrine\Migrations\Configuration\Migration\ExistingConfiguration;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Migrations\DependencyFactory;
use Doctrine\ORM\Id\AssignedGenerator;
use Doctrine\ORM\Mapping\ClassMetadata;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand('partdb:migrations:convert-db-platform', 'Convert the database to a different platform')]
class DBPlatformConvertCommand extends Command
{

    public function __construct(
        private readonly EntityManagerInterface $targetEM,
        private readonly PKImportHelper $importHelper,
        private readonly DependencyFactory $dependencyFactory,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $kernelProjectDir,
    )
    {
        parent::__construct();
    }

    public function configure(): void
    {
        $this
            ->setHelp('This command allows you to migrate the database from one database platform to another (e.g. from MySQL to PostgreSQL).')
            ->addArgument('url', InputArgument::REQUIRED, 'The database connection URL of the source database to migrate from');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $sourceEM = $this->getSourceEm($input->getArgument('url'));

        //Check that both databases are not using the same driver
        if ($sourceEM->getConnection()->getDatabasePlatform()::class === $this->targetEM->getConnection()->getDatabasePlatform()::class) {
            $io->warning('Source and target database are using the same database platform / driver. This command is only intended to migrate between different database platforms (e.g. from MySQL to PostgreSQL).');
            if (!$io->confirm('Do you want to continue anyway?', false)) {
                $io->info('Aborting migration process.');
                return Command::SUCCESS;
            }
        }


        $this->ensureVersionUpToDate($sourceEM);

        $io->note('This command is still in development. If you encounter any problems, please report them to the issue tracker on GitHub.');
        $io->warning(sprintf('This command will delete all existing data in the target database "%s". Make sure that you have no important data in the database before you continue!',
            $this->targetEM->getConnection()->getDatabase() ?? 'unknown'
        ));

        $io->ask('Please type "DELETE ALL DATA" to continue.', '', function ($answer) {
            if (strtoupper($answer) !== 'DELETE ALL DATA') {
                throw new \RuntimeException('You did not type "DELETE ALL DATA"!');
            }
            return $answer;
        });


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

        //Afterward we migrate all entities
        foreach ($metadata as $metadatum) {
            //skip all superclasses
            if ($metadatum->isMappedSuperclass) {
                continue;
            }

            $entityClass = $metadatum->getName();

            $io->note('Migrating entity: ' . $entityClass);

            $repo = $sourceEM->getRepository($entityClass);
            $items = $repo->findAll();
            foreach ($items as $index => $item) {
                $this->targetEM->persist($item);
            }
            $this->targetEM->flush();
        }

        $io->progressFinish();


        //Fix sequences / auto increment values on target database
        $io->info('Fixing sequences / auto increment values on target database...');
        $this->fixAutoIncrements($this->targetEM);

        $io->success('Database migration completed successfully.');

        if ($io->isVerbose()) {
            $io->info('Process took peak memory: ' . round(memory_get_peak_usage(true) / 1024 / 1024, 2) . ' MB');
        }

        return Command::SUCCESS;
    }

    /**
     * Construct a source EntityManager based on the given connection URL
     * @param  string  $url
     * @return EntityManagerInterface
     */
    private function getSourceEm(string $url): EntityManagerInterface
    {
        //Replace any %kernel.project_dir% placeholders
        $url = str_replace('%kernel.project_dir%', $this->kernelProjectDir, $url);

        $connectionFactory = new ConnectionFactory();
        $connection = $connectionFactory->createConnection(['url' => $url]);
        return new EntityManager($connection, $this->targetEM->getConfiguration());
    }

    private function ensureVersionUpToDate(EntityManagerInterface $sourceEM): void
    {
        //Ensure that target database is up to date
        $migrationStatusCalculator = $this->dependencyFactory->getMigrationStatusCalculator();
        $newMigrations = $migrationStatusCalculator->getNewMigrations();
        if (count($newMigrations->getItems()) > 0) {
            throw new \RuntimeException("Target database is not up to date. Please run all migrations (with doctrine:migrations:migrate) before starting the migration process.");
        }

        $sourceDependencyLoader = DependencyFactory::fromEntityManager(new ExistingConfiguration($this->dependencyFactory->getConfiguration()), new ExistingEntityManager($sourceEM));
        $sourceMigrationStatusCalculator = $sourceDependencyLoader->getMigrationStatusCalculator();
        $sourceNewMigrations = $sourceMigrationStatusCalculator->getNewMigrations();
        if (count($sourceNewMigrations->getItems()) > 0) {
            throw new \RuntimeException("Source database is not up to date. Please run all migrations (with doctrine:migrations:migrate) on the source database before starting the migration process.");
        }
    }

    private function fixAutoIncrements(EntityManagerInterface $em): void
    {
        $connection = $em->getConnection();
        $platform = $connection->getDatabasePlatform();

        if ($platform instanceof PostgreSQLPlatform) {
            $connection->executeStatement(
            //From: https://wiki.postgresql.org/wiki/Fixing_Sequences
                <<<SQL
                SELECT 'SELECT SETVAL(' ||
                    quote_literal(quote_ident(PGT.schemaname) || '.' || quote_ident(S.relname)) ||
                    ', COALESCE(MAX(' ||quote_ident(C.attname)|| '), 1) ) FROM ' ||
                    quote_ident(PGT.schemaname)|| '.'||quote_ident(T.relname)|| ';'
                FROM pg_class AS S,
                     pg_depend AS D,
                     pg_class AS T,
                     pg_attribute AS C,
                     pg_tables AS PGT
                WHERE S.relkind = 'S'
                    AND S.oid = D.objid
                    AND D.refobjid = T.oid
                    AND D.refobjid = C.attrelid
                    AND D.refobjsubid = C.attnum
                    AND T.relname = PGT.tablename
                ORDER BY S.relname;
                SQL);
        }
    }
}
