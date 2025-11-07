<?php
/**
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 * Copyright (C) 2019 - 2022 Jan Böhmer (https://github.com/jbtronics)
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

declare(strict_types=1);

namespace App\Command\Currencies;

use App\Settings\SystemSettings\LocalizationSettings;
use Symfony\Component\Console\Attribute\AsCommand;
use App\Entity\PriceInformations\Currency;
use App\Services\Tools\ExchangeRateUpdater;
use Doctrine\ORM\EntityManagerInterface;
use Exchanger\Exception\Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

use function count;
use function strlen;

#[AsCommand('partdb:currencies:update-exchange-rates|partdb:update-exchange-rates|app:update-exchange-rates', 'Updates the currency exchange rates.')]
class UpdateExchangeRatesCommand extends Command
{
    public function __construct(protected EntityManagerInterface $em, protected ExchangeRateUpdater $exchangeRateUpdater, private readonly LocalizationSettings $localizationSettings)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('iso_code', InputArgument::OPTIONAL | InputArgument::IS_ARRAY, 'The ISO Codes of the currencies that should be updated.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        //Check for valid base current
        if (3 !== strlen($this->localizationSettings->baseCurrency)) {
            $io->error('Chosen Base current is not valid. Check your settings!');

            return Command::FAILURE;
        }

        $io->note('Update currency exchange rates with base currency: '.$this->localizationSettings->baseCurrency);

        //Check what currencies we need to update:
        $iso_code = $input->getArgument('iso_code');
        $repo = $this->em->getRepository(Currency::class);

        $candidates = empty($iso_code) ? $repo->findAll() : $repo->findBy(['iso_code' => $iso_code]);

        $success_counter = 0;

        //Iterate over each candidate and update exchange rate
        foreach ($candidates as $currency) {
            /** @var Currency $currency */
            try {
                $this->exchangeRateUpdater->update($currency);
                $io->note(sprintf('Set exchange rate of %s to %f', $currency->getIsoCode(), $currency->getExchangeRate()->toFloat()));
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

        return Command::SUCCESS;
    }
}
