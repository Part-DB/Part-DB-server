<?php
/**
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 * Copyright (C) 2019 - 2020 Jan Böhmer (https://github.com/jbtronics)
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
use App\Entity\Attachments\PartAttachment;
use App\Entity\Base\AbstractDBElement;
use App\Entity\Devices\Device;
use App\Entity\Parts\Category;
use App\Entity\Parts\Footprint;
use App\Entity\Parts\Manufacturer;
use App\Entity\Parts\MeasurementUnit;
use App\Entity\Parts\Part;
use App\Entity\Parts\PartLot;
use App\Entity\Parts\Storelocation;
use App\Entity\Parts\Supplier;
use App\Entity\PriceInformations\Currency;
use App\Entity\PriceInformations\Orderdetail;
use App\Entity\PriceInformations\Pricedetail;
use App\Entity\UserSystem\Group;
use App\Entity\UserSystem\User;
use App\Exceptions\EntityNotSupportedException;
use App\Services\Attachments\AttachmentURLGenerator;
use function array_key_exists;
use function get_class;
use InvalidArgumentException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * This service can be used to generate links to controllers for different aspects of an entity
 * (like info, edit, delete, etc.)
 * Useful for Twig, where you can generate a link to an entity using a filter.
 */
class EntityURLGenerator
{
    protected $attachmentURLGenerator;
    /**
     * @var UrlGeneratorInterface
     */
    protected $urlGenerator;

    public function __construct(UrlGeneratorInterface $urlGenerator, AttachmentURLGenerator $attachmentURLGenerator)
    {
        $this->urlGenerator = $urlGenerator;
        $this->attachmentURLGenerator = $attachmentURLGenerator;
    }

    /**
     * Generates an URL to the page using the given page type and element.
     * For the given types, the [type]URL() functions are called (e.g. infoURL()).
     * Not all entity class and $type combinations are supported.
     *
     * @param mixed  $entity The element for which the page should be generated
     * @param string $type   The page type. Currently supported: 'info', 'edit', 'create', 'clone', 'list'/'list_parts'
     *
     * @return string the link to the desired page
     *
     * @throws EntityNotSupportedException thrown if the entity is not supported for the given type
     * @throws InvalidArgumentException    thrown if the givent type is not existing
     */
    public function getURL($entity, string $type)
    {
        switch ($type) {
            case 'info':
                return $this->infoURL($entity);
            case 'edit':
                return $this->editURL($entity);
            case 'create':
                return $this->createURL($entity);
            case 'clone':
                return $this->cloneURL($entity);
            case 'list':
            case 'list_parts':
                return $this->listPartsURL($entity);
            case 'delete':
                return $this->deleteURL($entity);
            case 'file_download':
                return $this->downloadURL($entity);
            case 'file_view':
                return $this->viewURL($entity);
        }

        throw new InvalidArgumentException('Method is not supported!');
    }

