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

use App\Entity\Attachments\MeasurementUnitAttachment;
use App\Entity\Base\AbstractPartsContainingDBElement;
use App\Entity\Parameters\MeasurementUnitParameter;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * This unit represents the unit in which the amount of parts in stock are measured.
 * This could be something like N, grams, meters, etc...
 */
#[UniqueEntity('unit')]
#[ORM\Entity(repositoryClass: \App\Repository\Parts\MeasurementUnitRepository::class)]
#[ORM\Table(name: '`measurement_units`')]
#[ORM\Index(name: 'unit_idx_name', columns: ['name'])]
#[ORM\Index(name: 'unit_idx_parent_name', columns: ['parent_id', 'name'])]
class MeasurementUnit extends AbstractPartsContainingDBElement
{
    /**
     * @var string The unit symbol that should be used for the Unit. This could be something like "", g (for grams)
     *             or m (for meters).
     */
    #[Assert\Length(max: 10)]
    #[Groups(['extended', 'full', 'import'])]
    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::STRING, name: 'unit', nullable: true)]
    protected ?string $unit = null;

    /**
     * @var bool Determines if the amount value associated with this unit should be treated as integer.
     *           Set to false, to measure continuous sizes likes masses or lengths.
     */
    #[Groups(['extended', 'full', 'import'])]
    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::BOOLEAN, name: 'is_integer')]
    protected bool $is_integer = false;

    /**
     * @var bool Determines if the unit can be used with SI Prefixes (kilo, giga, milli, etc.).
     *           Useful for sizes like meters. For this the unit must be set
     */
    #[Assert\Expression('this.isUseSIPrefix() == false or this.getUnit() != null', message: 'validator.measurement_unit.use_si_prefix_needs_unit')]
    #[Groups(['full', 'import'])]
    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::BOOLEAN, name: 'use_si_prefix')]
    protected bool $use_si_prefix = false;

    /**
     * @var Collection
     */
    #[ORM\OneToMany(targetEntity: 'MeasurementUnit', mappedBy: 'parent', cascade: ['persist'])]
    #[ORM\OrderBy(['name' => 'ASC'])]
    protected Collection $children;

    #[ORM\ManyToOne(targetEntity: 'MeasurementUnit', inversedBy: 'children')]
    #[ORM\JoinColumn(name: 'parent_id')]
    protected ?\App\Entity\Base\AbstractStructuralDBElement $parent = null;

    /**
     * @var Collection<int, MeasurementUnitAttachment>
     */
    #[Assert\Valid]
    #[ORM\OneToMany(targetEntity: \App\Entity\Attachments\MeasurementUnitAttachment::class, mappedBy: 'element', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['name' => 'ASC'])]
    protected Collection $attachments;

    /** @var Collection<int, MeasurementUnitParameter>
     */
    #[Assert\Valid]
    #[ORM\OneToMany(targetEntity: \App\Entity\Parameters\MeasurementUnitParameter::class, mappedBy: 'element', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['group' => 'ASC', 'name' => 'ASC'])]
    protected Collection $parameters;

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
        $this->children = new \Doctrine\Common\Collections\ArrayCollection();
        $this->attachments = new \Doctrine\Common\Collections\ArrayCollection();
        $this->parameters = new \Doctrine\Common\Collections\ArrayCollection();
    }
}
