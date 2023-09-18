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

use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Serializer\Filter\PropertyFilter;
use App\Entity\Parts\Footprint;
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
 * @extends AbstractStructuralDBElement<AttachmentTypeAttachment, AttachmentTypeParameter>
 */
#[ORM\Entity(repositoryClass: StructuralDBElementRepository::class)]
#[ORM\Table(name: '`attachment_types`')]
#[ORM\Index(name: 'attachment_types_idx_name', columns: ['name'])]
#[ORM\Index(name: 'attachment_types_idx_parent_name', columns: ['parent_id', 'name'])]
#[ApiResource(
    operations: [
        new Get(security: 'is_granted("read", object)'),
        new GetCollection(security: 'is_granted("@attachment_types.read")'),
        new Post(securityPostDenormalize: 'is_granted("create", object)'),
        new Patch(security: 'is_granted("edit", object)'),
        new Delete(security: 'is_granted("delete", object)'),
    ],
    normalizationContext: ['groups' => ['attachment_type:read', 'api:basic:read'], 'openapi_definition_name' => 'Read'],
    denormalizationContext: ['groups' => ['attachment_type:write', 'api:basic:write'], 'openapi_definition_name' => 'Write'],
)]
#[ApiResource(
    uriTemplate: '/attachment_types/{id}/children.{_format}',
    operations: [
        new GetCollection(openapiContext: ['summary' => 'Retrieves the children elements of an attachment type.'],
            security: 'is_granted("@attachment_types.read")')
    ],
    uriVariables: [
        'id' => new Link(fromProperty: 'children', fromClass: AttachmentType::class)
    ],
    normalizationContext: ['groups' => ['footprint:read', 'api:basic:read'], 'openapi_definition_name' => 'Read']
)]
#[ApiFilter(PropertyFilter::class)]
class AttachmentType extends AbstractStructuralDBElement
{
    #[ORM\OneToMany(targetEntity: AttachmentType::class, mappedBy: 'parent', cascade: ['persist'])]
    #[ORM\OrderBy(['name' => 'ASC'])]
    protected Collection $children;

    #[ORM\ManyToOne(targetEntity: AttachmentType::class, inversedBy: 'children')]
    #[ORM\JoinColumn(name: 'parent_id')]
    #[Groups(['attachment_type:read', 'attachment_type:write'])]
    #[ApiProperty(readableLink: false, writableLink: false)]
    protected ?AbstractStructuralDBElement $parent = null;

    /**
     * @var string A comma separated list of file types, which are allowed for attachment files.
     * Must be in the format of <input type=file> accept attribute
     * (See https://developer.mozilla.org/en-US/docs/Web/HTML/Element/input/file#Unique_file_type_specifiers).
     */
    #[ORM\Column(type: Types::TEXT)]
    #[ValidFileFilter]
    #[Groups(['attachment_type:read', 'attachment_type:write'])]
    protected string $filetype_filter = '';

    /**
     * @var Collection<int, AttachmentTypeAttachment>
     */
    #[Assert\Valid]
    #[ORM\OneToMany(targetEntity: AttachmentTypeAttachment::class, mappedBy: 'element', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['name' => 'ASC'])]
    #[Groups(['attachment_type:read', 'attachment_type:write'])]
    protected Collection $attachments;

    #[ORM\ManyToOne(targetEntity: AttachmentTypeAttachment::class)]
    #[ORM\JoinColumn(name: 'id_preview_attachment', onDelete: 'SET NULL')]
    #[Groups(['attachment_type:read', 'attachment_type:write'])]
    protected ?Attachment $master_picture_attachment = null;

    /** @var Collection<int, AttachmentTypeParameter>
     */
    #[Assert\Valid]
    #[ORM\OneToMany(targetEntity: AttachmentTypeParameter::class, mappedBy: 'element', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['group' => 'ASC', 'name' => 'ASC'])]
    protected Collection $parameters;

    /**
     * @var Collection<Attachment>
     */
    #[ORM\OneToMany(targetEntity: Attachment::class, mappedBy: 'attachment_type')]
    protected Collection $attachments_with_type;

    public function __construct()
    {
        $this->children = new ArrayCollection();
        $this->parameters = new ArrayCollection();
        parent::__construct();
        $this->attachments = new ArrayCollection();
        $this->attachments_with_type = new ArrayCollection();
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
}
