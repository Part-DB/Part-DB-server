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

namespace App\Services;

use App\Entity\Attachments\Attachment;
use App\Entity\Attachments\AttachmentType;
use App\Entity\Base\DBElement;
use App\Entity\Base\NamedDBElement;
use App\Entity\Devices\Device;
use App\Entity\Parts\Category;
use App\Entity\Parts\Footprint;
use App\Entity\Parts\Manufacturer;
use App\Entity\Parts\MeasurementUnit;
use App\Entity\Parts\Part;
use App\Entity\Parts\PartLot;
use App\Entity\Parts\Storelocation;
use App\Entity\PriceInformations\Currency;
use App\Entity\PriceInformations\Orderdetail;
use App\Entity\PriceInformations\Pricedetail;
use App\Entity\UserSystem\Group;
use App\Entity\UserSystem\User;
use App\Exceptions\EntityNotSupportedException;
use function get_class;
use Proxies\__CG__\App\Entity\Parts\Supplier;
use Symfony\Contracts\Translation\TranslatorInterface;

class ElementTypeNameGenerator
{
    protected $translator;
    protected $mapping;

    public function __construct(TranslatorInterface $translator)
    {
        $this->translator = $translator;

        //Child classes has to become before parent classes
        $this->mapping = [
            Attachment::class => $this->translator->trans('attachment.label'),
            Category::class => $this->translator->trans('category.label'),
            AttachmentType::class => $this->translator->trans('attachment_type.label'),
            Device::class => $this->translator->trans('device.label'),
            Footprint::class => $this->translator->trans('footprint.label'),
            Manufacturer::class => $this->translator->trans('manufacturer.label'),
            MeasurementUnit::class => $this->translator->trans('measurement_unit.label'),
            Part::class => $this->translator->trans('part.label'),
            PartLot::class => $this->translator->trans('part_lot.label'),
            Storelocation::class => $this->translator->trans('storelocation.label'),
            Supplier::class => $this->translator->trans('supplier.label'),
            Currency::class => $this->translator->trans('currency.label'),
            Orderdetail::class => $this->translator->trans('orderdetail.label'),
            Pricedetail::class => $this->translator->trans('pricedetail.label'),
            Group::class => $this->translator->trans('group.label'),
            User::class => $this->translator->trans('user.label'),
        ];
    }

    /**
     * Gets an localized label for the type of the entity.
     * A part element becomes "Part" ("Bauteil" in german) and a category object becomes "Category".
     * Useful when the type should be shown to user.
     * Throws an exception if the class is not supported.
     *
     * @param DBElement|string $entity The element or class for which the label should be generated
     *
     * @return string the localized label for the entity type
     *
     * @throws EntityNotSupportedException when the passed entity is not supported
     */
    public function getLocalizedTypeLabel($entity): string
    {
        $class = is_string($entity) ? $entity : get_class($entity);

        //Check if we have an direct array entry for our entity class, then we can use it
        if (isset($this->mapping[$class])) {
            return $this->mapping[$class];
        }

        //Otherwise iterate over array and check for inheritance (needed when the proxy element from doctrine are passed)
        foreach ($this->mapping as $class => $translation) {
            if (is_a($entity, $class, true)) {
                return $translation;
            }
        }

        //When nothing was found throw an exception
        throw new EntityNotSupportedException(sprintf('No localized label for the element with type %s was found!', get_class($entity)));
    }

    /**
     * Returns a string like in the format ElementType: ElementName.
     * For example this could be something like: "Part: BC547".
     * It uses getLocalizedLabel to determine the type.
     *
     * @param NamedDBElement $entity   the entity for which the string should be generated
     * @param bool           $use_html If set to true, a html string is returned, where the type is set italic
     *
     * @return string The localized string
     *
     * @throws EntityNotSupportedException when the passed entity is not supported
     */
    public function getTypeNameCombination(NamedDBElement $entity, bool $use_html = false): string
    {
        $type = $this->getLocalizedTypeLabel($entity);
        if ($use_html) {
            return '<i>'.$type.':</i> '.htmlspecialchars($entity->getName());
        }

        return $type.': '.$entity->getName();
    }
}
