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


namespace App\Command;

use App\Services\System\UpdateExecutor;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand('partdb:maintenance-mode', 'Enable/disable maintenance mode and set a message')]
class MaintenanceModeCommand extends Command
{
    public function __construct(
        private readonly UpdateExecutor $updateExecutor
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setDefinition([
                new InputOption('enable', null, InputOption::VALUE_NONE, 'Enable maintenance mode'),
                new InputOption('disable', null, InputOption::VALUE_NONE, 'Disable maintenance mode'),
                new InputOption('status', null, InputOption::VALUE_NONE, 'Show current maintenance mode status'),
                new InputOption('message', null, InputOption::VALUE_REQUIRED, 'Optional maintenance message (explicit option)'),
                new InputArgument('message_arg', InputArgument::OPTIONAL, 'Optional maintenance message as a positional argument (preferred when writing message directly)')
            ]);
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $enable = (bool)$input->getOption('enable');
        $disable = (bool)$input->getOption('disable');
        $status = (bool)$input->getOption('status');

        // Accept message either via --message option or as positional argument
        $optionMessage = $input->getOption('message');
        $argumentMessage = $input->getArgument('message_arg');

        // Prefer explicit --message option, otherwise use positional argument if provided
        $message = null;
        if (is_string($optionMessage) && $optionMessage !== '') {
            $message = $optionMessage;
        } elseif (is_string($argumentMessage) && $argumentMessage !== '') {
            $message = $argumentMessage;
        }

        // If no action provided, show help
        if (!$enable && !$disable && !$status) {
            $io->text('Maintenance mode command. See usage below:');
            $this->printHelp($io);
            return Command::SUCCESS;
        }

        if ($enable && $disable) {
            $io->error('Conflicting options: specify either --enable or --disable, not both.');
            return Command::FAILURE;
        }

        try {
            if ($status) {
                if ($this->updateExecutor->isMaintenanceMode()) {
                    $info = $this->updateExecutor->getMaintenanceInfo();
                    $reason = $info['reason'] ?? 'Unknown reason';
                    $enabledAt = $info['enabled_at'] ?? 'Unknown time';

                    $io->success(sprintf('Maintenance mode is ENABLED (since %s).', $enabledAt));
                    $io->text(sprintf('Reason: %s', $reason));
                } else {
                    $io->success('Maintenance mode is DISABLED.');
                }

                // If only status requested, exit
                if (!$enable && !$disable) {
                    return Command::SUCCESS;
                }
            }

            if ($enable) {
                // Use provided message or fallback to a default English message
                $reason = is_string($message)
                    ? $message
                    : 'The system is temporarily unavailable due to maintenance.';

                $this->updateExecutor->enableMaintenanceMode($reason);

                $io->success(sprintf('Maintenance mode enabled. Reason: %s', $reason));
            }

            if ($disable) {
                $this->updateExecutor->disableMaintenanceMode();
                $io->success('Maintenance mode disabled.');
            }

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $io->error(sprintf('Unexpected error: %s', $e->getMessage()));
            return Command::FAILURE;
        }
    }

    private function printHelp(SymfonyStyle $io): void
    {
        $io->writeln('');
        $io->writeln('Usage:');
        $io->writeln('  php bin/console partdb:maintenance_mode --enable [--message="Maintenance message"]');
        $io->writeln('  php bin/console partdb:maintenance_mode --enable "Maintenance message"');
        $io->writeln('  php bin/console partdb:maintenance_mode --disable');
        $io->writeln('  php bin/console partdb:maintenance_mode --status');
        $io->writeln('');
    }

}
