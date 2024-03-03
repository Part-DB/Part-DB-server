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
 * @extends AbstractStructuralDBElement<AttachmentTypeAttachment, AttachmentTypeParameter>
 */
#[ORM\Entity(repositoryClass: StructuralDBElementRepository::class)]
#[ORM\Table(name: '`attachment_types`')]
#[ORM\Index(columns: ['name'], name: 'attachment_types_idx_name')]
#[ORM\Index(columns: ['parent_id', 'name'], name: 'attachment_types_idx_parent_name')]
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
class AttachmentType extends AbstractStructuralDBElement
{
    #[ORM\OneToMany(mappedBy: 'parent', targetEntity: AttachmentType::class, cascade: ['persist'])]
    #[ORM\OrderBy(['name' => 'ASC'])]
    protected Collection $children;

    #[ORM\ManyToOne(targetEntity: AttachmentType::class, inversedBy: 'children')]
    #[ORM\JoinColumn(name: 'parent_id')]
    #[Groups(['attachment_type:read', 'attachment_type:write'])]
    #[ApiProperty(readableLink: false, writableLink: false)]
    protected ?AbstractStructuralDBElement $parent = null;

    /**
     * @var string A comma separated list of file types, which are allowed for attachment files.
     * Must be in the format of <pre><input type=file></pre> accept attribute
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
    #[ORM\OneToMany(mappedBy: 'element', targetEntity: AttachmentTypeAttachment::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
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
    #[ORM\OneToMany(mappedBy: 'element', targetEntity: AttachmentTypeParameter::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['group' => 'ASC', 'name' => 'ASC'])]
    #[Groups(['attachment_type:read', 'attachment_type:write'])]
    protected Collection $parameters;

    /**
     * @var Collection<Attachment>
     */
    #[ORM\OneToMany(mappedBy: 'attachment_type', targetEntity: Attachment::class)]
    protected Collection $attachments_with_type;

    #[Groups(['attachment_type:read'])]
    protected ?\DateTimeInterface $addedDate = null;
    #[Groups(['attachment_type:read'])]
    protected ?\DateTimeInterface $lastModified = null;


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
