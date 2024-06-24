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
use App\Entity\Attachments\Attachment;
use App\Repository\Parts\MeasurementUnitRepository;
use Doctrine\DBAL\Types\Types;
use App\Entity\Base\AbstractStructuralDBElement;
use Doctrine\Common\Collections\ArrayCollection;
use App\Entity\Attachments\MeasurementUnitAttachment;
use App\Entity\Base\AbstractPartsContainingDBElement;
use App\Entity\Parameters\MeasurementUnitParameter;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Constraints\Length;

/**
 * This unit represents the unit in which the amount of parts in stock are measured.
 * This could be something like N, grams, meters, etc...
 *
 * @extends AbstractPartsContainingDBElement<MeasurementUnitAttachment,MeasurementUnitParameter>
 */
#[UniqueEntity('unit')]
#[ORM\Entity(repositoryClass: MeasurementUnitRepository::class)]
#[ORM\Table(name: '`measurement_units`')]
#[ORM\Index(columns: ['name'], name: 'unit_idx_name')]
#[ORM\Index(columns: ['parent_id', 'name'], name: 'unit_idx_parent_name')]
#[ApiResource(
    operations: [
        new Get(security: 'is_granted("read", object)'),
        new GetCollection(security: 'is_granted("@measurement_units.read")'),
        new Post(securityPostDenormalize: 'is_granted("create", object)'),
        new Patch(security: 'is_granted("edit", object)'),
        new Delete(security: 'is_granted("delete", object)'),
    ],
    normalizationContext: ['groups' => ['measurement_unit:read', 'api:basic:read'], 'openapi_definition_name' => 'Read'],
    denormalizationContext: ['groups' => ['measurement_unit:write', 'api:basic:write', 'attachment:write', 'parameter:write'], 'openapi_definition_name' => 'Write'],
)]
#[ApiResource(
    uriTemplate: '/measurement_units/{id}/children.{_format}',
    operations: [
        new GetCollection(
            openapi: new Operation(summary: 'Retrieves the children elements of a MeasurementUnit.'),
            security: 'is_granted("@measurement_units.read")'
        )
    ],
    uriVariables: [
        'id' => new Link(fromProperty: 'children', fromClass: MeasurementUnit::class)
    ],
    normalizationContext: ['groups' => ['measurement_unit:read', 'api:basic:read'], 'openapi_definition_name' => 'Read']
)]
#[ApiFilter(PropertyFilter::class)]
#[ApiFilter(LikeFilter::class, properties: ["name", "comment", "unit"])]
#[ApiFilter(DateFilter::class, strategy: DateFilterInterface::EXCLUDE_NULL)]
#[ApiFilter(OrderFilter::class, properties: ['name', 'id', 'addedDate', 'lastModified'])]
class MeasurementUnit extends AbstractPartsContainingDBElement
{
    /**
     * @var string The unit symbol that should be used for the Unit. This could be something like "", g (for grams)
     *             or m (for meters).
     */
    #[Assert\Length(max: 10)]
    #[Groups(['simple', 'extended', 'full', 'import', 'measurement_unit:read', 'measurement_unit:write'])]
    #[ORM\Column(name: 'unit', type: Types::STRING, nullable: true)]
    protected ?string $unit = null;

    #[Groups(['measurement_unit:read', 'measurement_unit:write'])]
    protected string $comment = '';

    /**
     * @var bool Determines if the amount value associated with this unit should be treated as integer.
     *           Set to false, to measure continuous sizes likes masses or lengths.
     */
    #[Groups(['simple', 'extended', 'full', 'import', 'measurement_unit:read', 'measurement_unit:write'])]
    #[ORM\Column(name: 'is_integer', type: Types::BOOLEAN)]
    protected bool $is_integer = false;

    /**
     * @var bool Determines if the unit can be used with SI Prefixes (kilo, giga, milli, etc.).
     *           Useful for sizes like meters. For this the unit must be set
     */
    #[Assert\Expression('this.isUseSIPrefix() == false or this.getUnit() != null', message: 'validator.measurement_unit.use_si_prefix_needs_unit')]
    #[Groups(['simple', 'full', 'import', 'measurement_unit:read', 'measurement_unit:write'])]
    #[ORM\Column(name: 'use_si_prefix', type: Types::BOOLEAN)]
    protected bool $use_si_prefix = false;

    #[ORM\OneToMany(mappedBy: 'parent', targetEntity: self::class, cascade: ['persist'])]
    #[ORM\OrderBy(['name' => Criteria::ASC])]
    protected Collection $children;

    #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'children')]
    #[ORM\JoinColumn(name: 'parent_id')]
    #[Groups(['measurement_unit:read', 'measurement_unit:write'])]
    #[ApiProperty(readableLink: false, writableLink: false)]
    protected ?AbstractStructuralDBElement $parent = null;

    /**
     * @var Collection<int, MeasurementUnitAttachment>
     */
    #[Assert\Valid]
    #[ORM\OneToMany(mappedBy: 'element', targetEntity: MeasurementUnitAttachment::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['name' => Criteria::ASC])]
    #[Groups(['measurement_unit:read', 'measurement_unit:write'])]
    protected Collection $attachments;

    #[ORM\ManyToOne(targetEntity: MeasurementUnitAttachment::class)]
    #[ORM\JoinColumn(name: 'id_preview_attachment', onDelete: 'SET NULL')]
    #[Groups(['measurement_unit:read', 'measurement_unit:write'])]
    protected ?Attachment $master_picture_attachment = null;

    /** @var Collection<int, MeasurementUnitParameter>
     */
    #[Assert\Valid]
    #[ORM\OneToMany(mappedBy: 'element', targetEntity: MeasurementUnitParameter::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['group' => Criteria::ASC, 'name' => 'ASC'])]
    #[Groups(['measurement_unit:read', 'measurement_unit:write'])]
    protected Collection $parameters;

    #[Groups(['measurement_unit:read'])]
    protected ?\DateTimeImmutable $addedDate = null;
    #[Groups(['measurement_unit:read'])]
    protected ?\DateTimeImmutable $lastModified = null;


    /**
     * @return string
     */
    public function getUnit(): ?string
    {
        return $this->unit;
    }

    public function setUnit(?string $unit): self
    {
        $this->unit = $unit;

        return $this;
    }

    public function isInteger(): bool
    {
        return $this->is_integer;
    }

    public function setIsInteger(bool $isInteger): self
    {
        $this->is_integer = $isInteger;

        return $this;
    }

    public function isUseSIPrefix(): bool
    {
        return $this->use_si_prefix;
    }

    public function setUseSIPrefix(bool $usesSIPrefixes): self
    {
        $this->use_si_prefix = $usesSIPrefixes;

        return $this;
    }
    public function __construct()
    {
        parent::__construct();
        $this->children = new ArrayCollection();
        $this->attachments = new ArrayCollection();
        $this->parameters = new ArrayCollection();
    }
}
