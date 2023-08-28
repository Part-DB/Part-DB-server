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

namespace App\Entity\Parts;

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
use App\Entity\Attachments\Attachment;
use App\Entity\Attachments\AttachmentTypeAttachment;
use App\Repository\Parts\ManufacturerRepository;
use App\Entity\Base\AbstractStructuralDBElement;
use Doctrine\Common\Collections\ArrayCollection;
use App\Entity\Attachments\ManufacturerAttachment;
use App\Entity\Base\AbstractCompany;
use App\Entity\Parameters\ManufacturerParameter;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * This entity represents a manufacturer of a part (The company that produces the part).
 *
 * @extends AbstractCompany<ManufacturerAttachment, ManufacturerParameter>
 */
#[ORM\Entity(repositoryClass: ManufacturerRepository::class)]
#[ORM\Table('`manufacturers`')]
#[ORM\Index(name: 'manufacturer_name', columns: ['name'])]
#[ORM\Index(name: 'manufacturer_idx_parent_name', columns: ['parent_id', 'name'])]
#[ApiResource(
    operations: [
        new Get(security: 'is_granted("read", object)'),
        new GetCollection(security: 'is_granted("@manufacturers.read")'),
        new Post(securityPostDenormalize: 'is_granted("create", object)'),
        new Patch(security: 'is_granted("edit", object)'),
        new Delete(security: 'is_granted("delete", object)'),
    ],
    normalizationContext: ['groups' => ['manufacturer:read', 'company:read', 'api:basic:read'], 'openapi_definition_name' => 'Read'],
    denormalizationContext: ['groups' => ['manufacturer:write', 'company:write', 'api:basic:write'], 'openapi_definition_name' => 'Write'],
)]
#[ApiResource(
    uriTemplate: '/manufacturers/{id}/children.{_format}',
    operations: [
        new GetCollection(openapiContext: ['summary' => 'Retrieves the children elements of a manufacturer.'],
            security: 'is_granted("@manufacturers.read")')
    ],
    uriVariables: [
        'id' => new Link(fromProperty: 'children', fromClass: Manufacturer::class)
    ],
    normalizationContext: ['groups' => ['manufacturer:read', 'company:read', 'api:basic:read'], 'openapi_definition_name' => 'Read']
)]
#[ApiFilter(PropertyFilter::class)]
class Manufacturer extends AbstractCompany
{
    #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'children')]
    #[ORM\JoinColumn(name: 'parent_id')]
    #[Groups(['manufacturer:read', 'manufacturer:write'])]
    #[ApiProperty(readableLink: false, writableLink: false)]
    protected ?AbstractStructuralDBElement $parent = null;

    #[ORM\OneToMany(targetEntity: self::class, mappedBy: 'parent')]
    #[ORM\OrderBy(['name' => 'ASC'])]
    protected Collection $children;

    /**
     * @var Collection<int, ManufacturerAttachment>
     */
    #[Assert\Valid]
    #[ORM\OneToMany(targetEntity: ManufacturerAttachment::class, mappedBy: 'element', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['name' => 'ASC'])]
    #[Groups(['manufacturer:read', 'manufacturer:write'])]
    #[ApiProperty(readableLink: false, writableLink: true)]
    protected Collection $attachments;

    #[ORM\ManyToOne(targetEntity: ManufacturerAttachment::class)]
    #[ORM\JoinColumn(name: 'id_preview_attachment', onDelete: 'SET NULL')]
    #[Groups(['manufacturer:read', 'manufacturer:write'])]
    #[ApiProperty(readableLink: false, writableLink: true)]
    protected ?Attachment $master_picture_attachment = null;

    /** @var Collection<int, ManufacturerParameter>
     */
    #[Assert\Valid]
    #[ORM\OneToMany(targetEntity: ManufacturerParameter::class, mappedBy: 'element', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['group' => 'ASC', 'name' => 'ASC'])]
    #[Groups(['manufacturer:read', 'manufacturer:write'])]
    #[ApiProperty(readableLink: false, writableLink: true)]
    protected Collection $parameters;
    public function __construct()
    {
        parent::__construct();
        $this->children = new ArrayCollection();
        $this->attachments = new ArrayCollection();
        $this->parameters = new ArrayCollection();
    }
}
