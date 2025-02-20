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

use ApiPlatform\Doctrine\Common\Filter\DateFilterInterface;
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
use App\ApiPlatform\HandleAttachmentsUploadsProcessor;
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
#[ORM\DiscriminatorMap(self::ORM_DISCRIMINATOR_MAP)]
#[ORM\EntityListeners([AttachmentDeleteListener::class])]
#[ORM\Table(name: '`attachments`')]
#[ORM\Index(columns: ['id', 'element_id', 'class_name'], name: 'attachments_idx_id_element_id_class_name')]
#[ORM\Index(columns: ['class_name', 'id'], name: 'attachments_idx_class_name_id')]
#[ORM\Index(columns: ['name'], name: 'attachment_name_idx')]
#[ORM\Index(columns: ['class_name', 'element_id'], name: 'attachment_element_idx')]
#[ApiResource(
    operations: [
        new Get(security: 'is_granted("read", object)'),
        new GetCollection(security: 'is_granted("@attachments.list_attachments")'),
        new Post(securityPostDenormalize: 'is_granted("create", object)', ),
        new Patch(security: 'is_granted("edit", object)'),
        new Delete(security: 'is_granted("delete", object)'),
    ],
    normalizationContext: ['groups' => ['attachment:read', 'attachment:read:standalone',  'api:basic:read'], 'openapi_definition_name' => 'Read'],
    denormalizationContext: ['groups' => ['attachment:write', 'attachment:write:standalone', 'api:basic:write'], 'openapi_definition_name' => 'Write'],
    processor: HandleAttachmentsUploadsProcessor::class,
)]
//This property is added by the denormalizer in order to resolve the placeholder
#[DocumentedAPIProperty(
    schemaName: 'Attachment-Read', property: 'internal_path', type: 'string', nullable: false,
    description: 'The URL to the internally saved copy of the file, if one exists',
    example: '/media/part/2/bc547-6508afa5a79c8.pdf'
)]
#[DocumentedAPIProperty(
    schemaName: 'Attachment-Read', property: 'thumbnail_url', type: 'string', nullable: true,
    description: 'The URL to a thumbnail version of this file. This only exists for internal picture attachments.'
)]
#[ApiFilter(LikeFilter::class, properties: ["name"])]
#[ApiFilter(EntityFilter::class, properties: ["attachment_type"])]
#[ApiFilter(DateFilter::class, strategy: DateFilterInterface::EXCLUDE_NULL)]
#[ApiFilter(OrderFilter::class, properties: ['name', 'id', 'addedDate', 'lastModified'])]
//This discriminator map is required for API platform to know which class to use for deserialization, when creating a new attachment.
#[DiscriminatorMap(typeProperty: '_type', mapping: self::API_DISCRIMINATOR_MAP)]
abstract class Attachment extends AbstractNamedDBElement
{
    private const ORM_DISCRIMINATOR_MAP = ['PartDB\Part' => PartAttachment::class, 'Part' => PartAttachment::class,
        'PartDB\Device' => ProjectAttachment::class, 'Device' => ProjectAttachment::class, 'AttachmentType' => AttachmentTypeAttachment::class,
        'Category' => CategoryAttachment::class, 'Footprint' => FootprintAttachment::class, 'Manufacturer' => ManufacturerAttachment::class,
        'Currency' => CurrencyAttachment::class, 'Group' => GroupAttachment::class, 'MeasurementUnit' => MeasurementUnitAttachment::class,
        'Storelocation' => StorageLocationAttachment::class, 'Supplier' => SupplierAttachment::class,
        'User' => UserAttachment::class, 'LabelProfile' => LabelAttachment::class];

