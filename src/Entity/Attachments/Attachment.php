<?php
/**
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 * Copyright (C) 2019 - 2022 Jan Böhmer (https://github.com/jbtronics)
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

declare(strict_types=1);

namespace App\Entity\Attachments;

use App\Entity\Base\AbstractNamedDBElement;
use App\Validator\Constraints\Selectable;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use function in_array;
use InvalidArgumentException;
use LogicException;

/**
 * Class Attachment.
 *
 * @ORM\Entity(repositoryClass="App\Repository\AttachmentRepository")
 * @ORM\Table(name="`attachments`", indexes={
 *    @ORM\Index(name="attachments_idx_id_element_id_class_name", columns={"id", "element_id", "class_name"}),
 *    @ORM\Index(name="attachments_idx_class_name_id", columns={"class_name", "id"}),
 *    @ORM\Index(name="attachment_name_idx", columns={"name"}),
 *    @ORM\Index(name="attachment_element_idx", columns={"class_name", "element_id"})
 * })
 * @ORM\InheritanceType("SINGLE_TABLE")
 * @ORM\DiscriminatorColumn(name="class_name", type="string")
 * @ORM\DiscriminatorMap({
 *     "PartDB\Part" = "PartAttachment", "Part" = "PartAttachment",
 *     "PartDB\Device" = "ProjectAttachment", "Device" = "ProjectAttachment",
 *     "AttachmentType" = "AttachmentTypeAttachment", "Category" = "CategoryAttachment",
 *     "Footprint" = "FootprintAttachment", "Manufacturer" = "ManufacturerAttachment",
 *     "Currency" = "CurrencyAttachment", "Group" = "GroupAttachment",
 *     "MeasurementUnit" = "MeasurementUnitAttachment", "Storelocation" = "StorelocationAttachment",
 *     "Supplier" = "SupplierAttachment", "User" = "UserAttachment", "LabelProfile" = "LabelAttachment",
 * })
 * @ORM\EntityListeners({"App\EntityListeners\AttachmentDeleteListener"})
 */
abstract class Attachment extends AbstractNamedDBElement
{
    /**
     * A list of file extensions, that browsers can show directly as image.
     * Based on: https://developer.mozilla.org/en-US/docs/Web/Media/Formats/Image_types
     * It will be used to determine if a attachment is a picture and therefore will be shown to user as preview.
     */
    public const PICTURE_EXTS = ['apng', 'bmp', 'gif', 'ico', 'cur', 'jpg', 'jpeg', 'jfif', 'pjpeg', 'pjp', 'png',
        'svg', 'webp', ];

    /**
     * A list of extensions that will be treated as a 3D Model that can be shown to user directly in Part-DB.
     */
    public const MODEL_EXTS = ['x3d'];

    /**
     * When the path begins with one of this placeholders.
     */
    public const INTERNAL_PLACEHOLDER = ['%BASE%', '%MEDIA%', '%SECURE%'];

    /**
     * @var array placeholders for attachments which using built in files
     */
    public const BUILTIN_PLACEHOLDER = ['%FOOTPRINTS%', '%FOOTPRINTS3D%'];

    /**
     * @var string The class of the element that can be passed to this attachment. Must be overridden in subclasses.
     */
    public const ALLOWED_ELEMENT_CLASS = '';

    /**
     * @var string|null the original filename the file had, when the user uploaded it
     * @ORM\Column(type="string", nullable=true)
     */
    protected ?string $original_filename = null;

    /**
     * @var string The path to the file relative to a placeholder path like %MEDIA%
     * @ORM\Column(type="string", name="path")
     */
    protected string $path = '';

    /**
     * @var string the name of this element
     * @ORM\Column(type="string")
     * @Assert\NotBlank(message="validator.attachment.name_not_blank")
     * @Groups({"simple", "extended", "full"})
     */
    protected string $name = '';

    /**
     * ORM mapping is done in sub classes (like PartAttachment).
     */
    protected ?AttachmentContainingDBElement $element = null;

    /**
     * @var bool
     * @ORM\Column(type="boolean")
     */
    protected bool $show_in_table = false;

