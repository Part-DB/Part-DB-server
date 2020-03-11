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
 * part-db version 0.1
 * Copyright (C) 2005 Christoph Lechner
 * http://www.cl-projects.de/.
 *
 * part-db version 0.2+
 * Copyright (C) 2009 K. Jacobs and others (see authors.php)
 * http://code.google.com/p/part-db/
 *
 * Part-DB Version 0.4+
 * Copyright (C) 2016 - 2019 Jan Böhmer
 * https://github.com/jbtronics
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

namespace App\Entity\Devices;

use App\Entity\Attachments\DeviceAttachment;
use App\Entity\Base\AbstractPartsContainingDBElement;
use App\Entity\Parameters\DeviceParameter;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use InvalidArgumentException;

/**
 * Class AttachmentType.
 *
 * @ORM\Entity(repositoryClass="App\Repository\StructuralDBElementRepository")
 * @ORM\Table(name="`devices`")
 */
class Device extends AbstractPartsContainingDBElement
{
    /**
     * @ORM\OneToMany(targetEntity="Device", mappedBy="parent")
     */
    protected $children;

    /**
     * @ORM\ManyToOne(targetEntity="Device", inversedBy="children")
     * @ORM\JoinColumn(name="parent_id", referencedColumnName="id")
     */
    protected $parent;

    /**
     * @ORM\OneToMany(targetEntity="DevicePart", mappedBy="device")
     */
    protected $parts;

    /**
     * @var int
     * @ORM\Column(type="integer")
     */
    protected $order_quantity = 0;

    /**
     * @var bool
     * @ORM\Column(type="boolean")
     */
    protected $order_only_missing_parts = false;
    /**
     * @var Collection|DeviceAttachment[]
     * @ORM\OneToMany(targetEntity="App\Entity\Attachments\DeviceAttachment", mappedBy="element", cascade={"persist", "remove"}, orphanRemoval=true)
     */
    protected $attachments;

    /** @var DeviceParameter[]
     * @ORM\OneToMany(targetEntity="App\Entity\Parameters\DeviceParameter", mappedBy="element", cascade={"persist", "remove"}, orphanRemoval=true)
     */
    protected $parameters;

    /********************************************************************************
     *
     *   Getters
     *
     *********************************************************************************/

    /**
     *  Get the order quantity of this device.
     *
     * @return int the order quantity
     */
    public function getOrderQuantity(): int
    {
        return $this->order_quantity;
    }

    /**
     *  Get the "order_only_missing_parts" attribute.
     *
     * @return bool the "order_only_missing_parts" attribute
     */
    public function getOrderOnlyMissingParts(): bool
    {
        return $this->order_only_missing_parts;
    }

    /********************************************************************************
     *
     *   Setters
     *
     *********************************************************************************/

    /**
     *  Set the order quantity.
     *
     * @param int $new_order_quantity the new order quantity
     *
     * @return $this
     */
    public function setOrderQuantity(int $new_order_quantity): self
    {
        if ($new_order_quantity < 0) {
            throw new InvalidArgumentException('The new order quantity must not be negative!');
        }
        $this->order_quantity = $new_order_quantity;

        return $this;
    }

    /**
     *  Set the "order_only_missing_parts" attribute.
     *
     * @param bool $new_order_only_missing_parts the new "order_only_missing_parts" attribute
     *
     * @return Device
     */
    public function setOrderOnlyMissingParts(bool $new_order_only_missing_parts): self
    {
        $this->order_only_missing_parts = $new_order_only_missing_parts;

        return $this;
    }

    /**
     * Returns the ID as an string, defined by the element class.
     * This should have a form like P000014, for a part with ID 14.
     *
     * @return string The ID as a string;
     */
    public function getIDString(): string
    {
        return 'D'.sprintf('%09d', $this->getID());
    }
}
