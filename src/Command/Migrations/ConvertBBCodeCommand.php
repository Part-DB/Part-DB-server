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

namespace App\Command\Migrations;

use App\Entity\AssemblySystem\Assembly;
use Symfony\Component\Console\Attribute\AsCommand;
use App\Entity\Attachments\AttachmentType;
use App\Entity\Base\AbstractNamedDBElement;
use App\Entity\ProjectSystem\Project;
use App\Entity\Parts\Category;
use App\Entity\Parts\Manufacturer;
use App\Entity\Parts\MeasurementUnit;
use App\Entity\Parts\Part;
use App\Entity\Parts\StorageLocation;
use App\Entity\Parts\Supplier;
use App\Entity\PriceInformations\Currency;
use App\Entity\UserSystem\Group;
use App\Helpers\BBCodeToMarkdownConverter;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

use function count;

/**
 * This command converts the BBCode used by old Part-DB versions (<1.0), to the current used Markdown format.
 */
#[AsCommand('partdb:migrations:convert-bbcode|app:convert-bbcode', 'Converts BBCode used in old Part-DB versions to newly used Markdown')]
class ConvertBBCodeCommand extends Command
{
    /**
     * @var string The LIKE criteria used to detect on SQL server if an entry contains BBCode
     */
    protected const BBCODE_CRITERIA = '%[%]%[/%]%';
    /**
     * @var string The regex (performed in PHP) used to check if a property really contains BBCODE
     */
    protected const BBCODE_REGEX = '/\\[.+\\].*\\[\\/.+\\]/';
    protected BBCodeToMarkdownConverter $converter;

    public function __construct(protected EntityManagerInterface $em, protected PropertyAccessorInterface $propertyAccessor)
    {
        $this->converter = new BBCodeToMarkdownConverter();

        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setHelp('Older versions of Part-DB (<1.0) used BBCode for rich text formatting.
                Part-DB now uses Markdown which offers more features but is incompatible with BBCode.
                When you upgrade from an pre 1.0 version you have to run this command to convert your comment fields');

        $this->addOption('dry-run', null, null, 'Do not save changes to DB. In combination with -v or -vv you can check what will be changed!');
    }

    /**
     * Returns a list which entities and which properties need to be checked.
     * @return array<class-string<AbstractNamedDBElement>, string[]>
     */
    protected function getTargetsLists(): array
    {
        return [
            Part::class => ['description', 'comment'],
            AttachmentType::class => ['comment'],
            StorageLocation::class => ['comment'],
            Project::class => ['comment'],
            Assembly::class => ['comment'],
            Category::class => ['comment'],
            Manufacturer::class => ['comment'],
            MeasurementUnit::class => ['comment'],
            Supplier::class => ['comment'],
            Currency::class => ['comment'],
            Group::class => ['comment'],
        ];
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $targets = $this->getTargetsLists();

        //Convert for every class target
        foreach ($targets as $class => $properties) {
            $io->section(sprintf('Convert entities of class %s', $class));
            $io->note(sprintf(
                'Search for entities of type %s that need conversion',
                $class
            ));
            //Determine which entities of this type we need to modify
            $repo = $this->em->getRepository($class);
            $qb = $repo->createQueryBuilder('e')
                ->select('e');
            //Add fields criteria
            foreach ($properties as $key => $property) {
                $qb->orWhere('e.'.$property.' LIKE ?'.$key);
                $qb->setParameter($key, static::BBCODE_CRITERIA);
            }

            //Fetch resulting classes
            $results = $qb->getQuery()->getResult();
            $io->note(sprintf('Found %d entities, that need to be converted!', is_countable($results) ? count($results) : 0));

            //In verbose mode print the names of the entities
            foreach ($results as $result) {
                /** @var AbstractNamedDBElement $result */
                $io->writeln(
                    'Convert entity: '.$result->getName().' ('.$result::class.': '.$result->getID().')',
                    OutputInterface::VERBOSITY_VERBOSE
                );
                foreach ($properties as $property) {
                    //Retrieve bbcode from entity
                    $bbcode = $this->propertyAccessor->getValue($result, $property);
                    //Check if the current property really contains BBCode
                    if (!preg_match(static::BBCODE_REGEX, (string) $bbcode)) {
                        continue;
                    }
                    $io->writeln(
                        'BBCode (old): '
                        .str_replace('\n', ' ', substr((string) $bbcode, 0, 255)),
                        OutputInterface::VERBOSITY_VERY_VERBOSE
                    );
                    $markdown = $this->converter->convert($bbcode);
                    $io->writeln(
                        'Markdown (new): '
                        .str_replace('\n', ' ', substr($markdown, 0, 255)),
                        OutputInterface::VERBOSITY_VERY_VERBOSE
                    );
                    $io->writeln('', OutputInterface::VERBOSITY_VERY_VERBOSE);
                    $this->propertyAccessor->setValue($result, $property, $markdown);
                }
            }
        }

        //If we are not in dry run, save changes to DB
        if (!$input->getOption('dry-run')) {
            $this->em->flush();
            $io->success('Changes saved to DB successfully!');
        }

        return Command::SUCCESS;
    }
}
