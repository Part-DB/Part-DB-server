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
use Symfony\Component\Intl\Exception\NotImplementedException;

/**
 * Class Attachment.
 *
 * @ORM\Entity
 * @ORM\Table(name="`attachements`")
 * @ORM\InheritanceType("SINGLE_TABLE")
 * @ORM\DiscriminatorColumn(name="class_name", type="string")
 * @ORM\DiscriminatorMap({"PartDB\Part" = "PartAttachment", "Part" = "PartAttachment"})
 *
 */
abstract class Attachment extends NamedDBElement
{
    /**
     * @var bool
     * @ORM\Column(type="boolean")
     */
    protected $show_in_table;

    /**
     * @var string The filename using the %BASE% variable
     * @ORM\Column(type="string", name="filename")
     */
    protected $path;

    /**
     * ORM mapping is done in sub classes (like PartAttachment)
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
     * Check if this attachement is a picture (analyse the file's extension).
     *
     * @return bool * true if the file extension is a picture extension
     *              * otherwise false
     */
    public function isPicture(): bool
    {
        $extension = pathinfo($this->getPath(), PATHINFO_EXTENSION);

        // list all file extensions which are supported to display them by HTML code
        $picture_extensions = array('gif', 'png', 'jpg', 'jpeg', 'bmp', 'svg', 'tif');

        return in_array(strtolower($extension), $picture_extensions, true);
    }

    /**
     * Checks if the attachment file is externally saved (the database saves an URL)
     * @return bool true, if the file is saved externally
     */
    public function isExternal() : bool
    {
        return static::isUrl($this->getPath());
    }

    /********************************************************************************
     *
     *   Getters
     *
     *********************************************************************************/

    /**
     * Returns the extension of the file referenced via the attachment.
     * For a path like %BASE/path/foo.bar, bar will be returned.
     * @return string
     */
    public function getExtension() : string
    {
        return pathinfo($this->getPath(), PATHINFO_EXTENSION);
    }

    /**
     * Get the element, associated with this Attachement (for example a "Part" object).
     *
     * @return DBElement The associated Element.
     */
    public function getElement(): ?AttachmentContainingDBElement
    {
        return $this->element;
    }

    /**
     * Checks if the file in this attachement is existing. This works for files on the HDD, and for URLs
     * (it's not checked if the ressource behind the URL is really existing).
     *
     * @return bool True if the file is existing.
     */
    public function isFileExisting(): bool
    {
        return file_exists($this->getPath()) || static::isURL($this->getPath());
    }

    /**
     * Get the filepath, relative to %BASE%.
     *
     * @return string A string like %BASE/path/foo.bar
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * Returns the filename of the attachment.
     * For a path like %BASE/path/foo.bar, foo.bar will be returned.
     *
     * If the path is a URL (can be checked via isExternal()), null will be returned.
     *
     * @return string|null
     */
    public function getFilename(): ?string
    {
        if ($this->isExternal()) {
            return null;
        }

        return pathinfo($this->getPath(), PATHINFO_BASENAME);
    }

    /**
     * Get the show_in_table attribute.
     *
     * @return bool true means, this attachement will be listed in the "Attachements" column of the HTML tables
     *              false means, this attachement won't be listed in the "Attachements" column of the HTML tables
     */
    public function getShowInTable(): bool
    {
        return (bool) $this->show_in_table;
    }

    /**
     *  Get the type of this attachement.
     *
     * @return AttachmentType the type of this attachement
     *
     */
    public function getType(): AttachmentType
    {
        return $this->attachement_type;
    }

    /**
     * Returns the ID as an string, defined by the element class.
     * This should have a form like P000014, for a part with ID 14.
     *
     * @return string The ID as a string;
     */
    public function getIDString(): string
    {
        return 'A'.sprintf('%09d', $this->getID());
    }

    /*****************************************************************************************************
     * Setters
     ****************************************************************************************************/

    /**
     * @param bool $show_in_table
     *
     * @return self
     */
    public function setShowInTable(bool $show_in_table): self
    {
        $this->show_in_table = $show_in_table;

        return $this;
    }

    /*****************************************************************************************************
     * Static functions
     *****************************************************************************************************/

    /**
     * Check if a string is a URL and is valid.
     * @param $string string The string which should be checked.
     * @param bool $path_required If true, the string must contain a path to be valid. (e.g. foo.bar would be invalid, foo.bar/test.php would be valid).
     * @param $only_http bool Set this to true, if only HTTPS or HTTP schemata should be allowed.
     *  *Caution: When this is set to false, a attacker could use the file:// schema, to get internal server files, like /etc/passwd.*
     * @return bool True if the string is a valid URL. False, if the string is not an URL or invalid.
     */
    public static function isURL(string $string, bool $path_required = true, bool $only_http = true) : bool
    {
        if ($only_http) {   //Check if scheme is HTTPS or HTTP
            $scheme = parse_url($string, PHP_URL_SCHEME);
            if ($scheme !== 'http' && $scheme !== 'https') {
                return false;   //All other schemes are not valid.
            }
        }
        if ($path_required) {
            return (bool) filter_var($string, FILTER_VALIDATE_URL, FILTER_FLAG_PATH_REQUIRED);
        } else {
            return (bool) filter_var($string, FILTER_VALIDATE_URL);
        }
    }
}
