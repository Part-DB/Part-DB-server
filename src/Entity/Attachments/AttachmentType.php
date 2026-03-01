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

use Doctrine\Common\Collections\Criteria;
use ApiPlatform\Doctrine\Common\Filter\DateFilterInterface;
use ApiPlatform\Doctrine\Orm\Filter\DateFilter;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\Serializer\Filter\PropertyFilter;
use App\ApiPlatform\Filter\LikeFilter;
use App\Repository\StructuralDBElementRepository;
use Doctrine\DBAL\Types\Types;
use App\Entity\Base\AbstractStructuralDBElement;
use App\Entity\Parameters\AttachmentTypeParameter;
use App\Validator\Constraints\ValidFileFilter;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Class AttachmentType.
 * @see \App\Tests\Entity\Attachments\AttachmentTypeTest
 */
#[ORM\Entity(repositoryClass: StructuralDBElementRepository::class)]
#[ORM\Table(name: '`attachment_types`')]
#[ORM\Index(columns: ['name'], name: 'attachment_types_idx_name')]
#[ORM\Index(columns: ['parent_id', 'name'], name: 'attachment_types_idx_parent_name')]
#[ORM\HasLifecycleCallbacks]
#[ORM\EntityListeners([TreeCacheInvalidationListener::class])]
#[UniqueEntity(fields: ['name', 'parent'], message: 'structural.entity.unique_name', ignoreNull: false)]
#[ApiResource(
    operations: [
        new Get(security: 'is_granted("read", object)'),
        new GetCollection(security: 'is_granted("@attachment_types.read")'),
        new Post(securityPostDenormalize: 'is_granted("create", object)'),
        new Patch(security: 'is_granted("edit", object)'),
        new Delete(security: 'is_granted("delete", object)'),
    ],
    normalizationContext: ['groups' => ['attachment_type:read', 'api:basic:read'], 'openapi_definition_name' => 'Read'],
    denormalizationContext: ['groups' => ['attachment_type:write', 'api:basic:write', 'attachment:write', 'parameter:write'], 'openapi_definition_name' => 'Write'],
)]
#[ApiResource(
    uriTemplate: '/attachment_types/{id}/children.{_format}',
    operations: [
        new GetCollection(openapi: new Operation(summary: 'Retrieves the children elements of an attachment type.'),
            security: 'is_granted("@attachment_types.read")')
    ],
    uriVariables: [
        'id' => new Link(fromProperty: 'children', fromClass: AttachmentType::class)
    ],
    normalizationContext: ['groups' => ['attachment_type:read', 'api:basic:read'], 'openapi_definition_name' => 'Read']
)]
#[ApiFilter(PropertyFilter::class)]
#[ApiFilter(LikeFilter::class, properties: ["name", "comment"])]
#[ApiFilter(DateFilter::class, strategy: DateFilterInterface::EXCLUDE_NULL)]
#[ApiFilter(OrderFilter::class, properties: ['name', 'id', 'addedDate', 'lastModified'])]
class AttachmentType implements DBElementInterface, NamedElementInterface, TimeStampableInterface, HasAttachmentsInterface, HasMasterAttachmentInterface, StructuralElementInterface, HasParametersInterface, \Stringable, \JsonSerializable
{
    use DBElementTrait;
    use NamedElementTrait;
    use TimestampTrait;
    use AttachmentsTrait;
    use MasterAttachmentTrait;
    use StructuralElementTrait;
    use ParametersTrait;

    #[ORM\OneToMany(mappedBy: 'parent', targetEntity: AttachmentType::class, cascade: ['persist'])]
    #[ORM\OrderBy(['name' => Criteria::ASC])]
    protected Collection $children;

    #[ORM\ManyToOne(targetEntity: AttachmentType::class, inversedBy: 'children')]
    #[ORM\JoinColumn(name: 'parent_id')]
    #[Groups(['attachment_type:read', 'attachment_type:write'])]
    #[ApiProperty(readableLink: true, writableLink: false)]
    protected ?self $parent = null;

    #[Groups(['attachment_type:read', 'attachment_type:write'])]
    protected string $comment = '';

    /**
     * @var string A comma separated list of file types, which are allowed for attachment files.
     * Must be in the format of <pre><input type=file></pre> accept attribute
     * (See https://developer.mozilla.org/en-US/docs/Web/HTML/Element/input/file#Unique_file_type_specifiers).
     */
    #[ORM\Column(type: Types::TEXT)]
    #[ValidFileFilter]
    #[Groups(['attachment_type:read', 'attachment_type:write', 'import', 'extended'])]
    protected string $filetype_filter = '';

    /**
     * @var Collection<int, AttachmentTypeAttachment>
     */
    #[Assert\Valid]
    #[ORM\OneToMany(mappedBy: 'element', targetEntity: AttachmentTypeAttachment::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['name' => Criteria::ASC])]
    #[Groups(['attachment_type:read', 'attachment_type:write', 'import', 'full'])]
    protected Collection $attachments;

    #[ORM\ManyToOne(targetEntity: AttachmentTypeAttachment::class)]
    #[ORM\JoinColumn(name: 'id_preview_attachment', onDelete: 'SET NULL')]
    #[Groups(['attachment_type:read', 'attachment_type:write', 'full'])]
    protected ?Attachment $master_picture_attachment = null;

