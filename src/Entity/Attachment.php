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

namespace App\Entity;;

use Doctrine\ORM\Mapping as ORM;

/**
 * Class Attachment
 * @package PartDB\Models
 * @ORM\Entity
 * @ORM\Table(name="attachements")
 */
class Attachment extends NamedDBElement
{
    /**
     * @var bool
     * @ORM\Column(type="boolean")
     */
    protected $show_in_table;



    /**
     * @var string The filename using the %BASE% variable
     * @ORM\Column(type="string")
     */
    protected $filename;

    /**
     * //TODO
     * //@ORM\ManyToOne(targetEntity="AttachmentContainingDBElement", inversedBy="attachment")
     * //@ORM\JoinColumn(name="element_id", referencedColumnName="id")
     */
    protected $element;

    /**
     * @var AttachmentType
     * @ORM\ManyToOne(targetEntity="AttachmentType", inversedBy="attachments")
     * @ORM\JoinColumn(name="type_id", referencedColumnName="id")
     */
    protected $attachement_type;

    /***********************************************************
     * Various function
     ***********************************************************/

    /**
     * Check if this attachement is a picture (analyse the file's extension)
     *
     * @return boolean      @li true if the file extension is a picture extension
     *                      @li otherwise false
     */
    public function isPicture() : bool
    {
        $extension = pathinfo($this->getFilename(), PATHINFO_EXTENSION);

        // list all file extensions which are supported to display them by HTML code
        $picture_extensions = array('gif', 'png', 'jpg', 'jpeg', 'bmp', 'svg', 'tif');

        return in_array(strtolower($extension), $picture_extensions, true);
    }

    /********************************************************************************
     *
     *   Getters
     *
     *********************************************************************************/

    /**
     * Get the element, associated with this Attachement (for example a "Part" object)
     * @return DBElement The associated Element.
     */
    public function getElement() : AttachmentContainingDBElement
    {
        return $this->element;
    }

    /**
     * Checks if the file in this attachement is existing. This works for files on the HDD, and for URLs
     * (it's not checked if the ressource behind the URL is really existing).
     *
     * @return bool True if the file is existing.
     */
    public function isFileExisting() : bool
    {
        return file_exists($this->getFilename()) || isURL($this->getFilename());
    }

    /**
     * Get the filename (absolute path from filesystem root, as a UNIX path [only slashes])
     *
     * @return string   the filename as an absolute UNIX filepath from filesystem root
     */
    public function getFilename() : string
    {
        return str_replace('%BASE%', BASE, $this->filename);
    }

    /**
     * Get the show_in_table attribute
     *
     * @return bool  true means, this attachement will be listed in the "Attachements" column of the HTML tables
     *               false means, this attachement won't be listed in the "Attachements" column of the HTML tables
     */
    public function getShowInTable() : bool
    {
        return (bool) $this->show_in_table;
    }

    /**
     *  Get the type of this attachement
     * @return AttachmentType     the type of this attachement
     * @throws Exception if there was an error
     */
    public function getType() : AttachmentType
    {
        //TODO
    }

    /**
     * Returns the ID as an string, defined by the element class.
     * This should have a form like P000014, for a part with ID 14.
     * @return string The ID as a string;
     */
    public function getIDString(): string
    {
        return 'A' . sprintf('%09d', $this->getID());
    }

    /*****************************************************************************************************
     * Setters
     ****************************************************************************************************/

    /**
     * @param bool $show_in_table
     * @return self
     */
    public function setShowInTable(bool $show_in_table): self
    {
        $this->show_in_table = $show_in_table;
        return $this;
    }
}