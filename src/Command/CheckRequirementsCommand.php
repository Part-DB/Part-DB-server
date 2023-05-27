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

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;

#[\Symfony\Component\Console\Attribute\AsCommand('partdb:check-requirements', 'Checks if the requirements Part-DB needs or recommends are fulfilled.')]
class CheckRequirementsCommand extends Command
{
    protected ContainerBagInterface $params;

    public function __construct(ContainerBagInterface $params)
    {
        $this->params = $params;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('only_issues', 'i', InputOption::VALUE_NONE, 'Only show issues, not success messages.')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $only_issues = (bool) $input->getOption('only_issues');

        $io->title('Checking PHP configuration...');
        $this->checkPHP($io, $only_issues);

        $io->title('Checking PHP extensions...');
        $this->checkPHPExtensions($io, $only_issues);

        $io->title('Checking Part-DB configuration');
        $this->checkPartDBConfig($io, $only_issues);

        return self::SUCCESS;

    }

    protected function checkPHP(SymfonyStyle $io, $only_issues = false): void
    {
        //Check PHP versions
        $io->isVerbose() && $io->comment('Checking PHP version...');
        //We recommend PHP 8.2, but 8.1 is the minimum
        if (PHP_VERSION_ID < 80200) {
            $io->warning('You are using PHP '. PHP_VERSION .'. This will work, but a newer version is recommended.');
        } else {
            !$only_issues && $io->success('PHP version is sufficient.');
        }

        //Check if opcache is enabled
        $io->isVerbose() && $io->comment('Checking Opcache...');
        $opcache_enabled = ini_get('opcache.enable') === '1';
        if (!$opcache_enabled) {
            $io->warning('Opcache is not enabled. This will work, but performance will be better with opcache enabled. Set opcache.enable=1 in your php.ini to enable it');
        } else {
            !$only_issues && $io->success('Opcache is enabled.');
        }

        //Check if opcache is configured correctly
        $io->isVerbose() && $io->comment('Checking Opcache configuration...');
        if ($opcache_enabled && (ini_get('opcache.memory_consumption') < 256 || ini_get('opcache.max_accelerated_files') < 20000)) {
            $io->warning('Opcache configuration can be improved. See https://symfony.com/doc/current/performance.html for more info.');
        } else {
            !$only_issues && $io->success('Opcache configuration is already performance optimized.');
        }
    }

    protected function checkPartDBConfig(SymfonyStyle $io, $only_issues = false): void
    {
        //Check if APP_ENV is set to prod
        $io->isVerbose() && $io->comment('Checking debug mode...');
        if($this->params->get('kernel.debug')) {
            $io->warning('You have activated debug mode, this is will leak informations in a production environment.');
        } else {
            !$only_issues && $io->success('Debug mode disabled.');
        }

    }

    protected function checkPHPExtensions(SymfonyStyle $io, $only_issues = false): void
    {
        //Get all installed PHP extensions
        $extensions = get_loaded_extensions();
        $io->isVerbose() && $io->comment('Your PHP installation has '. count($extensions) .' extensions installed: '. implode(', ', $extensions));

        $db_drivers_count = 0;
        if(!in_array('pdo_mysql', $extensions)) {
            $io->error('pdo_mysql is not installed. You will not be able to use MySQL databases.');
        } else {
            !$only_issues && $io->success('PHP extension pdo_mysql is installed.');
            $db_drivers_count++;
        }

        if(!in_array('pdo_sqlite', $extensions)) {
            $io->error('pdo_sqlite is not installed. You will not be able to use SQLite. databases');
        } else {
            !$only_issues && $io->success('PHP extension pdo_sqlite is installed.');
            $db_drivers_count++;
        }

        $io->isVerbose() && $io->comment('You have '. $db_drivers_count .' database drivers installed.');
        if ($db_drivers_count === 0) {
            $io->error('You have no database drivers installed. You have to install at least one database driver!');
        }

        if(!in_array('curl', $extensions)) {
            $io->warning('curl extension is not installed. Install curl extension for better performance');
        } else {
            !$only_issues && $io->success('PHP extension curl is installed.');
        }

        $gd_installed = in_array('gd', $extensions);
        if(!$gd_installed) {
            $io->error('GD is not installed. GD is required for image processing.');
        } else {
            !$only_issues && $io->success('PHP extension GD is installed.');
        }

        //Check if GD has jpeg support
        $io->isVerbose() && $io->comment('Checking if GD has jpeg support...');
        if ($gd_installed) {
            $gd_info = gd_info();
            if($gd_info['JPEG Support'] === false) {
                $io->warning('Your GD does not have jpeg support. You will not be able to generate thumbnails of jpeg images.');
            } else {
                !$only_issues && $io->success('GD has jpeg support.');
            }

            if($gd_info['PNG Support'] === false) {
                $io->warning('Your GD does not have png support. You will not be able to generate thumbnails of png images.');
            } else {
                !$only_issues && $io->success('GD has png support.');
            }

            if($gd_info['WebP Support'] === false) {
                $io->warning('Your GD does not have WebP support. You will not be able to generate thumbnails of WebP images.');
            } else {
                !$only_issues && $io->success('GD has WebP support.');
            }
        }




    }

}