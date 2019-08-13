<?php

namespace App\Command;

use App\Entity\PriceInformations\Currency;
use App\Form\AdminPages\CurrencyAdminForm;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Runner\Exception;
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

    protected function configure()
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

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);

        //Check for valid base current
        if (strlen($this->base_current) !== 3) {
            $io->error("Choosen Base current is not valid. Check your settings!");
            return;
        }

        $io->note('Update currency exchange rates with base currency: ' . $this->base_current);

        $service = $input->getOption('service');
        $api_key = $input->getOption('api_key');

        //Construct Swap with the given options
        $swap = (new Builder())
            ->add($service, ['access_key' => $api_key])
            ->build();

        //Check what currencies we need to update:
        $iso_code = $input->getArgument('iso_code');
        $repo = $this->em->getRepository(Currency::class);
        $candidates = array();

        if (!empty($iso_code)) {
            $candidates = $repo->findBy(['iso_code' => $iso_code]);
        } else {
            $candidates = $repo->findAll();
        }

        $success_counter = 0;

        //Iterate over each candidate and update exchange rate
        foreach ($candidates as $currency) {
            try {
                $rate = $swap->latest($currency->getIsoCode() . '/' . $this->base_current);
                $currency->setExchangeRate($rate->getValue());
                $io->note(sprintf('Set exchange rate of %s to %f', $currency->getIsoCode(), $currency->getExchangeRate()));
                $this->em->persist($currency);

                $success_counter++;
            } catch (\Exchanger\Exception\Exception $ex) {
                $io->warning(sprintf('Error updating %s:', $currency->getIsoCode()));
                $io->warning($ex->getMessage());
            }

        }

        //Save to database
        $this->em->flush();

        $io->success(sprintf('%d (of %d) currency exchange rates were updated.', $success_counter, count($candidates)));
    }
}