    /** @var Collection<int, AttachmentTypeParameter>
     */
    #[Assert\Valid]
    #[UniqueObjectCollection(fields: ['name', 'group', 'element'])]
    #[ORM\OneToMany(mappedBy: 'element', targetEntity: AttachmentTypeParameter::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['group' => Criteria::ASC, 'name' => 'ASC'])]
    #[Groups(['attachment_type:read', 'attachment_type:write', 'import', 'full'])]
    protected Collection $parameters;

    /**
     * @var Collection<Attachment>
     */
    #[ORM\OneToMany(mappedBy: 'attachment_type', targetEntity: Attachment::class)]
    protected Collection $attachments_with_type;

    /**
     * @var string[]|null A list of allowed targets where this attachment type can be assigned to, as a list of portable names
     */
    #[ORM\Column(type: Types::SIMPLE_ARRAY, nullable: true)]
    protected ?array $allowed_targets = null;

    /**
     * @var class-string<Attachment>[]|null
     */
    protected ?array $allowed_targets_parsed_cache = null;

    #[Groups(['attachment_type:read'])]
    protected ?\DateTimeImmutable $addedDate = null;
    #[Groups(['attachment_type:read'])]
    protected ?\DateTimeImmutable $lastModified = null;


    public function __construct()
    {
        $this->initializeAttachments();
        $this->initializeStructuralElement();
        $this->children = new ArrayCollection();
        $this->parameters = new ArrayCollection();
        $this->attachments = new ArrayCollection();
        $this->attachments_with_type = new ArrayCollection();
    }

    public function __clone()
    {
        if ($this->id) {
            $this->cloneDBElement();
            $this->cloneAttachments();
            
            // We create a new object, so give it a new creation date
            $this->addedDate = null;
            
            //Deep clone parameters
            $parameters = $this->parameters;
            $this->parameters = new ArrayCollection();
            foreach ($parameters as $parameter) {
                $this->addParameter(clone $parameter);
            }
        }
    }

    public function jsonSerialize(): array
    {
        return ['@id' => $this->getID()];
    }

    /**
     * Get all attachments ("Attachment" objects) with this type.
     *
     * @return Collection all attachments with this type, as a one-dimensional array of Attachments
     *                                 (sorted by their names)
     * @phpstan-return Collection<int, Attachment>
     */
    public function getAttachmentsForType(): Collection
    {
        return $this->attachments_with_type;
    }

    /**
     * Gets a filter, which file types are allowed for attachment files.
     * Must be in the format of <input type=file> accept attribute
     * (See https://developer.mozilla.org/en-US/docs/Web/HTML/Element/input/file#Unique_file_type_specifiers).
     */
    public function getFiletypeFilter(): string
    {
        return $this->filetype_filter;
    }

    /**
     * Sets the filetype filter pattern.
     *
     * @param string $filetype_filter The new filetype filter
     *
     * @return $this
     */
    public function setFiletypeFilter(string $filetype_filter): self
    {
        $this->filetype_filter = $filetype_filter;

        return $this;
    }

    /**
     * Returns a list of allowed targets as class names (e.g. PartAttachment::class), where this attachment type can be assigned to. If null, there are no restrictions.
     * @return class-string<Attachment>[]|null
     */
    public function getAllowedTargets(): ?array
    {
        //Use cached value if available
        if ($this->allowed_targets_parsed_cache !== null) {
            return $this->allowed_targets_parsed_cache;
        }

        if (empty($this->allowed_targets)) {
            return null;
        }

        $tmp = [];
        foreach ($this->allowed_targets as $target) {
            if (isset(Attachment::ORM_DISCRIMINATOR_MAP[$target])) {
                $tmp[] = Attachment::ORM_DISCRIMINATOR_MAP[$target];
            }
            //Otherwise ignore the entry, as it is invalid
        }

        //Cache the parsed value
        $this->allowed_targets_parsed_cache = $tmp;
        return $tmp;
    }

    /**
     * Sets the allowed targets for this attachment type. Allowed targets are specified as a list of class names (e.g. PartAttachment::class). If null is passed, there are no restrictions.
     * @param  class-string<Attachment>[]|null  $allowed_targets
     * @return $this
     */
    public function setAllowedTargets(?array $allowed_targets): self
    {
        if ($allowed_targets === null) {
            $this->allowed_targets = null;
        } else {
            $tmp = [];
            foreach ($allowed_targets as $target) {
                $discriminator = array_search($target, Attachment::ORM_DISCRIMINATOR_MAP, true);
                if ($discriminator !== false) {
                    $tmp[] = $discriminator;
                } else {
                    throw new \InvalidArgumentException("Invalid allowed target: $target. Allowed targets must be a class name of an Attachment subclass.");
                }
            }
            $this->allowed_targets = $tmp;
        }

        //Reset the cache
        $this->allowed_targets_parsed_cache = null;
        return $this;
    }

    /**
     * Checks if this attachment type is allowed for the given attachment target.
     * @param  Attachment|string  $attachment
     * @return bool
     */
    public function isAllowedForTarget(Attachment|string $attachment): bool
    {
        //If no restrictions are set, allow all targets
        if ($this->getAllowedTargets() === null) {
            return true;
        }

        //Iterate over all allowed targets and check if the attachment is an instance of any of them
        foreach ($this->getAllowedTargets() as $allowed_target) {
            if (is_a($attachment, $allowed_target, true)) {
                return true;
            }
        }

        return false;
    }
}
