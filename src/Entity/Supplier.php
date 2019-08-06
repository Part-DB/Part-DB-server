<?php

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

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Class Supplier.
 *
 * @ORM\Entity(repositoryClass="App\Repository\StructuralDBElementRepository")
 * @ORM\Table("`suppliers`")
 */
class Supplier extends Company
{
    /**
     * @ORM\OneToMany(targetEntity="Supplier", mappedBy="parent")
     */
    protected $children;

    /**
     * @ORM\ManyToOne(targetEntity="Supplier", inversedBy="children")
     * @ORM\JoinColumn(name="parent_id", referencedColumnName="id")
     */
    protected $parent;

    /**
     * @ORM\OneToMany(targetEntity="Orderdetail", mappedBy="supplier")
     */
    protected $orderdetails;

    /**
     *  Get all parts from this element.
     *
     * @return int all parts in a one-dimensional array of Part objects
     *
     * @throws Exception if there was an error
     */
    public function getCountOfPartsToOrder(): int
    {
        /*
        $query =    'SELECT COUNT(*) as count FROM parts '.
            'LEFT JOIN device_parts ON device_parts.id_part = parts.id '.
            'LEFT JOIN devices ON devices.id = device_parts.id_device '.
            'LEFT JOIN orderdetails ON orderdetails.id = parts.order_orderdetails_id '.
            'WHERE ((parts.instock < parts.mininstock) OR (parts.manual_order != false) '.
            'OR ((devices.order_quantity > 0) '.
            'AND ((devices.order_only_missing_parts = false) '.
            'OR (parts.instock - device_parts.quantity * devices.order_quantity < parts.mininstock)))) '.
            'AND (parts.order_orderdetails_id IS NOT NULL) '.
            'AND (orderdetails.id_supplier = ?)';

        $query_data = $this->database->query($query, array($this->getID()));



        return (int) $query_data[0]['count']; */

        //TODO
        throw new \Exception('Not implemented yet!');
    }

    /**
     * Returns the ID as an string, defined by the element class.
     * This should have a form like P000014, for a part with ID 14.
     *
     * @return string The ID as a string;
     */
    public function getIDString(): string
    {
        return 'L'.sprintf('%06d', $this->getID());
    }
}
