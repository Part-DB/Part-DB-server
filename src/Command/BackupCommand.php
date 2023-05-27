<?php

namespace App\Command;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\ORM\EntityManagerInterface;
use PhpZip\Constants\ZipCompressionMethod;
use PhpZip\Exception\ZipException;
use PhpZip\ZipFile;
use Spatie\DbDumper\Databases\MySql;
use Spatie\DbDumper\DbDumper;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[\Symfony\Component\Console\Attribute\AsCommand('partdb:backup', 'Backup the files and the database of Part-DB')]
class BackupCommand extends Command
{
    private string $project_dir;
    private EntityManagerInterface $entityManager;

    public function __construct(string $project_dir, EntityManagerInterface $entityManager)
    {
        $this->project_dir = $project_dir;
        $this->entityManager = $entityManager;

        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setHelp('This command allows you to backup the files and database of Part-DB.');

        $this
            ->addArgument('output', InputArgument::REQUIRED, 'The file to which the backup should be written')
            ->addOption('overwrite', null, InputOption::VALUE_NONE, 'Overwrite the output file, if it already exists without asking')
            ->addOption('database', null, InputOption::VALUE_NONE, 'Backup the database')
            ->addOption('attachments', null, InputOption::VALUE_NONE, 'Backup the attachments files')
            ->addOption('config', null, InputOption::VALUE_NONE, 'Backup the config files')
            ->addOption('full', null, InputOption::VALUE_NONE, 'Backup database, attachments and config files')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $output_filepath = $input->getArgument('output');
        $backup_database = $input->getOption('database');
        $backup_attachments = $input->getOption('attachments');
        $backup_config = $input->getOption('config');
        $backup_full = $input->getOption('full');

        if ($backup_full) {
            $backup_database = true;
            $backup_attachments = true;
            $backup_config = true;
        }

        //When all options are false, we abort and show an error message
        if (! $backup_database && ! $backup_attachments && ! $backup_config) {
            $io->error('You have to select at least one option what to backup! Use --full to backup everything.');

            return Command::FAILURE;
        }

        $io->info('Backup Part-DB to '.$output_filepath);

        //Check if the file already exists
        if (file_exists($output_filepath)) {
            //Then ask the user, if he wants to overwrite the file
            if (!$io->confirm('The file '.realpath($output_filepath).' already exists. Do you want to overwrite it?', false)) {
                $io->error('Backup aborted!');

                return Command::FAILURE;
            }
        }

        $io->note('Starting backup...');

        //Open ZIP file
        $zip = new ZipFile();

        if ($backup_config) {
            $this->backupConfig($zip, $io);
        }
        if ($backup_attachments) {
            $this->backupAttachments($zip, $io);
        }
        if ($backup_database) {
            $this->backupDatabase($zip, $io);
        }

        $zip->setArchiveComment('Part-DB Backup of '.date('Y-m-d H:i:s'));

        //Write and close ZIP file
        try {
            $zip->saveAsFile($output_filepath);
        } catch (ZipException $e) {
            $io->error('Could not write ZIP file: '.$e->getMessage());

            return Command::FAILURE;
        }
        $zip->close();

        $io->success('Backup finished! You can find the backup file at '.$output_filepath);

        return Command::SUCCESS;
    }

    /**
     * Constructs the MySQL PDO DSN.
     * Taken from https://github.com/doctrine/dbal/blob/3.5.x/src/Driver/PDO/MySQL/Driver.php
     *
     * @param array $params
     */
    private function configureDumper(array $params, DbDumper $dumper): void
    {
        if (isset($params['host']) && $params['host'] !== '') {
            $dumper->setHost($params['host']);
        }

        if (isset($params['port'])) {
            $dumper->setPort($params['port']);
        }

        if (isset($params['dbname'])) {
            $dumper->setDbName($params['dbname']);
        }

        if (isset($params['unix_socket'])) {
            $dumper->setSocket($params['unix_socket']);
        }

        if (isset($params['user'])) {
            $dumper->setUserName($params['user']);
        }

        if (isset($params['password'])) {
            $dumper->setPassword($params['password']);
        }
    }

    protected function backupDatabase(ZipFile $zip, SymfonyStyle $io): void
    {
        $io->note('Backup database...');

        //Determine if we use MySQL or SQLite
        $connection = $this->entityManager->getConnection();
        if ($connection->getDatabasePlatform() instanceof AbstractMySQLPlatform) {
            try {
                $io->note('MySQL database detected. Dump DB to SQL using mysqldump...');
                $params = $connection->getParams();
                $dumper = MySql::create();
                $this->configureDumper($params, $dumper);

                $tmp_file = tempnam(sys_get_temp_dir(), 'partdb_sql_dump');

                $dumper->dumpToFile($tmp_file);
                $zip->addFile($tmp_file, 'mysql_dump.sql');
            } catch (\Exception $e) {
                $io->error('Could not dump database: '.$e->getMessage());
                $io->error('This can maybe be fixed by installing the mysqldump binary and adding it to the PATH variable!');
            }
        } elseif ($connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\SqlitePlatform) {
            $io->note('SQLite database detected. Copy DB file to ZIP...');
            $params = $connection->getParams();
            $zip->addFile($params['path'], 'var/app.db');
        } else {
            $io->error('Unknown database platform. Could not backup database!');
        }


    }

    protected function backupConfig(ZipFile $zip, SymfonyStyle $io): void
    {
        $io->note('Backing up config files...');

        //Add .env.local file if it exists
        $env_local_filepath = $this->project_dir.'/.env.local';
        if (file_exists($env_local_filepath)) {
            $zip->addFile($env_local_filepath, '.env.local');
        } else {
            $io->warning('Could not find .env.local file. You maybe use env variables, then you have to backup them manually!!');
        }

        //Add config/parameters.yaml and config/banner.md files
        $config_dir = $this->project_dir.'/config';
        $zip->addFile($config_dir.'/parameters.yaml', 'config/parameters.yaml');
        $zip->addFile($config_dir.'/banner.md', 'config/banner.md');
    }

    protected function backupAttachments(ZipFile $zip, SymfonyStyle $io): void
    {
        $io->note('Backing up attachments files...');

        //Add public attachments directory
        $attachments_dir = $this->project_dir.'/public/media/';
        $zip->addDirRecursive($attachments_dir, 'public/media/', ZipCompressionMethod::DEFLATED);

        //Add private attachments directory
        $attachments_dir = $this->project_dir.'/uploads/';
        $zip->addDirRecursive($attachments_dir, 'uploads/', ZipCompressionMethod::DEFLATED);
    }
}