    /**
     * Gets the URL to view the given element at a given timestamp.
     *
     * @return string
     */
    public function timeTravelURL(AbstractDBElement $entity, \DateTime $dateTime): string
    {
        $map = [
            Part::class => 'part_info',

            //As long we does not have own things for it use edit page
            AttachmentType::class => 'attachment_type_edit',
            Category::class => 'category_edit',
            Device::class => 'device_edit',
            Supplier::class => 'supplier_edit',
            Manufacturer::class => 'manufacturer_edit',
            Storelocation::class => 'store_location_edit',
            Footprint::class => 'footprint_edit',
            User::class => 'user_edit',
            Currency::class => 'currency_edit',
            MeasurementUnit::class => 'measurement_unit_edit',
            Group::class => 'group_edit',
        ];

        try {
            return $this->urlGenerator->generate(
                $this->mapToController($map, $entity),
                [
                    'id' => $entity->getID(),
                    'timestamp' => $dateTime->getTimestamp(),
                ]
            );
        } catch (EntityNotSupportedException $exception) {
            if ($entity instanceof PartLot) {
                return $this->urlGenerator->generate('part_info', [
                    'id' => $entity->getPart()->getID(),
                    'timestamp' => $dateTime->getTimestamp(),
                ]);
            }
            if ($entity instanceof PartAttachment) {
                return $this->urlGenerator->generate('part_info', [
                    'id' => $entity->getElement()->getID(),
                    'timestamp' => $dateTime->getTimestamp(),
                ]);
            }
            if ($entity instanceof Orderdetail) {
                return $this->urlGenerator->generate('part_info', [
                    'id' => $entity->getPart()->getID(),
                    'timestamp' => $dateTime->getTimestamp(),
                ]);
            }
            if ($entity instanceof Pricedetail) {
                return $this->urlGenerator->generate('part_info', [
                    'id' => $entity->getOrderdetail()->getPart()->getID(),
                    'timestamp' => $dateTime->getTimestamp(),
                ]);
            }
        }

        //Otherwise throw an error
        throw new EntityNotSupportedException('The given entity is not supported yet!');
    }

    public function viewURL(Attachment $entity): ?string
    {
        if ($entity instanceof Attachment) {
            if ($entity->isExternal()) { //For external attachments, return the link to external path
                return $entity->getURL();
            }
            //return $this->urlGenerator->generate('attachment_view', ['id' => $entity->getID()]);
            return $this->attachmentURLGenerator->getViewURL($entity);
        }

        //Otherwise throw an error
        throw new EntityNotSupportedException('The given entity is not supported yet!');
    }

    public function downloadURL($entity): ?string
    {
        if ($entity instanceof Attachment) {
            if ($entity->isExternal()) { //For external attachments, return the link to external path
                return $entity->getURL();
            }

            return $this->attachmentURLGenerator->getDownloadURL($entity);
        }

        //Otherwise throw an error
        throw new EntityNotSupportedException(sprintf('The given entity is not supported yet! Passed class type: %s', get_class($entity)));
    }

    /**
     * Generates an URL to a page, where info about this entity can be viewed.
     *
     * @param AbstractDBElement $entity The entity for which the info should be generated
     *
     * @return string The URL to the info page
     *
     * @throws EntityNotSupportedException If the method is not supported for the given Entity
     */
    public function infoURL(AbstractDBElement $entity): string
    {
        $map = [
            Part::class => 'part_info',

            //As long we does not have own things for it use edit page
            AttachmentType::class => 'attachment_type_edit',
            Category::class => 'category_edit',
            Device::class => 'device_edit',
            Supplier::class => 'supplier_edit',
            Manufacturer::class => 'manufacturer_edit',
            Storelocation::class => 'store_location_edit',
            Footprint::class => 'footprint_edit',
            User::class => 'user_edit',
            Currency::class => 'currency_edit',
            MeasurementUnit::class => 'measurement_unit_edit',
            Group::class => 'group_edit',
        ];

        return $this->urlGenerator->generate($this->mapToController($map, $entity), ['id' => $entity->getID()]);
    }

    /**
     * Generates an URL to a page, where this entity can be edited.
     *
     * @param mixed $entity The entity for which the edit link should be generated
     *
     * @return string the URL to the edit page
     *
     * @throws EntityNotSupportedException If the method is not supported for the given Entity
     */
    public function editURL($entity): string
    {
        $map = [
            Part::class => 'part_edit',
            AttachmentType::class => 'attachment_type_edit',
            Category::class => 'category_edit',
            Device::class => 'device_edit',
            Supplier::class => 'supplier_edit',
            Manufacturer::class => 'manufacturer_edit',
            Storelocation::class => 'store_location_edit',
            Footprint::class => 'footprint_edit',
            User::class => 'user_edit',
            Currency::class => 'currency_edit',
            MeasurementUnit::class => 'measurement_unit_edit',
            Group::class => 'group_edit',
        ];

        return $this->urlGenerator->generate($this->mapToController($map, $entity), ['id' => $entity->getID()]);
    }

