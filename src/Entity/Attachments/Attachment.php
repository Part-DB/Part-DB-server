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

use ApiPlatform\Doctrine\Orm\Filter\DateFilter;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\ApiPlatform\DocumentedAPIProperty;
use App\ApiPlatform\Filter\EntityFilter;
use App\ApiPlatform\Filter\LikeFilter;
use App\Repository\AttachmentRepository;
use App\EntityListeners\AttachmentDeleteListener;
use Doctrine\DBAL\Types\Types;
use App\Entity\Base\AbstractNamedDBElement;
use App\Validator\Constraints\Selectable;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Annotation\SerializedName;
use Symfony\Component\Serializer\Attribute\DiscriminatorMap;
use Symfony\Component\Validator\Constraints as Assert;
use function in_array;
use InvalidArgumentException;
use LogicException;

/**
 * Class Attachment.
 * @see \App\Tests\Entity\Attachments\AttachmentTest
 * @template-covariant  T of AttachmentContainingDBElement
 */
#[ORM\Entity(repositoryClass: AttachmentRepository::class)]
#[ORM\InheritanceType('SINGLE_TABLE')]
#[ORM\DiscriminatorColumn(name: 'class_name', type: 'string')]
#[ORM\DiscriminatorMap(['PartDB\Part' => PartAttachment::class, 'Part' => PartAttachment::class,
    'PartDB\Device' => ProjectAttachment::class, 'Device' => ProjectAttachment::class, 'AttachmentType' => AttachmentTypeAttachment::class,
    'Category' => CategoryAttachment::class, 'Footprint' => FootprintAttachment::class, 'Manufacturer' => ManufacturerAttachment::class,
    'Currency' => CurrencyAttachment::class, 'Group' => GroupAttachment::class, 'MeasurementUnit' => MeasurementUnitAttachment::class,
    'Storelocation' => StorageLocationAttachment::class, 'Supplier' => SupplierAttachment::class,
    'User' => UserAttachment::class, 'LabelProfile' => LabelAttachment::class])]
#[ORM\EntityListeners([AttachmentDeleteListener::class])]
#[ORM\Table(name: '`attachments`')]
#[ORM\Index(name: 'attachments_idx_id_element_id_class_name', columns: ['id', 'element_id', 'class_name'])]
#[ORM\Index(name: 'attachments_idx_class_name_id', columns: ['class_name', 'id'])]
#[ORM\Index(name: 'attachment_name_idx', columns: ['name'])]
#[ORM\Index(name: 'attachment_element_idx', columns: ['class_name', 'element_id'])]
#[ApiResource(
    operations: [
        new Get(security: 'is_granted("read", object)'),
        new GetCollection(security: 'is_granted("@attachments.list_attachments")'),
        new Post(securityPostDenormalize: 'is_granted("create", object)'),
        new Patch(security: 'is_granted("edit", object)'),
        new Delete(security: 'is_granted("delete", object)'),
    ],
    normalizationContext: ['groups' => ['attachment:read', 'attachment:read:standalone',  'api:basic:read'], 'openapi_definition_name' => 'Read'],
    denormalizationContext: ['groups' => ['attachment:write', 'attachment:write:standalone', 'api:basic:write'], 'openapi_definition_name' => 'Write'],
)]
#[DocumentedAPIProperty(schemaName: 'Attachment-Read', property: 'media_url', type: 'string', nullable: true,
    description: 'The URL to the file, where the attachment file can be downloaded. This can be an internal or external URL.',
    example: '/media/part/2/bc547-6508afa5a79c8.pdf')]
#[DocumentedAPIProperty(schemaName: 'Attachment-Read', property: 'thumbnail_url', type: 'string', nullable: true,
    description: 'The URL to a thumbnail version of this file. This only exists for internal picture attachments.')]
#[ApiFilter(LikeFilter::class, properties: ["name"])]
#[ApiFilter(EntityFilter::class, properties: ["attachment_type"])]
#[ApiFilter(DateFilter::class, strategy: DateFilter::EXCLUDE_NULL)]
#[ApiFilter(OrderFilter::class, properties: ['name', 'id', 'addedDate', 'lastModified'])]
#[DiscriminatorMap(typeProperty: '_type', mapping: ['part' => PartAttachment::class])]
abstract class Attachment extends AbstractNamedDBElement
{
    /**
     * A list of file extensions, that browsers can show directly as image.
     * Based on: https://developer.mozilla.org/en-US/docs/Web/Media/Formats/Image_types
     * It will be used to determine if an attachment is a picture and therefore will be shown to user as preview.
     */
    final public const PICTURE_EXTS = ['apng', 'bmp', 'gif', 'ico', 'cur', 'jpg', 'jpeg', 'jfif', 'pjpeg', 'pjp', 'png',
        'svg', 'webp', ];

    /**
     * A list of extensions that will be treated as a 3D Model that can be shown to user directly in Part-DB.
     */
    final public const MODEL_EXTS = ['x3d'];

    /**
     * When the path begins with one of the placeholders.
     */
    final public const INTERNAL_PLACEHOLDER = ['%BASE%', '%MEDIA%', '%SECURE%'];

    /**
     * @var array placeholders for attachments which using built in files
     */
    final public const BUILTIN_PLACEHOLDER = ['%FOOTPRINTS%', '%FOOTPRINTS3D%'];

