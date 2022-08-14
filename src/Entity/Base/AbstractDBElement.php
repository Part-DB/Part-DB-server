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

namespace App\Entity\Base;

use Doctrine\ORM\Mapping as ORM;
use JsonSerializable;
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
 * @ORM\MappedSuperclass(repositoryClass="App\Repository\DBElementRepository")
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
abstract class AbstractDBElement implements JsonSerializable
{
    /** @var int|null The Identification number for this part. This value is unique for the element in this table.
     * Null if the element is not saved to DB yet.
     * @ORM\Column(type="integer")
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @Groups({"full"})
     */
    protected $id;

    public function __clone()
    {
        if ($this->id) {
            //Set ID to null, so that an new entry is created
            $this->id = null;
        }
    }

    /**
     * Get the ID. The ID can be zero, or even negative (for virtual elements). If an element is virtual, can be
     * checked with isVirtualElement().
     *
     * Returns null, if the element is not saved to the DB yet.
     *
     * @return int|null the ID of this element
     */
    public function getID(): ?int
    {
        return $this->id;
    }

    public function jsonSerialize(): array
    {
        return ['@id' => $this->getID()];
    }
}
