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

/**
 * Class AttachmentType.
 *
 * @ORM\Entity(repositoryClass="App\Repository\StructuralDBElementRepository")
 * @ORM\Table(name="`categories`")
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
     * @var string
     * @ORM\Column(type="text")
     */
    protected $partname_hint = "";

    /**
     * @var string
     * @ORM\Column(type="text")
     */
    protected $partname_regex = "";

    /**
     * @var bool
     * @ORM\Column(type="boolean")
     */
    protected $disable_footprints = false;

    /**
     * @var bool
     * @ORM\Column(type="boolean")
     */
    protected $disable_manufacturers = false;

    /**
     * @var bool
     * @ORM\Column(type="boolean")
     */
    protected $disable_autodatasheets = false;

    /**
     * @var bool
     * @ORM\Column(type="boolean")
     */
    protected $disable_properties = false;

    /**
     * @var string
     * @ORM\Column(type="text")
     */
    protected $default_description = "";

    /**
     * @var string
     * @ORM\Column(type="text")
     */
    protected $default_comment = "";

    /**
     * Returns the ID as an string, defined by the element class.
     * This should have a form like P000014, for a part with ID 14.
     *
     * @return string The ID as a string;
     */
    public function getIDString(): string
    {
        return 'C'.sprintf('%09d', $this->getID());
    }

    /**
     * @return string
     */
    public function getPartnameHint(): string
    {
        return $this->partname_hint;
    }

    /**
     * @param string $partname_hint
     * @return Category
     */
    public function setPartnameHint(string $partname_hint): Category
    {
        $this->partname_hint = $partname_hint;
        return $this;
    }

    /**
     * @return string
     */
    public function getPartnameRegex(): string
    {
        return $this->partname_regex;
    }

    /**
     * @param string $partname_regex
     * @return Category
     */
    public function setPartnameRegex(string $partname_regex): Category
    {
        $this->partname_regex = $partname_regex;
        return $this;
    }

    /**
     * @return bool
     */
    public function isDisableFootprints(): bool
    {
        return $this->disable_footprints;
    }

    /**
     * @param bool $disable_footprints
     * @return Category
     */
    public function setDisableFootprints(bool $disable_footprints): Category
    {
        $this->disable_footprints = $disable_footprints;
        return $this;
    }

    /**
     * @return bool
     */
    public function isDisableManufacturers(): bool
    {
        return $this->disable_manufacturers;
    }

    /**
     * @param bool $disable_manufacturers
     * @return Category
     */
    public function setDisableManufacturers(bool $disable_manufacturers): Category
    {
        $this->disable_manufacturers = $disable_manufacturers;
        return $this;
    }

    /**
     * @return bool
     */
    public function isDisableAutodatasheets(): bool
    {
        return $this->disable_autodatasheets;
    }

    /**
     * @param bool $disable_autodatasheets
     * @return Category
     */
    public function setDisableAutodatasheets(bool $disable_autodatasheets): Category
    {
        $this->disable_autodatasheets = $disable_autodatasheets;
        return $this;
    }

    /**
     * @return bool
     */
    public function isDisableProperties(): bool
    {
        return $this->disable_properties;
    }

    /**
     * @param bool $disable_properties
     * @return Category
     */
    public function setDisableProperties(bool $disable_properties): Category
    {
        $this->disable_properties = $disable_properties;
        return $this;
    }

    /**
     * @return string
     */
    public function getDefaultDescription(): string
    {
        return $this->default_description;
    }

    /**
     * @param string $default_description
     * @return Category
     */
    public function setDefaultDescription(string $default_description): Category
    {
        $this->default_description = $default_description;
        return $this;
    }

    /**
     * @return string
     */
    public function getDefaultComment(): string
    {
        return $this->default_comment;
    }

    /**
     * @param string $default_comment
     * @return Category
     */
    public function setDefaultComment(string $default_comment): Category
    {
        $this->default_comment = $default_comment;
        return $this;
    }


}