    /**
     * @var AttachmentType
     * @ORM\ManyToOne(targetEntity="AttachmentType", inversedBy="attachments_with_type")
     * @ORM\JoinColumn(name="type_id", referencedColumnName="id", nullable=false)
     * @Selectable()
     * @Assert\NotNull(message="validator.attachment.must_not_be_null")
     */
    protected ?AttachmentType $attachment_type = null;

    public function __construct()
    {
        //parent::__construct();
        if ('' === static::ALLOWED_ELEMENT_CLASS) {
            throw new LogicException('An *Attachment class must override the ALLOWED_ELEMENT_CLASS const!');
        }
    }

    public function updateTimestamps(): void
    {
        parent::updateTimestamps();
        if ($this->element instanceof AttachmentContainingDBElement) {
            $this->element->updateTimestamps();
        }
    }

    /***********************************************************
     * Various function
     ***********************************************************/

    /**
     * Check if this attachment is a picture (analyse the file's extension).
     * If the link is external, it is assumed that this is true.
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
     * Check if this attachment is a 3D model and therefore can be directly shown to user.
     * If the attachment is external, false is returned (3D Models must be internal).
     */
    public function is3DModel(): bool
    {
        //We just assume that 3D Models are internally saved, otherwise we get problems loading them.
        if ($this->isExternal()) {
            return false;
        }

        $extension = pathinfo($this->getPath(), PATHINFO_EXTENSION);

        return in_array(strtolower($extension), static::MODEL_EXTS, true);
    }

    /**
     * Checks if the attachment file is externally saved (the database saves an URL).
     *
     * @return bool true, if the file is saved externally
     */
    public function isExternal(): bool
    {
        //When path is empty, this attachment can not be external
        if (empty($this->path)) {
            return false;
        }

        //After the %PLACEHOLDER% comes a slash, so we can check if we have a placeholder via explode
        $tmp = explode('/', $this->path);

        return !in_array($tmp[0], array_merge(static::INTERNAL_PLACEHOLDER, static::BUILTIN_PLACEHOLDER), true);
    }

    /**
     * Check if this attachment is saved in a secure place.
     * This means that it can not be accessed directly via a web request, but must be viewed via a controller.
     *
     * @return bool true, if the file is secure
     */
    public function isSecure(): bool
    {
        //After the %PLACEHOLDER% comes a slash, so we can check if we have a placeholder via explode
        $tmp = explode('/', $this->path);

        return '%SECURE%' === $tmp[0];
    }

