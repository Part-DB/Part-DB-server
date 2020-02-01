<?php

declare(strict_types=1);

/**
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 * Copyright (C) 2019 Jan Böhmer (https://github.com/jbtronics)
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA
 */

namespace App\Command;

use App\Entity\PriceInformations\Currency;
use function count;
use Doctrine\ORM\EntityManagerInterface;
use Exchanger\Exception\Exception;
use function strlen;
use Swap\Builder;
use Swap\Swap;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class UpdateExchangeRatesCommand extends Command
{
    protected static $defaultName = 'app:update-exchange-rates';

    protected $base_current;
    protected $em;

    public function __construct(string $base_current, EntityManagerInterface $entityManager)
    {
        //$this->swap = $swap;
        $this->base_current = $base_current;

        $this->em = $entityManager;

        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Updates the currency exchange rates.')
            ->addArgument('iso_code', InputArgument::OPTIONAL | InputArgument::IS_ARRAY, 'The ISO Codes of the currencies that should be updated.')
            ->addOption('service', null, InputOption::VALUE_REQUIRED,
                'Which service should be used for requesting the exchange rates (e.g. fixer). See florianv/swap for full list.',
                'exchange_rates_api')
            ->addOption('api_key', null, InputOption::VALUE_REQUIRED,
                'The API key to use for the service.',
                null);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        //Check for valid base current
        if (3 !== strlen($this->base_current)) {
            $io->error('Choosen Base current is not valid. Check your settings!');

            return 1;
        }

        $io->note('Update currency exchange rates with base currency: '.$this->base_current);

        $service = $input->getOption('service');
        $api_key = $input->getOption('api_key');

        //Construct Swap with the given options
        $swap = (new Builder())
            ->add($service, ['access_key' => $api_key])
            ->build();

        //Check what currencies we need to update:
        $iso_code = $input->getArgument('iso_code');
        $repo = $this->em->getRepository(Currency::class);
        $candidates = [];

        if (! empty($iso_code)) {
            $candidates = $repo->findBy(['iso_code' => $iso_code]);
        } else {
            $candidates = $repo->findAll();
        }

        $success_counter = 0;

        //Iterate over each candidate and update exchange rate
        foreach ($candidates as $currency) {
            try {
                $rate = $swap->latest($currency->getIsoCode().'/'.$this->base_current);
                $currency->setExchangeRate((string) $rate->getValue());
                $io->note(sprintf('Set exchange rate of %s to %f', $currency->getIsoCode(), $currency->getExchangeRate()));
                $this->em->persist($currency);

                ++$success_counter;
            } catch (Exception $exception) {
                $io->warning(sprintf('Error updating %s:', $currency->getIsoCode()));
                $io->warning($exception->getMessage());
            }
        }

        //Save to database
        $this->em->flush();

        $io->success(sprintf('%d (of %d) currency exchange rates were updated.', $success_counter, count($candidates)));
        return 0;
    }
}
