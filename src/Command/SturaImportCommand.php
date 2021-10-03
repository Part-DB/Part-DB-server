<?php

namespace App\Command;

use App\Entity\Base\AbstractNamedDBElement;
use App\Entity\Parts\Category;
use App\Entity\Parts\Part;
use App\Entity\Parts\PartLot;
use App\Entity\Parts\Storelocation;
use App\Entity\Parts\Supplier;
use App\Entity\PriceInformations\Orderdetail;
use App\Entity\PriceInformations\Pricedetail;
use Brick\Math\BigDecimal;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use League\Csv\CharsetConverter;
use League\Csv\Reader;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class SturaImportCommand extends Command
{
    protected static $defaultName = 'app:stura-import';
    protected static $defaultDescription = 'Add a short description for your command';

    protected $entityManager;

    protected $dry_run;

    const NORMALIZE_MAP = [
        'Tag der Buchung' => 'booking_date',
        'Beleg-Nr.' => 'reference_id',
        'Anzahl' => 'amount',
        'Bezeichnung' => 'name',
        'Lieferant/Empfänger' => 'supplier',
        'Stückpreis in Euro' => 'price',
        'Standort' => 'location',
        'Anmerkungen' => 'comment',
        'Zugang' => 'incoming_date',
        'Abgang' => 'outcoming_date',
    ];

    public function __construct(string $name = null, EntityManagerInterface $entityManager)
    {
        parent::__construct($name);
        $this->entityManager = $entityManager;
    }

    protected function configure(): void
    {
        $this
            ->addArgument('input', InputArgument::REQUIRED, 'The input electoral register as CSV')
            ->addOption('dry', null, InputOption::VALUE_NONE, 'Dry run (Dont write changes to databse)')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $filename = $input->getArgument('input');
        $dry = $input->getOption('dry');
        $this->dry_run = $dry;

        $csv = Reader::createFromPath($filename, 'r');
        $csv->setDelimiter(';');
        $csv->setHeaderOffset(0);

        $stream = (new CharsetConverter())->inputEncoding('iso-8859-15')->convert($csv);

        $io->info('Use file ' . $csv->getPathname());
        $io->info(sprintf('File contains %d entries', $csv->count()));

        $progressBar = new ProgressBar($output, $csv->count());
        $progressBar->start();

        foreach ($stream as $entry)
        {
            $data = $this->normalizeData($entry);

            //Skip empty or not existing things
            if(empty($data['name']) || $data['amount'] === '0') {
                continue;
            }

            $existing_part = $this->tryToFindExistingPart($data);
            $io->info(sprintf('Try to find "%s" from %s.', $data['name'], $data['location']));
            if ($existing_part !== null) {
                $io->info(sprintf('Found existing part. ID: %d', $existing_part->getID()));
                $this->updateExistingPart($existing_part, $data);
            } else {
                $io->info('Part not found. Create a new one.');
                $new_part = $this->createPartFromData($data);
                $this->entityManager->persist($new_part);
            }

            $progressBar->advance();

        }

        $progressBar->finish();

        if (!$dry) {
            $this->entityManager->flush();
            $io->success('Successfully wrote changes to database!');
        } else {
            $io->warning('Dry run mode activated. Changes were not written to DB.');
        }



        return Command::SUCCESS;
    }

    protected function normalizeData(array $data): array
    {
        $return = [];
        foreach ($data as $key => $value) {
            if (isset(self::NORMALIZE_MAP[$key])) {
                $return[self::NORMALIZE_MAP[$key]] = $value;
            }
        }

        return $return;
    }

    protected function tryToFindExistingPart(array $data): ?Part
    {
        //Always return null
        return null;

        //Find location
        $repo = $this->entityManager->getRepository(Storelocation::class);
        $location = $repo->findOneBy(['name' => $data['location']]);
        //Return early if no location was created yet
        if ($location === null) {
            return null;
        }

        $qb = new QueryBuilder($this->entityManager);

        $qb->select('part')
            ->from(Part::class, 'part')
            ->leftJoin('part.partLots', 'lots')
            //->leftJoin('part.orderdetails', 'orderdetails')
            //->leftJoin('orderdetails.pricedetails', 'pricedetails')
            //->andWhere('pricedetails.price')
            ->where('lots.storage_location = ?1')
            ->andWhere('part.name = ?2')
            ->setParameter(1, $location)
            ->setParameter(2, $data['name'])
        ;

        $result = $qb->getQuery()->getResult();
        if (empty($result)) {
            return null;
        }

        $price_str = $this->getPriceAsFormattedString($data['amount']);
        //Check price to ensure it is the same part
        foreach ($result as $part) {
            /** @var Part $part */

            if (!empty($price_str)) {
                if (!$part->getOrderdetails()[0]->getPricedetails()[0]->getPrice() !== null) {
                    return null;
                }
            }
        }

        //Assume that part is always not existing
        return null;
    }

    protected function updateExistingPart(Part $existing_part, array $data): void
    {

    }

    protected function getPriceAsFormattedString(string $amount): string
    {
        $price_str = trim($amount, " \n\r\t\v\0€");
        //Use decimal point instead of comma
        $price_str = trim(str_replace(',', '.', $price_str));

        return $price_str;
    }

    protected function createPartFromData(array $data): Part
    {
        $part = new Part();
        $part->setName($data['name']);
        $part_lot = new PartLot();
        $part_lot->setAmount((float) $data['amount']);
        $part_lot->setStorageLocation($this->getStorageLocation($data['location']));
        $part->addPartLot($part_lot);

        $part->setCategory($this->getDummyCategory());

        //Add price information
        $price_str = $this->getPriceAsFormattedString($data['amount']);
        if (!empty($price_str)) {

            $orderdetail = new Orderdetail();
            $orderdetail->setSupplier($this->getDummySupplier());

            $orderdetail->addPricedetail(
                (new Pricedetail())
                    ->setMinDiscountQuantity(1)
                    ->setPrice(BigDecimal::of((float) $price_str))
            );

            $part->addOrderdetail($orderdetail);
        }

        //Add comment
        $comment = '';
        if (!empty($data['comment'])) {
            $comment .= $data['comment'] . "\n\n";
        }
        if (!empty($data['booking_date'])) {
            $comment .= 'Buchungsdatum: ' . $data['booking_date'] . "\n\n";
        }
        if (!empty($data['incoming_date'])) {
            $comment .= 'Zugang: ' . $data['incoming_date'] . "\n\n";
        }
        if (!empty($data['outcoming_date_date'])) {
            $comment .= 'Ausgang: ' . $data['outcoming_date_date'] . "\n\n";
        }
        if (!empty($data['reference_id'])) {
            $comment .= 'Beleg-Nr.: ' . $data['reference_id'] . "\n\n";
        }
        $part->setComment($comment);

        return $part;
    }

    protected function getStorageLocation(string $name)
    {
        $repo = $this->entityManager->getRepository(Storelocation::class);
        $existing = $repo->findOneBy(['name' => $name]);
        if ($existing) {
            return $existing;
        }

        $new = (new Storelocation())->setName($name);
        $this->entityManager->persist($new);
        if(!$this->dry_run) {
            $this->entityManager->flush();
        }

        return $new;
    }

    protected function getDummyCategory()
    {
        return $this->getDummyNamedEntity('Unsortiert', Category::class);
    }

    protected function getDummySupplier()
    {
        return $this->getDummyNamedEntity('Buchwert', Supplier::class);
    }

    protected function getDummyNamedEntity(string $name, string $class): AbstractNamedDBElement
    {
        $repo = $this->entityManager->getRepository($class);
        $existing = $repo->findOneBy(['name' => $name]);

        if($existing) {
            return $existing;
        }

        $new = (new $class)->setName($name);
        $this->entityManager->persist($new);
        if(!$this->dry_run) {
            $this->entityManager->flush();
        }

        return $new;
    }
}
