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

namespace App\Command\Migrations;

use App\Services\ImportExportSystem\PartKeeprImporter\PKDatastructureImporter;
use App\Services\ImportExportSystem\PartKeeprImporter\MySQLDumpXMLConverter;
use App\Services\ImportExportSystem\PartKeeprImporter\PKImportHelper;
use App\Services\ImportExportSystem\PartKeeprImporter\PKPartImporter;
use App\Services\ImportExportSystem\PartKeeprImporter\PKOptionalImporter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[\Symfony\Component\Console\Attribute\AsCommand('partdb:migrations:import-partkeepr', 'Import a PartKeepr database XML dump into Part-DB')]
class ImportPartKeeprCommand extends Command
{

    protected EntityManagerInterface $em;
    protected MySQLDumpXMLConverter $xml_converter;
    protected PKDatastructureImporter $datastructureImporter;
    protected PKImportHelper $importHelper;
    protected PKPartImporter $partImporter;
    protected PKOptionalImporter $optionalImporter;

    public function __construct(EntityManagerInterface $em, MySQLDumpXMLConverter $xml_converter,
        PKDatastructureImporter $datastructureImporter, PKPartImporter $partImporter, PKImportHelper $importHelper,
        PKOptionalImporter $optionalImporter)
    {
        parent::__construct(self::$defaultName);
        $this->em = $em;
        $this->datastructureImporter = $datastructureImporter;
        $this->importHelper = $importHelper;
        $this->partImporter = $partImporter;
        $this->xml_converter = $xml_converter;
        $this->optionalImporter = $optionalImporter;
    }

    protected function configure()
    {
        $this->setHelp('This command allows you to import a PartKeepr database exported by mysqldump as XML file into Part-DB');

        $this->addArgument('file', InputArgument::REQUIRED, 'The file to which should be imported.');

        $this->addOption('--no-projects', null, InputOption::VALUE_NONE, 'Do not import projects.');
        $this->addOption('--import-users', null, InputOption::VALUE_NONE, 'Import users (passwords will not be imported).');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $input_path = $input->getArgument('file');
        $no_projects_import = $input->getOption('no-projects');
        $import_users = $input->getOption('import-users');

        $io->note('This command is still in development. If you encounter any problems, please report them to the issue tracker on GitHub.');
        $io->warning('This command will delete all existing data in the database (except users). Make sure that you have no important data in the database before you continue!');

        $io->ask('Please type "DELETE ALL DATA" to continue.', '', function ($answer) {
            if (strtoupper($answer) !== 'DELETE ALL DATA') {
                throw new \RuntimeException('You did not type "DELETE ALL DATA"!');
            }
            return $answer;
        });

        //Make more checks here
        //$io->confirm('This will delete all data in the database. Do you want to continue?', false);

        //Purge the databse, so we will not have any conflicts
        $this->importHelper->purgeDatabaseForImport();

        //Convert the XML file to an array
        $xml = file_get_contents($input_path);
        $data = $this->xml_converter->convertMySQLDumpXMLDataToArrayStructure($xml);

        if (!$this->importHelper->checkVersion($data)) {
            $db_version = $this->importHelper->getDatabaseSchemaVersion($data);
            $io->error('The version of the imported database is not supported! (Version: '.$db_version.')');
            return \Symfony\Component\Console\Command\Command::FAILURE;
        }

        //Import the mandatory data
        $this->doImport($io, $data);

        if (!$no_projects_import) {
            $io->info('Importing projects...');
            $count = $this->optionalImporter->importProjects($data);
            $io->success('Imported '.$count.' projects.');
        }

        if ($import_users) {
            $io->info('Importing users...');
            $count = $this->optionalImporter->importUsers($data);
            $io->success('Imported '.$count.' users.');
        }

        return \Symfony\Component\Console\Command\Command::SUCCESS;
    }

    private function doImport(SymfonyStyle $io, array $data): void
    {
        //First import the distributors
        $io->info('Importing distributors...');
        $count = $this->datastructureImporter->importDistributors($data);
        $io->success('Imported '.$count.' distributors.');

        //Import the measurement units
        $io->info('Importing part measurement units...');
        $count = $this->datastructureImporter->importPartUnits($data);
        $io->success('Imported '.$count.' measurement units.');

        //Import manufacturers
        $io->info('Importing manufacturers...');
        $count = $this->datastructureImporter->importManufacturers($data);
        $io->success('Imported '.$count.' manufacturers.');

        $io->info('Importing categories...');
        $count = $this->datastructureImporter->importCategories($data);
        $io->success('Imported '.$count.' categories.');

        $io->info('Importing Footprints...');
        $count = $this->datastructureImporter->importFootprints($data);
        $io->success('Imported '.$count.' footprints.');

        $io->info('Importing storage locations...');
        $count = $this->datastructureImporter->importStorelocations($data);
        $io->success('Imported '.$count.' storage locations.');

        $io->info('Importing parts...');
        $count = $this->partImporter->importParts($data);
        $io->success('Imported '.$count.' parts.');
    }

}