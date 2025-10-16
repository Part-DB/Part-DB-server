<?php

declare(strict_types=1);

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
namespace App\Twig;

use App\Entity\AssemblySystem\Assembly;
use App\Entity\Attachments\Attachment;
use App\Entity\Base\AbstractDBElement;
use App\Entity\Parts\PartCustomState;
use App\Entity\ProjectSystem\Project;
use App\Entity\LabelSystem\LabelProfile;
use App\Entity\Parts\Category;
use App\Entity\Parts\Footprint;
use App\Entity\Parts\Manufacturer;
use App\Entity\Parts\MeasurementUnit;
use App\Entity\Parts\Part;
use App\Entity\Parts\StorageLocation;
use App\Entity\Parts\Supplier;
use App\Entity\PriceInformations\Currency;
use App\Entity\UserSystem\Group;
use App\Entity\UserSystem\User;
use App\Exceptions\EntityNotSupportedException;
use App\Services\ElementTypeNameGenerator;
use App\Services\EntityURLGenerator;
use App\Services\Trees\TreeViewGenerator;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;
use Twig\TwigTest;

/**
 * @see \App\Tests\Twig\EntityExtensionTest
 */
final class EntityExtension extends AbstractExtension
{
    public function __construct(protected EntityURLGenerator $entityURLGenerator, protected TreeViewGenerator $treeBuilder, private readonly ElementTypeNameGenerator $nameGenerator)
    {
    }

    public function getTests(): array
    {
        return [
            /* Checks if the given variable is an entitity (instance of AbstractDBElement) */
            new TwigTest('entity', static fn($var) => $var instanceof AbstractDBElement),
        ];
    }

    public function getFunctions(): array
    {
        return [
            /* Returns a string representation of the given entity */
            new TwigFunction('entity_type', fn(object $entity): ?string => $this->getEntityType($entity)),
            /* Returns the URL to the given entity */
            new TwigFunction('entity_url', fn(AbstractDBElement $entity, string $method = 'info'): string => $this->generateEntityURL($entity, $method)),
            /* Returns the URL to the given entity in timetravel mode */
            new TwigFunction('timetravel_url', fn(AbstractDBElement $element, \DateTimeInterface $dateTime): ?string => $this->timeTravelURL($element, $dateTime)),
            /* Generates a JSON array of the given tree */
            new TwigFunction('tree_data', fn(AbstractDBElement $element, string $type = 'newEdit'): string => $this->treeData($element, $type)),

            /* Gets a human readable label for the type of the given entity */
            new TwigFunction('entity_type_label', fn(object|string $entity): string => $this->nameGenerator->getLocalizedTypeLabel($entity)),
        ];
    }

    public function timeTravelURL(AbstractDBElement $element, \DateTimeInterface $dateTime): ?string
    {
        try {
            return $this->entityURLGenerator->timeTravelURL($element, $dateTime);
        } catch (EntityNotSupportedException) {
            return null;
        }
    }

    public function treeData(AbstractDBElement $element, string $type = 'newEdit'): string
    {
        $tree = $this->treeBuilder->getTreeView($element::class, null, $type, $element);

        return json_encode($tree, JSON_THROW_ON_ERROR);
    }

    public function generateEntityURL(AbstractDBElement $entity, string $method = 'info'): string
    {
        return $this->entityURLGenerator->getURL($entity, $method);
    }

    public function getEntityType(object $entity): ?string
    {
        $map = [
            Part::class => 'part',
            Footprint::class => 'footprint',
            StorageLocation::class => 'storelocation',
            Manufacturer::class => 'manufacturer',
            Category::class => 'category',
            Project::class => 'device',
            Assembly::class => 'assembly',
            Attachment::class => 'attachment',
            Supplier::class => 'supplier',
            User::class => 'user',
            Group::class => 'group',
            Currency::class => 'currency',
            MeasurementUnit::class => 'measurement_unit',
            LabelProfile::class => 'label_profile',
            PartCustomState::class => 'part_custom_state',
        ];

        foreach ($map as $class => $type) {
            if ($entity instanceof $class) {
                return $type;
            }
        }

        return null;
    }
}
