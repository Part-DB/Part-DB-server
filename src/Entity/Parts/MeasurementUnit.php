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

namespace App\Entity\Parts;


use App\Entity\Base\PartsContainingDBElement;
use App\Entity\Base\StructuralDBElement;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * This unit represents the unit in which the amount of parts in stock are measured.
 * This could be something like N, gramms, meters, etc...
 *
 * @package App\Entity
 * @ORM\Entity(repositoryClass="App\Repository\StructuralDBElementRepository")
 * @ORM\Table(name="`measurement_units`")
 * @UniqueEntity("unit")
 */
class MeasurementUnit extends PartsContainingDBElement
{

    /**
     * @var string The unit symbol that should be used for the Unit. This could be something like "", g (for gramms)
     * or m (for meters).
     * @ORM\Column(type="string", name="unit", nullable=true)
     * @Assert\Length(max=10)
     */
    protected $unit;

    /**
     * @var bool Determines if the amount value associated with this unit should be treated as integer.
     * Set to false, to measure continuous sizes likes masses or lengthes.
     * @ORM\Column(type="boolean", name="is_integer")
     */
    protected $is_integer = false;

    /**
     * @var bool Determines if the unit can be used with SI Prefixes (kilo, giga, milli, etc.).
     * Useful for sizes like meters.
     * @ORM\Column(type="boolean", name="use_si_prefix")
     */
    protected $use_si_prefix = false;

    /**
     * @ORM\OneToMany(targetEntity="MeasurementUnit", mappedBy="parent", cascade={"persist"})
     */
    protected $children;

    /**
     * @ORM\ManyToOne(targetEntity="MeasurementUnit", inversedBy="children")
     * @ORM\JoinColumn(name="parent_id", referencedColumnName="id")
     */
    protected $parent;

    /**
     * Returns the ID as an string, defined by the element class.
     * This should have a form like P000014, for a part with ID 14.
     *
     * @return string The ID as a string;
     *
     */
    public function getIDString(): string
    {
        return 'MU' . $this->getID();
    }

    /**
     * @return string
     */
    public function getUnit(): ?string
    {
        return $this->unit;
    }

    /**
     * @param string $unit
     * @return MeasurementUnit
     */
    public function setUnit(?string $unit): MeasurementUnit
    {
        $this->unit = $unit;
        return $this;
    }

    /**
     * @return bool
     */
    public function isInteger(): bool
    {
        return $this->is_integer;
    }

    /**
     * @param bool $isInteger
     * @return MeasurementUnit
     */
    public function setIsInteger(bool $isInteger): MeasurementUnit
    {
        $this->isInteger = $isInteger;
        return $this;
    }

    /**
     * @return bool
     */
    public function isUseSIPrefix(): bool
    {
        return $this->use_si_prefix;
    }

    /**
     * @param bool $usesSIPrefixes
     * @return MeasurementUnit
     */
    public function setUseSIPrefix(bool $usesSIPrefixes): MeasurementUnit
    {
        $this->useSIPrefixes = $usesSIPrefixes;
        return $this;
    }
}