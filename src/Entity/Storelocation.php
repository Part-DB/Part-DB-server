<?php


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
 * Class Storelocation
 * @package App\Entity
 *
 * @ORM\Entity()
 * @ORM\Table("storelocations")
 */
class Storelocation extends PartsContainingDBElement
{
    /**
     * @ORM\OneToMany(targetEntity="Storelocation", mappedBy="parent")
     */
    protected $children;

    /**
     * @ORM\ManyToOne(targetEntity="Storelocation", inversedBy="children")
     * @ORM\JoinColumn(name="parent_id", referencedColumnName="id")
     */
    protected $parent;

    /**
     * @ORM\OneToMany(targetEntity="Part", mappedBy="storelocation")
     */
    protected $parts;

    /**
     * @var bool
     * @ORM\Column(type="boolean")
     */
    protected $is_full;

    /********************************************************************************
     *
     *   Getters
     *
     *********************************************************************************/

    /**
     * Get the "is full" attribute
     *
     *     "is_full == true" means that there is no more space in this storelocation.
     *     This attribute is only for information, it has no effect.
     *
     * @return boolean      @li true if the storelocation is full
     *                      @li false if the storelocation isn't full
     */
    public function getIsFull() : bool
    {
        return (bool) $this->is_full;
    }

    /********************************************************************************
     *
     *   Setters
     *
     *********************************************************************************/

    /**
     * Change the "is full" attribute of this storelocation
     *
     *     "is_full" = true means that there is no more space in this storelocation.
     *     This attribute is only for information, it has no effect.
     *
     * @param boolean $new_is_full      @li true means that the storelocation is full
     *                                  @li false means that the storelocation isn't full
     *
     * @throws Exception if there was an error
     */
    public function setIsFull(bool $new_is_full) : self
    {
        $this->is_full = $new_is_full;
        return $this;
    }

    /**
     * Returns the ID as an string, defined by the element class.
     * This should have a form like P000014, for a part with ID 14.
     * @return string The ID as a string;
     */
    public function getIDString(): string
    {
        return 'L' . sprintf('%06d', $this->getID());
    }
}