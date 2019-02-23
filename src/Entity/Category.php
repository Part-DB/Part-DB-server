<?php
/**
 *
 * Part-DB Version 0.4+ "nextgen"
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
 * @ORM\Entity
 * @ORM\Table(name="categories")
 */
class Category extends PartsContainingDBElement
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
     * @ORM\OneToMany(targetEntity="Part", mappedBy="category")
     */
    protected $parts;

    /**
     * @var bool
     * @ORM\Column(type="boolean")
     */
    protected $partname_hint;

    /**
     * @var string
     * @ORM\Column(type="string")
     */
    protected $partname_regex;

    /**
     * @var bool
     * @ORM\Column(type="boolean")
     */
    protected $disable_footprints;

    /**
     * @var bool
     * @ORM\Column(type="boolean")
     */
    protected $disable_manufacturers;

    /**
     * @var bool
     * @ORM\Column(type="boolean")
     */
    protected $disable_autodatasheets;

    /**
     * @var bool
     * @ORM\Column(type="boolean")
     */
    protected $disable_properties;

    /**
     * @var string
     * @ORM\Column(type="string")
     */
    protected $default_description;

    /**
     * @var string
     * @ORM\Column(type="string")
     */
    protected $default_comment;


    /**
     * Returns the ID as an string, defined by the element class.
     * This should have a form like P000014, for a part with ID 14.
     * @return string The ID as a string;
     */
    public function getIDString(): string
    {
        return 'C' . sprintf('%09d', $this->getID());
    }
}
