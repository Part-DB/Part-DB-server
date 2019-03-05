<?php declare(strict_types=1);


/**
 *
 * part-db version 0.1
 * Copyright (C) 2005 Christoph Lechner
 * http://www.cl-projects.de/
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
 *
 */

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Class AttachmentType
 * @ORM\Entity()
 * @ORM\Table(name="devices")
 */
class Device extends PartsContainingDBElement
{

    /**
     * @ORM\OneToMany(targetEntity="Category", mappedBy="parent")
     */
    protected $children;

    /**
     * @ORM\ManyToOne(targetEntity="Category", inversedBy="children")
     * @ORM\JoinColumn(name="parent_id", referencedColumnName="id")
     */
    protected $parent;

    /**
     * @var int
     * @ORM\Column(type="integer")
     *
     */
    protected $order_quantity;

    /**
     * @var bool
     * @ORM\Column(type="boolean")
     */
    protected $order_only_missing_parts;

    /**
     * @ORM\OneToMany(targetEntity="DevicePart", mappedBy="device")
     */
    protected $parts;

    /********************************************************************************
     *
     *   Getters
     *
     *********************************************************************************/

    /**
     *  Get the order quantity of this device
     *
     * @return integer      the order quantity
     */
    public function getOrderQuantity() : int
    {
        return $this->order_quantity;
    }

    /**
     *  Get the "order_only_missing_parts" attribute
     *
     * @return boolean      the "order_only_missing_parts" attribute
     */
    public function getOrderOnlyMissingParts() : bool
    {
        return $this->order_only_missing_parts;
    }

    /********************************************************************************
     *
     *   Setters
     *
     *********************************************************************************/

    /**
     *  Set the order quantity
     *
     * @param integer $new_order_quantity       the new order quantity
     */
    public function setOrderQuantity(int $new_order_quantity) : self
    {
        if($new_order_quantity < 0)
        {
            throw new \InvalidArgumentException("The new order quantity must not be negative!");
        }
        $this->order_quantity = $new_order_quantity;
        return $this;
    }

    /**
     *  Set the "order_only_missing_parts" attribute
     *
     * @param boolean $new_order_only_missing_parts       the new "order_only_missing_parts" attribute
     *
     */
    public function setOrderOnlyMissingParts(bool $new_order_only_missing_parts) : self
    {
        $this->order_only_missing_parts = $new_order_only_missing_parts;
        return $this;
    }


    /**
     * Returns the ID as an string, defined by the element class.
     * This should have a form like P000014, for a part with ID 14.
     * @return string The ID as a string;
     */
    public function getIDString(): string
    {
        return 'D' . sprintf('%09d', $this->getID());
    }
}