<?php

declare(strict_types=1);
/**
 * Part-DB Version 0.4+ "nextgen"
 * Copyright (C) 2016 - 2019 Jan Böhmer
 * https://github.com/jbtronics.
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

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\DiscriminatorMap;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * This class is for managing all database objects.
 *
 * You should use this class for ALL classes which manages database records!
 *          (except special tables like "internal"...)
 * Every database table which are managed with this class (or a subclass of it)
 *          must have the table row "id"!! The ID is the unique key to identify the elements.
 *
 * @ORM\MappedSuperclass()
 *
 * @ORM\EntityListeners({"App\Security\EntityListeners\ElementPermissionListener"})
 *
 * @DiscriminatorMap(typeProperty="type", mapping={
 *      "attachment_type" = "App\Entity\AttachmentType",
 *      "attachment" = "App\Entity\Attachment",
 *      "category" = "App\Entity\Attachment",
 *      "device" = "App\Entity\Device",
 *      "device_part" = "App\Entity\DevicePart",
 *      "footprint" = "App\Entity\Footprint",
 *      "group" = "App\Entity\Group",
 *      "manufacturer" = "App\Entity\Manufacturer",
 *      "orderdetail" = "App\Entity\Orderdetail",
 *      "part" = "App\Entity\Part",
 *      "pricedetail" = "App\Entity\Pricedetail",
 *      "storelocation" = "App\Entity\Storelocation",
 *      "supplier" = "App\Entity\Supplier",
 *      "user" = "App\Entity\User"
 *  })
 */
abstract class DBElement
{
    /** @var int The Identification number for this part. This value is unique for the element in this table.
     * @ORM\Column(type="integer")
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @Groups({"full"})
     */
    protected $id;

    /**
     * Get the ID. The ID can be zero, or even negative (for virtual elements). If an elemenent is virtual, can be
     * checked with isVirtualElement().
     *
     * Returns null, if the element is not saved to the DB yet.
     *
     * @return int|null the ID of this element
     */
    final public function getID(): ?int
    {
        return (int) $this->id;
    }

    /**
     * Returns the ID as an string, defined by the element class.
     * This should have a form like P000014, for a part with ID 14.
     *
     * @return string The ID as a string;
     *
     */
    abstract public function getIDString(): string;

    public function __clone()
    {
        //Set ID to null, so that an new entry is created
        $this->id = null;
    }
}
