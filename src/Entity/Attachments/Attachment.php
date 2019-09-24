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

namespace App\Entity\Attachments;

use App\Entity\Base\NamedDBElement;
use App\Validator\Constraints\Selectable;
use Doctrine\ORM\Mapping as ORM;

/**
 * Class Attachment.
 *
 * @ORM\Entity
 * @ORM\Table(name="`attachments`")
 * @ORM\InheritanceType("SINGLE_TABLE")
 * @ORM\DiscriminatorColumn(name="class_name", type="string")
 * @ORM\DiscriminatorMap({
 *     "PartDB\Part" = "PartAttachment", "Part" = "PartAttachment",
 *     "PartDB\Device" = "DeviceAttachment", "Device" = "DeviceAttachment",
 *     "AttachmentType" = "AttachmentTypeAttachment", "Category" = "CategoryAttachment",
 *     "Footprint" = "FootprintAttachment", "Manufacturer" = "ManufacturerAttachment",
 *     "Currency" = "CurrencyAttachment", "Group" = "GroupAttachment",
 *     "MeasurementUnit" = "MeasurementUnitAttachment", "Storelocation" = "StorelocationAttachment",
 *     "Supplier" = "SupplierAttachment", "User" = "UserAttachment"
 * })
 * @ORM\EntityListeners({"App\EntityListeners\AttachmentDeleteListener"})
 *
 */
abstract class Attachment extends NamedDBElement
{

    /**
     * A list of file extensions, that browsers can show directly as image.
     * Based on: https://developer.mozilla.org/en-US/docs/Web/Media/Formats/Image_types
     * It will be used to determine if a attachment is a picture and therefore will be shown to user as preview.
     */
    const PICTURE_EXTS = ['apng', 'bmp', 'gif', 'ico', 'cur', 'jpg', 'jpeg', 'jfif', 'pjpeg', 'pjp', 'png',
                            'svg', 'webp'];

    /**
     * @var bool
     * @ORM\Column(type="boolean")
     */
    protected $show_in_table = false;

    /**
     * @var string The path to the file relative to a placeholder path like %MEDIA%
     * @ORM\Column(type="string", name="path")
     */
    protected $path = '';

    /**
     * @var string The original filenamethe file had, when the user uploaded it.
     * @ORM\Column(type="string", nullable=true)
     */
    protected $original_filename;

    /**
     * ORM mapping is done in sub classes (like PartAttachment)
     */
    protected $element;

    /**
     * @var AttachmentType
     * @ORM\ManyToOne(targetEntity="AttachmentType", inversedBy="attachments_with_type")
     * @ORM\JoinColumn(name="type_id", referencedColumnName="id")
     * @Selectable()
     */
    protected $attachment_type;

    /***********************************************************
     * Various function
     ***********************************************************/

    /**
     * Check if this attachement is a picture (analyse the file's extension).
     * If the link is external, it is assumed that this is false.
     *
     * @return bool * true if the file extension is a picture extension
     *              * otherwise false
     */
    public function isPicture(): bool
    {
        //We can not check if a external link is a picture, so just assume this is false
        if ($this->isExternal()) {
            return true;
        }

        $extension = pathinfo($this->getPath(), PATHINFO_EXTENSION);

        return in_array(strtolower($extension), static::PICTURE_EXTS, true);
    }

    /**
     * Checks if the attachment file is externally saved (the database saves an URL)
     * @return bool true, if the file is saved externally
     */
    public function isExternal() : bool
    {
        //Treat all pathes without a filepath as external
        return (strpos($this->getPath(), '%MEDIA%') !== 0)
            && (strpos($this->getPath(), '%BASE%') !== 0);
    }

    /********************************************************************************
     *
     *   Getters
     *
     *********************************************************************************/

    /**
     * Returns the extension of the file referenced via the attachment.
     * For a path like %BASE/path/foo.bar, bar will be returned.
     * If this attachment is external null is returned.
     * @return string|null The file extension in lower case.
     */
    public function getExtension() : ?string
    {
        if ($this->isExternal()) {
            return null;
        }
        return strtolower(pathinfo($this->getPath(), PATHINFO_EXTENSION));
    }

    /**
     * Get the element, associated with this Attachement (for example a "Part" object).
     *
     * @return AttachmentContainingDBElement The associated Element.
     */
    public function getElement(): ?AttachmentContainingDBElement
    {
        return $this->element;
    }

    /**
     * The URL to the external file.
     * Returns null, if the file is not external.
     * @return string|null
     */
    public function getURL(): ?string
    {
        if (!$this->isExternal()) {
            return null;
        }

        return $this->path;
    }

    /**
     * Returns the hostname where the external file is stored.
     * Returns null, if the file is not external.
     * @return string|null
     */
    public function getHost(): ?string
    {
        if (!$this->isExternal()) {
            return null;
        }

        return parse_url($this->getURL(), PHP_URL_HOST);
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
    public function getAttachmentType(): ?AttachmentType
    {
        return $this->attachment_type;
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

    abstract public function setElement(AttachmentContainingDBElement $element) : Attachment;

    /**
     * @param string $path
     * @return Attachment
     */
    public function setPath(string $path): Attachment
    {
        $this->path = $path;
        return $this;
    }

    /**
     * @param AttachmentType $attachement_type
     * @return Attachment
     */
    public function setAttachmentType(AttachmentType $attachement_type): Attachment
    {
        $this->attachment_type = $attachement_type;
        return $this;
    }

    /**
     * Sets the url associated with this attachment.
     * If the url is empty nothing is changed, to not override the file path.
     * @param string|null $url
     * @return Attachment
     */
    public function setURL(?string $url) : Attachment
    {
        //Only set if the URL is not empty
        if (!empty($url)) {
            if (strpos($url, '%BASE%') !== false || strpos($url, '%MEDIA%') !== false) {
                throw new \InvalidArgumentException('You can not reference internal files via the url field! But nice try!');
            }

            $this->path = $url;
        }

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
        }

        return (bool) filter_var($string, FILTER_VALIDATE_URL);
    }
}
