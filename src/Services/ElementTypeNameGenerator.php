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

use App\Entity\Attachments\Attachment;
use App\Entity\Attachments\AttachmentContainingDBElement;
use App\Entity\Base\AbstractDBElement;
use App\Entity\Contracts\NamedElementInterface;
use App\Entity\Parameters\AbstractParameter;
use App\Entity\Parts\Part;
use App\Entity\Parts\PartLot;
use App\Entity\PriceInformations\Orderdetail;
use App\Entity\PriceInformations\Pricedetail;
use App\Entity\ProjectSystem\Project;
use App\Entity\ProjectSystem\ProjectBOMEntry;
use App\Exceptions\EntityNotSupportedException;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @see \App\Tests\Services\ElementTypeNameGeneratorTest
 */
final readonly class ElementTypeNameGenerator
{

    public function __construct(private TranslatorInterface $translator, private EntityURLGenerator $entityURLGenerator)
    {
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
     * @deprecated Use label() instead
     */
    public function getLocalizedTypeLabel(object|string $entity): string
    {
        return $this->typeLabel($entity);
    }

    /**
     * Gets a localized label for the type of the entity. If user defined synonyms are defined,
     * these are used instead of the default labels.
     * @param  object|string  $entity
     * @param  string|null  $locale
     * @return string
     */
    public function typeLabel(object|string $entity, ?string $locale = null): string
    {
        $type = ElementTypes::fromValue($entity);

        return $this->translator->trans($type->getDefaultLabelKey(), locale: $locale);
    }

    /**
     * Similar to label(), but returns the plural version of the label.
     * @param  object|string  $entity
     * @param  string|null  $locale
     * @return string
     */
    public function typeLabelPlural(object|string $entity, ?string $locale = null): string
    {
        $type = ElementTypes::fromValue($entity);

        return $this->translator->trans($type->getDefaultPluralLabelKey(), locale: $locale);
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
        $type = $this->typeLabel($entity);
        if ($use_html) {
            return '<i>' . $type . ':</i> ' . htmlspecialchars($entity->getName());
        }

        return $type . ': ' . $entity->getName();
    }


    /**
     * Returns a HTML formatted label for the given entity in the format "Type: Name" (on elements with a name) and
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