    /**
     * Generates an URL to a page, where a entity of this type can be created.
     *
     * @param mixed $entity The entity for which the link should be generated
     *
     * @return string the URL to the page
     *
     * @throws EntityNotSupportedException If the method is not supported for the given Entity
     */
    public function createURL($entity): string
    {
        $map = [
            Part::class => 'part_new',
            AttachmentType::class => 'attachment_type_new',
            Category::class => 'category_new',
            Device::class => 'device_new',
            Supplier::class => 'supplier_new',
            Manufacturer::class => 'manufacturer_new',
            Storelocation::class => 'store_location_new',
            Footprint::class => 'footprint_new',
            User::class => 'user_new',
            Currency::class => 'currency_new',
            MeasurementUnit::class => 'measurement_unit_new',
            Group::class => 'group_new',
        ];

        return $this->urlGenerator->generate($this->mapToController($map, $entity));
    }

    /**
     * Generates an URL to a page, where a new entity can be created, that has the same informations as the
     * given entity (element cloning).
     *
     * @param AbstractDBElement $entity The entity for which the link should be generated
     *
     * @return string the URL to the page
     *
     * @throws EntityNotSupportedException If the method is not supported for the given Entity
     */
    public function cloneURL(AbstractDBElement $entity): string
    {
        $map = [
            Part::class => 'part_clone',
        ];

        return $this->urlGenerator->generate($this->mapToController($map, $entity), ['id' => $entity->getID()]);
    }

    /**
     * Generates an URL to a page, where all parts are listed, which are contained in the given element.
     *
     * @param AbstractDBElement $entity The entity for which the link should be generated
     *
     * @return string the URL to the page
     *
     * @throws EntityNotSupportedException If the method is not supported for the given Entity
     */
    public function listPartsURL(AbstractDBElement $entity): string
    {
        $map = [
            Category::class => 'part_list_category',
            Footprint::class => 'part_list_footprint',
            Manufacturer::class => 'part_list_manufacturer',
            Supplier::class => 'part_list_supplier',
            Storelocation::class => 'part_list_store_location',
        ];

        return $this->urlGenerator->generate($this->mapToController($map, $entity), ['id' => $entity->getID()]);
    }

    public function deleteURL(AbstractDBElement $entity): string
    {
        $map = [
            Part::class => 'part_delete',
            AttachmentType::class => 'attachment_type_delete',
            Category::class => 'category_delete',
            Device::class => 'device_delete',
            Supplier::class => 'supplier_delete',
            Manufacturer::class => 'manufacturer_delete',
            Storelocation::class => 'store_location_delete',
            Footprint::class => 'footprint_delete',
            User::class => 'user_delete',
            Currency::class => 'currency_delete',
            MeasurementUnit::class => 'measurement_unit_delete',
            Group::class => 'group_delete',
        ];

        return $this->urlGenerator->generate($this->mapToController($map, $entity), ['id' => $entity->getID()]);
    }

    /**
     * Finds the controller name for the class of the entity using the given map.
     * Throws an exception if the entity class is not known to the map.
     *
     * @param array $map    The map that should be used for determing the controller
     * @param mixed $entity The entity for which the controller name should be determined
     *
     * @return string The name of the controller fitting the entity class
     *
     * @throws EntityNotSupportedException
     */
    protected function mapToController(array $map, $entity): string
    {
        $class = get_class($entity);

        //Check if we have an direct mapping for the given class
        if (! array_key_exists($class, $map)) {
            //Check if we need to check inheritance by looping through our map
            foreach (array_keys($map) as $key) {
                if (is_a($entity, $key)) {
                    return $map[$key];
                }
            }

            throw new EntityNotSupportedException(sprintf('The given entity is not supported yet! Passed class type: %s', get_class($entity)));
        }

        return $map[$class];
    }
}
