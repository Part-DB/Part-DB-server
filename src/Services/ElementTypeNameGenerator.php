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

namespace App\Services;

use App\Entity\Attachments\AttachmentContainingDBElement;
use App\Entity\Attachments\Attachment;
use App\Entity\Attachments\AttachmentType;
use App\Entity\Base\AbstractDBElement;
use App\Entity\Contracts\NamedElementInterface;
use App\Entity\Parts\PartAssociation;
use App\Entity\ProjectSystem\Project;
use App\Entity\LabelSystem\LabelProfile;
use App\Entity\Parameters\AbstractParameter;
use App\Entity\Parts\Category;
use App\Entity\Parts\Footprint;
use App\Entity\Parts\Manufacturer;
use App\Entity\Parts\MeasurementUnit;
use App\Entity\Parts\Part;
use App\Entity\Parts\PartLot;
use App\Entity\Parts\StorageLocation;
use App\Entity\Parts\Supplier;
use App\Entity\PriceInformations\Currency;
use App\Entity\PriceInformations\Orderdetail;
use App\Entity\PriceInformations\Pricedetail;
use App\Entity\ProjectSystem\ProjectBOMEntry;
use App\Entity\UserSystem\Group;
use App\Entity\UserSystem\User;
use App\Exceptions\EntityNotSupportedException;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @see \App\Tests\Services\ElementTypeNameGeneratorTest
 */
class ElementTypeNameGenerator
{
    protected array $mapping;

    public function __construct(protected TranslatorInterface $translator, private readonly EntityURLGenerator $entityURLGenerator)
    {
        //Child classes has to become before parent classes
        $this->mapping = [
            Attachment::class => $this->translator->trans('attachment.label'),
            Category::class => $this->translator->trans('category.label'),
            AttachmentType::class => $this->translator->trans('attachment_type.label'),
            Project::class => $this->translator->trans('project.label'),
            ProjectBOMEntry::class => $this->translator->trans('project_bom_entry.label'),
            Footprint::class => $this->translator->trans('footprint.label'),
            Manufacturer::class => $this->translator->trans('manufacturer.label'),
            MeasurementUnit::class => $this->translator->trans('measurement_unit.label'),
            Part::class => $this->translator->trans('part.label'),
            PartLot::class => $this->translator->trans('part_lot.label'),
            StorageLocation::class => $this->translator->trans('storelocation.label'),
            Supplier::class => $this->translator->trans('supplier.label'),
            Currency::class => $this->translator->trans('currency.label'),
            Orderdetail::class => $this->translator->trans('orderdetail.label'),
            Pricedetail::class => $this->translator->trans('pricedetail.label'),
            Group::class => $this->translator->trans('group.label'),
            User::class => $this->translator->trans('user.label'),
            AbstractParameter::class => $this->translator->trans('parameter.label'),
            LabelProfile::class => $this->translator->trans('label_profile.label'),
            PartAssociation::class => $this->translator->trans('part_association.label'),
        ];
    }

    /**
     * Gets a localized label for the type of the entity.
     * A part element becomes "Part" ("Bauteil" in german) and a category object becomes "Category".
     * Useful when the type should be shown to user.
     * Throws an exception if the class is not supported.
     *
     * @param object|string $entity The element or class for which the label should be generated
     *
     * @return string the localized label for the entity type
     *
     * @throws EntityNotSupportedException when the passed entity is not supported
     */
    public function getLocalizedTypeLabel(object|string $entity): string
    {
        $class = is_string($entity) ? $entity : $entity::class;

        //Check if we have a direct array entry for our entity class, then we can use it
        if (isset($this->mapping[$class])) {
            return $this->mapping[$class];
        }

        //Otherwise iterate over array and check for inheritance (needed when the proxy element from doctrine are passed)
        foreach ($this->mapping as $class_to_check => $translation) {
            if (is_a($entity, $class_to_check, true)) {
                return $translation;
            }
        }

        //When nothing was found throw an exception
        throw new EntityNotSupportedException(sprintf('No localized label for the element with type %s was found!', is_object($entity) ? $entity::class : (string) $entity));
    }

