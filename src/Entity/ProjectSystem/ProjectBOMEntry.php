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

namespace App\Entity\ProjectSystem;

use App\Entity\Base\AbstractDBElement;
use App\Entity\Base\TimestampTrait;
use App\Entity\Parts\Part;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * The ProjectBOMEntry class represents a entry in a project's BOM.
 *
 * @ORM\Table("device_parts")
 * @ORM\Entity()
 */
class ProjectBOMEntry extends AbstractDBElement
{
    use TimestampTrait;

    /**
     * @var int
     * @ORM\Column(type="float", name="quantity")
     * @Assert\PositiveOrZero()
     */
    protected float $quantity;

    /**
     * @var string A comma separated list of the names, where this parts should be placed
     * @ORM\Column(type="text", name="mountnames")
     */
    protected string $mountnames;

    /**
     * @var string An optional name describing this BOM entry (useful for non-part entries)
     * @ORM\Column(type="text")
     */
    protected string $name;

    /**
     * @var string An optional comment for this BOM entry
     * @ORM\Column(type="text")
     */
    protected string $comment;

    /**
     * @var Project
     * @ORM\ManyToOne(targetEntity="Project", inversedBy="parts")
     * @ORM\JoinColumn(name="id_device", referencedColumnName="id")
     */
    protected ?Project $project = null;

    /**
     * @var Part|null The part associated with this
     * @ORM\ManyToOne(targetEntity="App\Entity\Parts\Part", inversedBy="project_bom_entries")
     * @ORM\JoinColumn(name="id_part", referencedColumnName="id", nullable=true)
     */
    protected ?Part $part = null;

    /**
     * @return float
     */
    public function getQuantity(): float
    {
        return $this->quantity;
    }

    /**
     * @param  float  $quantity
     * @return ProjectBOMEntry
     */
    public function setQuantity(float $quantity): ProjectBOMEntry
    {
        $this->quantity = $quantity;
        return $this;
    }

    /**
     * @return string
     */
    public function getMountnames(): string
    {
        return $this->mountnames;
    }

    /**
     * @param  string  $mountnames
     * @return ProjectBOMEntry
     */
    public function setMountnames(string $mountnames): ProjectBOMEntry
    {
        $this->mountnames = $mountnames;
        return $this;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @param  string  $name
     * @return ProjectBOMEntry
     */
    public function setName(string $name): ProjectBOMEntry
    {
        $this->name = $name;
        return $this;
    }

    /**
     * @return string
     */
    public function getComment(): string
    {
        return $this->comment;
    }

    /**
     * @param  string  $comment
     * @return ProjectBOMEntry
     */
    public function setComment(string $comment): ProjectBOMEntry
    {
        $this->comment = $comment;
        return $this;
    }

    /**
     * @return Project|null
     */
    public function getProject(): ?Project
    {
        return $this->project;
    }

    /**
     * @param  Project|null  $project
     * @return ProjectBOMEntry
     */
    public function setProject(?Project $project): ProjectBOMEntry
    {
        $this->project = $project;
        return $this;
    }



    /**
     * @return Part|null
     */
    public function getPart(): ?Part
    {
        return $this->part;
    }

    /**
     * @param  Part|null  $part
     * @return ProjectBOMEntry
     */
    public function setPart(?Part $part): ProjectBOMEntry
    {
        $this->part = $part;
        return $this;
    }


}