    /**
     * @var string The class of the element that can be passed to this attachment. Must be overridden in subclasses.
     * @phpstan-var class-string<T>
     */
    protected const ALLOWED_ELEMENT_CLASS = AttachmentContainingDBElement::class;

    /**
     * @var string|null the original filename the file had, when the user uploaded it
     */
    #[ORM\Column(type: Types::STRING, nullable: true)]
    protected ?string $original_filename = null;

    /**
     * @var string The path to the file relative to a placeholder path like %MEDIA%
     */
    #[ORM\Column(type: Types::STRING, name: 'path')]
    protected string $path = '';

    /**
     * @var string the name of this element
     */
    #[Assert\NotBlank(message: 'validator.attachment.name_not_blank')]
    #[Groups(['simple', 'extended', 'full', 'attachment:read', 'attachment:write'])]
    protected string $name = '';

    /**
     * ORM mapping is done in subclasses (like PartAttachment).
     * @phpstan-param T|null $element
     */
    #[Groups(['attachment:read:standalone', 'attachment:write:standalone'])]
    #[ApiProperty(writableLink: false)]
    protected ?AttachmentContainingDBElement $element = null;

    #[ORM\Column(type: Types::BOOLEAN)]
    #[Groups(['attachment:read', 'attachment_write'])]
    protected bool $show_in_table = false;

    #[Assert\NotNull(message: 'validator.attachment.must_not_be_null')]
    #[ORM\ManyToOne(targetEntity: AttachmentType::class, inversedBy: 'attachments_with_type')]
    #[ORM\JoinColumn(name: 'type_id', nullable: false)]
    #[Selectable()]
    #[Groups(['attachment:read', 'attachment:write'])]
    protected ?AttachmentType $attachment_type = null;

    #[Groups(['attachment:read'])]
    protected ?\DateTimeInterface $addedDate = null;
    #[Groups(['attachment:read'])]
    protected ?\DateTimeInterface $lastModified = null;


    public function __construct()
    {
        //parent::__construct();
        if (AttachmentContainingDBElement::class === static::ALLOWED_ELEMENT_CLASS) {
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
    #[Groups(['attachment:read'])]
    public function isPicture(): bool
    {
        if ($this->isExternal()) {
            //Check if we can extract a file extension from the URL
            $extension = pathinfo(parse_url($this->path, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION);

            //If no extension is found or it is known picture extension, we assume that this is a picture extension
            if ($extension === '' || in_array(strtolower($extension), static::PICTURE_EXTS, true)) {
                return true;
            }

            //Otherwise we assume that the file is not a picture
            return false;
        }

        $extension = pathinfo($this->getPath(), PATHINFO_EXTENSION);

        return in_array(strtolower($extension), static::PICTURE_EXTS, true);
    }

    /**
     * Check if this attachment is a 3D model and therefore can be directly shown to user.
     * If the attachment is external, false is returned (3D Models must be internal).
     */
    #[Groups(['attachment:read'])]
    #[SerializedName('3d_model')]
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
    #[Groups(['attachment:read'])]
    public function isExternal(): bool
    {
        //When path is empty, this attachment can not be external
        if ($this->path === '') {
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
    #[Groups(['attachment:read'])]
    #[SerializedName('private')]
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
     * @return bool true if the attachment is using a builtin file
     */
    #[Groups(['attachment:read'])]
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

        if ($this->original_filename !== null && $this->original_filename !== '') {
            return strtolower(pathinfo($this->original_filename, PATHINFO_EXTENSION));
        }

        return strtolower(pathinfo($this->getPath(), PATHINFO_EXTENSION));
    }

    /**
     * Get the element, associated with this Attachment (for example a "Part" object).
     *
     * @return AttachmentContainingDBElement|null the associated Element
     * @phpstan-return T|null
     */
    public function getElement(): ?AttachmentContainingDBElement
    {
        return $this->element;
    }

    /**
     * The URL to the external file, or the path to the built-in file.
     * Returns null, if the file is not external (and not builtin).
     */
    #[Groups(['attachment:read'])]
    #[SerializedName('url')]
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
        if ($this->original_filename !== null && $this->original_filename !== '') {
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
    #[Groups(['attachment:write'])]
    #[SerializedName('url')]
    public function setURL(?string $url): self
    {
        $url = trim($url);
        //Escape spaces in URL
        $url = str_replace(' ', '%20', $url);

        //Only set if the URL is not empty
        if ($url !== null && $url !== '') {
            if (str_contains($url, '%BASE%') || str_contains($url, '%MEDIA%')) {
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

        return in_array($tmp[0], static::BUILTIN_PLACEHOLDER, true);
    }

    /**
     * Check if a string is a URL and is valid.
     *
     * @param string $string        The string which should be checked
     * @param bool   $path_required If true, the string must contain a path to be valid. (e.g. foo.bar would be invalid, foo.bar/test.php would be valid).
     * @param bool   $only_http     Set this to true, if only HTTPS or HTTP schemata should be allowed.
     *                              *Caution: When this is set to false, an attacker could use the file:// schema, to get internal server files, like /etc/passwd.*
     *
     * @return bool True if the string is a valid URL. False, if the string is not a URL or invalid.
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

    /**
     * Returns the class of the element that is allowed to be associated with this attachment.
     * @return string
     */
    public function getElementClass(): string
    {
        return static::ALLOWED_ELEMENT_CLASS;
    }
}
