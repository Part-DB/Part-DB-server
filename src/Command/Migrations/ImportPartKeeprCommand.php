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

use App\Services\ImportExportSystem\PartkeeprImporter;
use App\Services\Misc\MySQLDumpXMLConverter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class ImportPartKeeprCommand extends Command
{

    protected static $defaultName = 'partdb:import-partkeepr';

    protected EntityManagerInterface $em;
    protected PartkeeprImporter $importer;
    protected MySQLDumpXMLConverter $xml_converter;

    public function __construct(EntityManagerInterface $em, PartkeeprImporter $importer, MySQLDumpXMLConverter $xml_converter)
    {
        parent::__construct(self::$defaultName);
        $this->em = $em;
        $this->importer = $importer;
        $this->xml_converter = $xml_converter;
    }

    protected function configure()
    {
        $this->setDescription('Import a PartKeepr database dump into Part-DB');

        $this->addArgument('file', InputArgument::REQUIRED, 'The file to which should be imported.');
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);

        $input_path = $input->getArgument('file');

        //Make more checks here
        //$io->confirm('This will delete all data in the database. Do you want to continue?', false);

        //Purge the databse, so we will not have any conflicts
        $this->importer->purgeDatabaseForImport();

        //Convert the XML file to an array
        $xml = file_get_contents($input_path);
        $data = $this->xml_converter->convertMySQLDumpXMLDataToArrayStructure($xml);

        //Import the data
        $this->doImport($io, $data);

        return 0;
    }

    private function doImport(SymfonyStyle $io, array $data)
    {
        //First import the distributors
        $io->info('Importing distributors...');
        $count = $this->importer->importDistributors($data);
        $io->success('Imported '.$count.' distributors.');

        //Import the measurement units
        $io->info('Importing part measurement units...');
        $count = $this->importer->importPartUnits($data);
        $io->success('Imported '.$count.' measurement units.');

        //Import manufacturers
        $io->info('Importing manufacturers...');
        $count = $this->importer->importManufacturers($data);
        $io->success('Imported '.$count.' manufacturers.');

        $io->info('Importing categories...');
        $count = $this->importer->importCategories($data);
        $io->success('Imported '.$count.' categories.');

        $io->info('Importing Footprints...');
        $count = $this->importer->importFootprints($data);
        $io->success('Imported '.$count.' footprints.');

        $io->info('Importing storage locations...');
        $count = $this->importer->importStorelocations($data);
        $io->success('Imported '.$count.' storage locations.');

        $io->info('Importing parts...');
        $count = $this->importer->importParts($data);
        $io->success('Imported '.$count.' parts.');
    }

}