    /*
     * The discriminator map used for API platform. The key should be the same as the api platform short type (the @type JSONLD field).
     */
    private const API_DISCRIMINATOR_MAP = ["Part" => PartAttachment::class, "Project" => ProjectAttachment::class, "AttachmentType" => AttachmentTypeAttachment::class,
        "Category" => CategoryAttachment::class, "Footprint" => FootprintAttachment::class, "Manufacturer" => ManufacturerAttachment::class,
        "Currency" => CurrencyAttachment::class, "Group" => GroupAttachment::class, "MeasurementUnit" => MeasurementUnitAttachment::class,
        "StorageLocation" => StorageLocationAttachment::class, "Supplier" => SupplierAttachment::class, "User" => UserAttachment::class, "LabelProfile" => LabelAttachment::class];

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
     * @var array placeholders for attachments which using built in files
     */
    final public const BUILTIN_PLACEHOLDER = ['%FOOTPRINTS%', '%FOOTPRINTS3D%'];

    /**
     * @var string The class of the element that can be passed to this attachment. Must be overridden in subclasses.
     * @phpstan-var class-string<T>
     */
    protected const ALLOWED_ELEMENT_CLASS = AttachmentContainingDBElement::class;

    /**
     * @var AttachmentUpload|null The options used for uploading a file to this attachment or modify it.
     * This value is not persisted in the database, but is just used to pass options to the upload manager.
     * If it is null, no upload process is started.
     */
    #[Groups(['attachment:write'])]
    protected ?AttachmentUpload $upload = null;

    /**
     * @var string|null the original filename the file had, when the user uploaded it
     */
    #[ORM\Column(type: Types::STRING, nullable: true)]
    #[Groups(['attachment:read', 'import'])]
    #[Assert\Length(max: 255)]
    protected ?string $original_filename = null;

    /**
     * @var string|null If a copy of the file is stored internally, the path to the file relative to a placeholder
     * path like %MEDIA%
     */
    #[ORM\Column(type: Types::STRING, nullable: true)]
    protected ?string $internal_path = null;


    /**
     * @var string|null The path to the external source if the file is stored externally or was downloaded from an
     * external source. Null if there is no external source.
     */
    #[ORM\Column(type: Types::STRING, nullable: true)]
    #[Groups(['attachment:read'])]
    #[ApiProperty(example: 'http://example.com/image.jpg')]
    protected ?string $external_path = null;

    /**
     * @var string the name of this element
     */
    #[Assert\NotBlank(message: 'validator.attachment.name_not_blank')]
    #[Groups(['simple', 'extended', 'full', 'attachment:read', 'attachment:write', 'import'])]
    protected string $name = '';

    /**
     * ORM mapping is done in subclasses (like PartAttachment).
     * @phpstan-param T|null $element
     */
    #[Groups(['attachment:read:standalone', 'attachment:write:standalone'])]
    #[ApiProperty(writableLink: false)]
    protected ?AttachmentContainingDBElement $element = null;

    #[ORM\Column(type: Types::BOOLEAN)]
    #[Groups(['attachment:read', 'attachment_write', 'full', 'import'])]
    protected bool $show_in_table = false;

    #[Assert\NotNull(message: 'validator.attachment.must_not_be_null')]
    #[ORM\ManyToOne(targetEntity: AttachmentType::class, inversedBy: 'attachments_with_type')]
    #[ORM\JoinColumn(name: 'type_id', nullable: false)]
    #[Selectable]
    #[Groups(['attachment:read', 'attachment:write', 'import', 'full'])]
    #[ApiProperty(readableLink: false)]
    protected ?AttachmentType $attachment_type = null;

    #[Groups(['attachment:read'])]
    protected ?\DateTimeImmutable $addedDate = null;
    #[Groups(['attachment:read'])]
    protected ?\DateTimeImmutable $lastModified = null;


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

    /**
     * Gets the upload currently associated with this attachment.
     * This is only temporary and not persisted directly in the database.
     * @internal This function should only be used by the Attachment Submit handler service
     * @return AttachmentUpload|null
     */
    public function getUpload(): ?AttachmentUpload
    {
        return $this->upload;
    }