    /**
     * Returns a string like in the format ElementType: ElementName.
     * For example this could be something like: "Part: BC547".
     * It uses getLocalizedLabel to determine the type.
     *
     * @param NamedElementInterface $entity   the entity for which the string should be generated
     * @param bool                  $use_html If set to true, a html string is returned, where the type is set italic, and the name is escaped
     *
     * @return string The localized string
     *
     * @throws EntityNotSupportedException when the passed entity is not supported
     */
    public function getTypeNameCombination(NamedElementInterface $entity, bool $use_html = false): string
    {
        $type = $this->getLocalizedTypeLabel($entity);
        if ($use_html) {
            return '<i>'.$type.':</i> '.htmlspecialchars($entity->getName());
        }

        return $type.': '.$entity->getName();
    }


    /**
     * Returns a HTML formatted label for the given enitity in the format "Type: Name" (on elements with a name) and
     * "Type: ID" (on elements without a name). If possible the value is given as a link to the element.
     * @param  AbstractDBElement  $entity The entity for which the label should be generated
     * @param  bool  $include_associated If set to true, the associated entity (like the part belonging to a part lot) is included in the label to give further information
     */
    public function formatLabelHTMLForEntity(AbstractDBElement $entity, bool $include_associated = false): string
    {
        //The element is existing
        if ($entity instanceof NamedElementInterface && $entity->getName() !== '') {
            try {
                $tmp = sprintf(
                    '<a href="%s">%s</a>',
                    $this->entityURLGenerator->infoURL($entity),
                    $this->getTypeNameCombination($entity, true)
                );
            } catch (EntityNotSupportedException) {
                $tmp = $this->getTypeNameCombination($entity, true);
            }
        } else { //Target does not have a name
            $tmp = sprintf(
                '<i>%s</i>: %s',
                $this->getLocalizedTypeLabel($entity),
                $entity->getID()
            );
        }

        //Add a hint to the associated element if possible
        if ($include_associated) {
            if ($entity instanceof Attachment && $entity->getElement() instanceof AttachmentContainingDBElement) {
                $on = $entity->getElement();
            } elseif ($entity instanceof AbstractParameter && $entity->getElement() instanceof AbstractDBElement) {
                $on = $entity->getElement();
            } elseif ($entity instanceof PartLot && $entity->getPart() instanceof Part) {
                $on = $entity->getPart();
            } elseif ($entity instanceof Orderdetail && $entity->getPart() instanceof Part) {
                $on = $entity->getPart();
            } elseif ($entity instanceof Pricedetail && $entity->getOrderdetail() instanceof Orderdetail && $entity->getOrderdetail()->getPart() instanceof Part) {
                $on = $entity->getOrderdetail()->getPart();
            } elseif ($entity instanceof ProjectBOMEntry && $entity->getProject() instanceof Project) {
                $on = $entity->getProject();
            }

            if (isset($on) && $on instanceof NamedElementInterface) {
                try {
                    $tmp .= sprintf(
                        ' (<a href="%s">%s</a>)',
                        $this->entityURLGenerator->infoURL($on),
                        $this->getTypeNameCombination($on, true)
                    );
                } catch (EntityNotSupportedException) {
                }
            }
        }

        return $tmp;
    }

    /**
     * Create a HTML formatted label for a deleted element of which we only know the class and the ID.
     * Please note that it is not checked if the element really not exists anymore, so you have to do this yourself.
     */
    public function formatElementDeletedHTML(string $class, int $id): string
    {
        return sprintf(
            '<i>%s</i>: %s [%s]',
            $this->getLocalizedTypeLabel($class),
            $id,
            $this->translator->trans('log.target_deleted')
        );
    }
}
