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

use ApiPlatform\Metadata\ApiProperty;
use App\Entity\Attachments\Attachment;
use App\Entity\Attachments\PartCustomStateAttachment;
use ApiPlatform\Doctrine\Common\Filter\DateFilterInterface;
use ApiPlatform\Doctrine\Orm\Filter\DateFilter;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Serializer\Filter\PropertyFilter;
use App\ApiPlatform\Filter\LikeFilter;
use App\Entity\Base\AbstractPartsContainingDBElement;
use App\Entity\Base\AbstractStructuralDBElement;
use App\Entity\Parameters\PartCustomStateParameter;
use App\Repository\Parts\PartCustomStateRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * This entity represents a custom part state.
 * If an organisation uses Part-DB and has its custom part states, this is useful.
 *
 * @extends AbstractPartsContainingDBElement<PartCustomStateAttachment,PartCustomStateParameter>
 */
#[ORM\Entity(repositoryClass: PartCustomStateRepository::class)]
#[ORM\Table('`part_custom_states`')]
#[ORM\Index(columns: ['name'], name: 'part_custom_state_name')]
#[ApiResource(
    operations: [
        new Get(security: 'is_granted("read", object)'),
        new GetCollection(security: 'is_granted("@part_custom_states.read")'),
        new Post(securityPostDenormalize: 'is_granted("create", object)'),
        new Patch(security: 'is_granted("edit", object)'),
        new Delete(security: 'is_granted("delete", object)'),
    ],
    normalizationContext: ['groups' => ['part_custom_state:read', 'api:basic:read'], 'openapi_definition_name' => 'Read'],
    denormalizationContext: ['groups' => ['part_custom_state:write', 'api:basic:write'], 'openapi_definition_name' => 'Write'],
)]
#[ApiFilter(PropertyFilter::class)]
#[ApiFilter(LikeFilter::class, properties: ["name"])]
#[ApiFilter(DateFilter::class, strategy: DateFilterInterface::EXCLUDE_NULL)]
#[ApiFilter(OrderFilter::class, properties: ['name', 'id', 'addedDate', 'lastModified'])]
class PartCustomState extends AbstractPartsContainingDBElement
{
    /**
     * @var string The comment info for this element as markdown
     */
    #[Groups(['part_custom_state:read', 'part_custom_state:write', 'full', 'import'])]
    protected string $comment = '';

    #[ORM\OneToMany(mappedBy: 'parent', targetEntity: self::class, cascade: ['persist'])]
    #[ORM\OrderBy(['name' => Criteria::ASC])]
    protected Collection $children;

    #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'children')]
    #[ORM\JoinColumn(name: 'parent_id')]
    #[Groups(['part_custom_state:read', 'part_custom_state:write'])]
    #[ApiProperty(readableLink: false, writableLink: false)]
    protected ?AbstractStructuralDBElement $parent = null;

    /**
     * @var Collection<int, PartCustomStateAttachment>
     */
    #[Assert\Valid]
    #[ORM\OneToMany(targetEntity: PartCustomStateAttachment::class, mappedBy: 'element', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['name' => Criteria::ASC])]
    #[Groups(['part_custom_state:read', 'part_custom_state:write'])]
    protected Collection $attachments;

    #[ORM\ManyToOne(targetEntity: PartCustomStateAttachment::class)]
    #[ORM\JoinColumn(name: 'id_preview_attachment', onDelete: 'SET NULL')]
    #[Groups(['part_custom_state:read', 'part_custom_state:write'])]
    protected ?Attachment $master_picture_attachment = null;

    /** @var Collection<int, PartCustomStateAttachment>
     */
    #[Assert\Valid]
    #[ORM\OneToMany(mappedBy: 'element', targetEntity: PartCustomStateAttachment::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['name' => 'ASC'])]
    #[Groups(['part_custom_state:read', 'part_custom_state:write'])]
    protected Collection $parameters;

    #[Groups(['part_custom_state:read'])]
    protected ?\DateTimeImmutable $addedDate = null;
    #[Groups(['part_custom_state:read'])]
    protected ?\DateTimeImmutable $lastModified = null;

    public function __construct()
    {
        parent::__construct();
        $this->children = new ArrayCollection();
        $this->attachments = new ArrayCollection();
        $this->parameters = new ArrayCollection();
    }
}