    /**
     * Sets the current upload for this attachment.
     * It will be processed as the attachment is persisted/flushed.
     * @param  AttachmentUpload|null  $upload
     * @return $this
     */
    public function setUpload(?AttachmentUpload $upload): Attachment
    {
        $this->upload = $upload;
        return $this;
    }



    /***********************************************************
     * Various function
     ***********************************************************/

    /**
     * Check if this attachment is a picture (analyse the file's extension).
     * If the link is only external and doesn't contain an extension, it is assumed that this is true.
     *
     * @return bool * true if the file extension is a picture extension
     *              * otherwise false
     */
    #[Groups(['attachment:read'])]
    public function isPicture(): bool
    {
        if($this->hasInternal()){

            $extension = pathinfo($this->getInternalPath(), PATHINFO_EXTENSION);

            return in_array(strtolower($extension), static::PICTURE_EXTS, true);

        }
        if ($this->hasExternal()) {
            //Check if we can extract a file extension from the URL
            $extension = pathinfo(parse_url($this->getExternalPath(), PHP_URL_PATH) ?? '', PATHINFO_EXTENSION);

            //If no extension is found or it is known picture extension, we assume that this is a picture extension
            return $extension === '' || in_array(strtolower($extension), static::PICTURE_EXTS, true);
        }
        //File doesn't have an internal, nor an external copy. This shouldn't happen, but it certainly isn't a picture...
        return false;
    }

    /**
     * Check if this attachment is a 3D model and therefore can be directly shown to user.
     * If no internal copy exists, false is returned (3D Models must be internal).
     */
    #[Groups(['attachment:read'])]
    #[SerializedName('3d_model')]
    public function is3DModel(): bool
    {
        //We just assume that 3D Models are internally saved, otherwise we get problems loading them.
        if (!$this->hasInternal()) {
            return false;
        }

        $extension = pathinfo($this->getInternalPath(), PATHINFO_EXTENSION);

        return in_array(strtolower($extension), static::MODEL_EXTS, true);
    }

    /**
     * Checks if this attachment has a path to an external file
     *
     * @return bool true, if there is a path to an external file
     * @phpstan-assert-if-true non-empty-string $this->external_path
     * @phpstan-assert-if-true non-empty-string $this->getExternalPath())
     */
    #[Groups(['attachment:read'])]
    public function hasExternal(): bool
    {
        return $this->external_path !== null && $this->external_path !== '';
    }

    /**
     * Checks if this attachment has a path to an internal file.
     * Does not check if the file exists.
     *
     * @return bool true, if there is a path to an internal file
     * @phpstan-assert-if-true non-empty-string $this->internal_path
     * @phpstan-assert-if-true non-empty-string $this->getInternalPath())
     */
    #[Groups(['attachment:read'])]
    public function hasInternal(): bool
    {
        return $this->internal_path !== null && $this->internal_path !== '';
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
        if ($this->internal_path === null) {
            return false;
        }

        //After the %PLACEHOLDER% comes a slash, so we can check if we have a placeholder via explode
        $tmp = explode('/', $this->internal_path);

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
        if ($this->internal_path === null) {
            return false;
        }

        return static::checkIfBuiltin($this->internal_path);
    }

    /********************************************************************************
     *
     *   Getters
     *
     *********************************************************************************/

