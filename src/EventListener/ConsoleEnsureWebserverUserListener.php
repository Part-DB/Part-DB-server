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


namespace App\EventListener;

use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * This event listener is called before any console command is executed and should ensure that the webserver
 * user is used for all operations (and show a warning if not). This ensures that all files are created with the
 * correct permissions.
 * If the console is in non-interactive mode, a warning is shown, but the command is still executed.
 */
#[AsEventListener(ConsoleEvents::COMMAND)]
class ConsoleEnsureWebserverUserListener
{
    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private readonly string $project_root)
    {
    }

    public function __invoke(ConsoleCommandEvent $event): void
    {
        $input = $event->getInput();
        $io = new SymfonyStyle($event->getInput(), $event->getOutput());

        //Check if we are (not) running as the webserver user
        $webserver_user = $this->getWebserverUser();
        $running_user = $this->getRunningUser();

        //Check if we are trying to run as root
        if ($this->isRunningAsRoot()) {
            $io->warning('You are running this command as root. This is not recommended, as it can cause permission problems. Please run this command as the webserver user "'. ($webserver_user ?? '??') . '" instead.');
            $io->info('You might have already caused permission problems by running this command as wrong user. If you encounter issues with Part-DB, delete the var/cache directory completely and let it be recreated by Part-DB.');
            if ($input->isInteractive() && !$io->confirm('Do you want to continue?', false)) {
                $event->disableCommand();
            }

            return;
        }

        if ($webserver_user !== null && $running_user !== null && $webserver_user !== $running_user) {
            $io->warning('You are running this command as the user "' . $running_user . '". This is not recommended, as it can cause permission problems. Please run this command as the webserver user "' . $webserver_user . '" instead.');
            $io->info('You might have already caused permission problems by running this command as wrong user. If you encounter issues with Part-DB, delete the var/cache directory completely and let it be recreated by Part-DB.');
            if ($input->isInteractive() && !$io->confirm('Do you want to continue?', false)) {
                $event->disableCommand();
            }

            return;
        }
    }

    private function isRunningAsRoot(): bool
    {
        //If we are on windows, we can't run as root
        if (PHP_OS_FAMILY === 'Windows') {
            return false;
        }

        //Try to use the posix extension if available (Linux)
        if (function_exists('posix_geteuid')) {
            //Check if the current user is root
            return posix_geteuid() === 0;
        }

        //Otherwise we can't determine the username
        return false;
    }

    /**
     * Determines the username of the user who started the current script if possible.
     * Returns null if the username could not be determined.
     * @return string|null
     */
    private function getRunningUser(): ?string
    {
        //Try to use the posix extension if available (Linux)
        if (function_exists('posix_geteuid') && function_exists('posix_getpwuid')) {
            $id = posix_geteuid();

            $user = posix_getpwuid($id);
            //Try to get the username from the posix extension or return the id
            return $user['name'] ?? ("ID: " . $id);
        }

        //Otherwise we can't determine the username
        return $_SERVER['USERNAME'] ?? $_SERVER['USER'] ?? null;
    }

    private function getWebserverUser(): ?string
    {
        //Determine the webserver user, by checking who owns the uploads/ directory
        $path_to_check = $this->project_root . '/uploads/';

        //Determine the owner of this directory
        if (!is_dir($path_to_check)) {
            return null;
        }

        //If we are on windows we need some special logic
        if (PHP_OS_FAMILY === 'Windows') {
            //If we have the COM extension available, we can use it to determine the owner
            if (extension_loaded('com_dotnet')) {
                $su = new \COM("ADsSecurityUtility"); // Call interface
                //@phpstan-ignore-next-line
                $securityInfo = $su->GetSecurityDescriptor($path_to_check, 1, 1); // Call method
                return $securityInfo->owner; // Get file owner
            }

            //Otherwise we can't determine the owner
            return null;
        }

        //When we are on a POSIX system, we can use the fileowner function
        $owner = fileowner($path_to_check);

        if (function_exists('posix_getpwuid')) {
            $user = posix_getpwuid($owner);
            //Try to get the username from the posix extension or return the id
            return $user['name'] ?? ("ID: " . $owner);
        }

        return null;
    }

}