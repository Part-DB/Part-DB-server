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
use Twig\Attribute\AsTwigFunction;
use Twig\Attribute\AsTwigTest;
use Twig\DeprecatedCallableInfo;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;
use Twig\TwigTest;

/**
 * @see \App\Tests\Twig\EntityExtensionTest
 */
final readonly class EntityExtension
{
    public function __construct(private EntityURLGenerator $entityURLGenerator, private TreeViewGenerator $treeBuilder, private ElementTypeNameGenerator $nameGenerator)
    {
    }

    /**
     * Checks if the given variable is an entity (instance of AbstractDBElement).
     */
    #[AsTwigTest("entity")]
    public function entityTest(mixed $var): bool
    {
        return $var instanceof AbstractDBElement;
    }


    /**
     * Returns a string representation of the given entity
     */
    #[AsTwigFunction("entity_type")]
    public function entityType(object $entity): ?string
    {
        $map = [
            Part::class => 'part',
            Footprint::class => 'footprint',
            StorageLocation::class => 'storelocation',
            Manufacturer::class => 'manufacturer',
            Category::class => 'category',
            Project::class => 'device',
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

    /**
     * Returns the URL for the given entity and method. E.g. for a Part and method "edit", it will return the URL to edit this part.
     */
    #[AsTwigFunction("entity_url")]
    public function entityURL(AbstractDBElement $entity, string $method = 'info'): string
    {
        return $this->entityURLGenerator->getURL($entity, $method);
    }


    /**
     * Returns the URL for the given entity in timetravel mode.
     */
    #[AsTwigFunction("timetravel_url")]
    public function timeTravelURL(AbstractDBElement $element, \DateTimeInterface $dateTime): ?string
    {
        try {
            return $this->entityURLGenerator->timeTravelURL($element, $dateTime);
        } catch (EntityNotSupportedException) {
            return null;
        }
    }

    /**
     * Generates a tree data structure for the given element, which can be used to display a tree view of the element and its related entities.
     * The type parameter can be used to specify the type of tree view (e.g. "newEdit" for the tree view in the new/edit pages). The returned data is a JSON string.
     */
    #[AsTwigFunction("tree_data")]
    public function treeData(AbstractDBElement $element, string $type = 'newEdit'): string
    {
        $tree = $this->treeBuilder->getTreeView($element::class, null, $type, $element);

        return json_encode($tree, JSON_THROW_ON_ERROR);
    }

    /**
     * Gets the localized type label for the given entity. E.g. for a Part, it will return "Part" in English and "Bauteil" in German.
     * @deprecated Use the "type_label" function instead, which does the same but is more concise.
     */
    #[AsTwigFunction("entity_type_label", deprecationInfo: new DeprecatedCallableInfo("Part-DB", "2", "Use the 'type_label' function instead."))]
    public function entityTypeLabel(object|string $entity): string
    {
        return $this->nameGenerator->getLocalizedTypeLabel($entity);
    }

    /**
     * Gets the localized type label for the given entity. E.g. for a Part, it will return "Part" in English and "Bauteil" in German.
     */
    #[AsTwigFunction("type_label")]
    public function typeLabel(object|string $entity): string
    {
        return $this->nameGenerator->typeLabel($entity);
    }

    /**
     * Gets the localized plural type label for the given entity. E.g. for a Part, it will return "Parts" in English and "Bauteile" in German.
     * @param  object|string  $entity
     * @return string
     */
    #[AsTwigFunction("type_label_p")]
    public function typeLabelPlural(object|string $entity): string
    {
        return $this->nameGenerator->typeLabelPlural($entity);
    }
}
