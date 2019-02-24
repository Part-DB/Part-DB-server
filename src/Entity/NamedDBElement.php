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
 * All subclasses of this class have an attribute "name".
 *
 * @ORM\MappedSuperclass()
 */
abstract class NamedDBElement extends DBElement
{
    /**
     * @var string The name of this element.
     * @ORM\Column(type="string")
     */
    protected $name;

    /**
     * @var \DateTime The date when this element was modified the last time.
     * @ORM\Column(type="datetimetz", name="last_modified")
     */
    protected $lastModified;

    /**
     * @var \DateTime The date when this element was created.
     * @ORM\Column(type="datetimetz", name="datetime_added")
     */
    protected $addedDate;

    /********************************************************************************
     *
     *   Getters
     *
     *********************************************************************************/

    /**
     * Get the name
     *
     * @return string   the name of this element
     */
    public function getName() : string
    {
        //Strip HTML from Name, so no XSS injection is possible.
        return strip_tags($this->name);
    }

    /**
     * Returns the last time when the element was modified.
     * @param $formatted bool When true, the date gets formatted with the locale and timezone settings.
     *          When false, the raw value from the DB is returned.
     * @return string The time of the last edit.
     */
    public function getLastModified(bool $formatted = true) : string
    {
        //TODO
        return "TODO";
    }

    /**
     * Returns the date/time when the element was created.
     * @param $formatted bool When true, the date gets formatted with the locale and timezone settings.
     *       When false, the raw value from the DB is returned.
     * @return string The creation time of the part.
     */
    public function getDatetimeAdded(bool $formatted = true) : string
    {
        //TODO
        return "TODO";
    }

    /********************************************************************************
     *
     *   Setters
     *
     *********************************************************************************/

    /**
     * Change the name of this element
     *
     * @note    Spaces at the begin and at the end of the string will be removed
     *          automatically in NamedDBElement::check_values_validity().
     *          So you don't have to do this yourself.
     *
     * @param string $new_name      the new name
     */
    public function setName(string $new_name) : self
    {
        $this->name = $new_name;
        return $this;
    }

}