    /**
     * Checks if the attachment file is using a builtin file. (see BUILTIN_PLACEHOLDERS const for possible placeholders)
     * If a file is built in, the path is shown to user in url field (no sensitive infos are provided).
     *
     * @return bool true if the attachment is using an builtin file
     */
    public function isBuiltIn(): bool
    {
        return static::checkIfBuiltin($this->path);
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
     *
     * @return string|null the file extension in lower case
     */
    public function getExtension(): ?string
    {
        if ($this->isExternal()) {
            return null;
        }

        if (!empty($this->original_filename)) {
            return strtolower(pathinfo($this->original_filename, PATHINFO_EXTENSION));
        }

        return strtolower(pathinfo($this->getPath(), PATHINFO_EXTENSION));
    }

    /**
     * Get the element, associated with this Attachment (for example a "Part" object).
     *
     * @return AttachmentContainingDBElement the associated Element
     */
    public function getElement(): ?AttachmentContainingDBElement
    {
        return $this->element;
    }

    /**
     * The URL to the external file, or the path to the built in file.
     * Returns null, if the file is not external (and not builtin).
     */
    public function getURL(): ?string
    {
        if (!$this->isExternal() && !$this->isBuiltIn()) {
            return null;
        }

        return $this->path;
    }

    /**
     * Returns the hostname where the external file is stored.
     * Returns null, if the file is not external.
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
     */
    public function getFilename(): ?string
    {
        if ($this->isExternal()) {
            return null;
        }

        //If we have a stored original filename, then use it
        if (!empty($this->original_filename)) {
            return $this->original_filename;
        }

        return pathinfo($this->getPath(), PATHINFO_BASENAME);
    }

    /**
     * Sets the filename that is shown for this attachment. Useful when the internal path is some generated value.
     *
     * @param string|null $new_filename The filename that should be shown.
     *                                  Set to null to generate the filename from path.
     *
     * @return Attachment
     */
    public function setFilename(?string $new_filename): self
    {
        if ('' === $new_filename) {
            $new_filename = null;
        }
        $this->original_filename = $new_filename;

        return $this;
    }

    /**
     * Get the show_in_table attribute.
     *
     * @return bool true means, this attachment will be listed in the "Attachments" column of the HTML tables
     *              false means, this attachment won't be listed in the "Attachments" column of the HTML tables
     */
    public function getShowInTable(): bool
    {
        return $this->show_in_table;
    }

    /**
     *  Get the type of this attachement.
     *
     * @return AttachmentType the type of this attachement
     */
    public function getAttachmentType(): ?AttachmentType
    {
        return $this->attachment_type;
    }

    /*****************************************************************************************************
     * Setters
     ***************************************************************************************************
     * @param  bool  $show_in_table
     * @return Attachment
     */

    public function setShowInTable(bool $show_in_table): self
    {
        $this->show_in_table = $show_in_table;

        return $this;
    }

    /**
     * Sets the element that is associated with this attachment.
     *
     * @return $this
     */
    public function setElement(AttachmentContainingDBElement $element): self
    {
        if (!is_a($element, static::ALLOWED_ELEMENT_CLASS)) {
            throw new InvalidArgumentException(sprintf('The element associated with a %s must be a %s!', static::class, static::ALLOWED_ELEMENT_CLASS));
        }

        $this->element = $element;

        return $this;
    }

    /**
     * Sets the filepath (with relative placeholder) for this attachment.
     *
     * @param string $path the new filepath of the attachment
     *
     * @return Attachment
     */
    public function setPath(string $path): self
    {
        $this->path = $path;

        return $this;
    }

    /**
     * @return $this
     */
    public function setAttachmentType(AttachmentType $attachement_type): self
    {
        $this->attachment_type = $attachement_type;

        return $this;
    }

    /**
     * Sets the url associated with this attachment.
     * If the url is empty nothing is changed, to not override the file path.
     *
     * @return Attachment
     */
    public function setURL(?string $url): self
    {
        //Only set if the URL is not empty
        if (!empty($url)) {
            if (false !== strpos($url, '%BASE%') || false !== strpos($url, '%MEDIA%')) {
                throw new InvalidArgumentException('You can not reference internal files via the url field! But nice try!');
            }

            $this->path = $url;
            //Reset internal filename
            $this->original_filename = null;
        }

        return $this;
    }

    /*****************************************************************************************************
     * Static functions
     *****************************************************************************************************/

    /**
     * Checks if the given path is a path to a builtin resource.
     *
     * @param string $path The path that should be checked
     *
     * @return bool true if the path is pointing to a builtin resource
     */
    public static function checkIfBuiltin(string $path): bool
    {
        //After the %PLACEHOLDER% comes a slash, so we can check if we have a placeholder via explode
        $tmp = explode('/', $path);
        //Builtins must have a %PLACEHOLDER% construction

        return in_array($tmp[0], static::BUILTIN_PLACEHOLDER, false);
    }

    /**
     * Check if a string is a URL and is valid.
     *
     * @param string $string        The string which should be checked
     * @param bool   $path_required If true, the string must contain a path to be valid. (e.g. foo.bar would be invalid, foo.bar/test.php would be valid).
     * @param bool   $only_http     Set this to true, if only HTTPS or HTTP schemata should be allowed.
     *                              *Caution: When this is set to false, a attacker could use the file:// schema, to get internal server files, like /etc/passwd.*
     *
     * @return bool True if the string is a valid URL. False, if the string is not an URL or invalid.
     */
    public static function isValidURL(string $string, bool $path_required = true, bool $only_http = true): bool
    {
        if ($only_http) {   //Check if scheme is HTTPS or HTTP
            $scheme = parse_url($string, PHP_URL_SCHEME);
            if ('http' !== $scheme && 'https' !== $scheme) {
                return false;   //All other schemes are not valid.
            }
        }
        if ($path_required) {
            return (bool) filter_var($string, FILTER_VALIDATE_URL, FILTER_FLAG_PATH_REQUIRED);
        }

        return (bool) filter_var($string, FILTER_VALIDATE_URL);
    }
}