    /**
     * Returns the extension of the file referenced via the attachment.
     * For a path like %BASE/path/foo.bar, bar will be returned.
     * If this attachment is only external null is returned.
     *
     * @return string|null the file extension in lower case
     */
    public function getExtension(): ?string
    {
        if (!$this->hasInternal()) {
            return null;
        }

        if ($this->original_filename !== null && $this->original_filename !== '') {
            return strtolower(pathinfo($this->original_filename, PATHINFO_EXTENSION));
        }

        return strtolower(pathinfo($this->getInternalPath(), PATHINFO_EXTENSION));
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
     * The URL to the external file, or the path to the built-in file, but not paths to uploaded files.
     * Returns null, if the file is not external (and not builtin).
     * The output of this function is such, that no changes occur when it is fed back into setURL().
     * Required for the Attachment form field.
     */
    public function getURL(): ?string
    {
        if($this->hasExternal()){
            return $this->getExternalPath();
        }
        if($this->isBuiltIn()){
            return $this->getInternalPath();
        }
        return null;
    }

    /**
     * Returns the hostname where the external file is stored.
     * Returns null, if there is no external path.
     */
    public function getHost(): ?string
    {
        if (!$this->hasExternal()) {
            return null;
        }

        return parse_url($this->getExternalPath(), PHP_URL_HOST);
    }

    public function getInternalPath(): ?string
    {
        return $this->internal_path;
    }

    public function getExternalPath(): ?string
    {
        return $this->external_path;
    }

    /**
     * Returns the filename of the attachment.
     * For a path like %BASE/path/foo.bar, foo.bar will be returned.
     *
     * If there is no internal copy of the file, null will be returned.
     */
    public function getFilename(): ?string
    {
        if (!$this->hasInternal()) {
            return null;
        }

        //If we have a stored original filename, then use it
        if ($this->original_filename !== null && $this->original_filename !== '') {
            return $this->original_filename;
        }

        return pathinfo($this->getInternalPath(), PATHINFO_BASENAME);
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
        //Do not allow Rector to replace this check with a instanceof. It will not work!!
        if (!is_a($element, static::ALLOWED_ELEMENT_CLASS, true)) {
            throw new InvalidArgumentException(sprintf('The element associated with a %s must be a %s!', static::class, static::ALLOWED_ELEMENT_CLASS));
        }

        $this->element = $element;

        return $this;
    }

    /**
     * Sets the path to a file hosted internally. If you set this path to a file that was not downloaded from the
     * external source in external_path, make sure to reset external_path.
     */
    public function setInternalPath(?string $internal_path): self
    {
        $this->internal_path = $internal_path;

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
     * Sets up the paths using a user provided string which might contain an external path or a builtin path. Allows
     * resetting the external path if an internal path exists. Resets any other paths if a (nonempty) new path is set.
     */
    #[Groups(['attachment:write'])]
    #[SerializedName('url')]
    #[ApiProperty(description: 'Set the path of the attachment here. 
    Provide either an external URL, a path to a builtin file (like %FOOTPRINTS%/Active/ICs/IC_DFS.png) or an empty 
    string if the attachment has an internal file associated and you\'d like to reset the external source.
    If you set a new (nonempty) file path any associated internal file will be removed!')]
    public function setURL(?string $url): self
    {
        //Don't allow the user to set an empty external path if the internal path is empty already
        if (($url === null || $url === "") && !$this->hasInternal()) {
            return $this;
        }

        //The URL field can also contain the special builtin internal paths, so we need to distinguish here
        if ($this::checkIfBuiltin($url)) {
            $this->setInternalPath($url);
            //make sure the external path isn't still pointing to something unrelated
            $this->setExternalPath(null);
        } else {
            $this->setExternalPath($url);
        }
    return $this;
    }


    /**
     * Sets the path to a file hosted on an external server. Setting the external path to a (nonempty) value different
     * from the the old one _clears_ the internal path, so that the external path reflects where any associated internal
     * file came from.
     */
    public function setExternalPath(?string $external_path): self
    {
        //If we only clear the external path, don't reset the internal path, since that could be confusing
        if($external_path === null || $external_path === '') {
            $this->external_path = null;
            return $this;
        }

        $external_path = trim($external_path);
        //Escape spaces in URL
        $external_path = str_replace(' ', '%20', $external_path);

        if($this->external_path === $external_path) {
            //Nothing changed, nothing to do
            return $this;
        }

        $this->external_path = $external_path;
        $this->internal_path = null;
        //Reset internal filename
        $this->original_filename = null;

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
