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

use App\Entity\Attachments\ProjectAttachment;
use App\Entity\Base\AbstractStructuralDBElement;
use App\Entity\Parameters\ProjectParameter;
use App\Entity\Parts\Part;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use InvalidArgumentException;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Class AttachmentType.
 *
 * @ORM\Entity(repositoryClass="App\Repository\Parts\DeviceRepository")
 * @ORM\Table(name="devices")
 */
class Project extends AbstractStructuralDBElement
{
    /**
     * @ORM\OneToMany(targetEntity="Project", mappedBy="parent")
     * @ORM\OrderBy({"name" = "ASC"})
     * @var Collection
     */
    protected $children;

    /**
     * @ORM\ManyToOne(targetEntity="Project", inversedBy="children")
     * @ORM\JoinColumn(name="parent_id", referencedColumnName="id")
     */
    protected $parent;

    /**
     * @ORM\OneToMany(targetEntity="ProjectBOMEntry", mappedBy="project", cascade={"persist", "remove"}, orphanRemoval=true)
     * @Assert\Valid()
     */
    protected $bom_entries;

    /**
     * @ORM\Column(type="integer")
     */
    protected int $order_quantity = 0;

    /**
     * @var string The current status of the project
     * @ORM\Column(type="string", length=64, nullable=true)
     * @Assert\Choice({"draft","planning","in_production","finished","archived"})
     */
    protected ?string $status = null;


    /**
     * @var Part|null The (optional) part that represents the builds of this project in the stock
     * @ORM\OneToOne(targetEntity="App\Entity\Parts\Part", mappedBy="built_project", cascade={"persist"}, orphanRemoval=true)
     */
    protected ?Part $build_part = null;

    /**
     * @ORM\Column(type="boolean")
     */
    protected bool $order_only_missing_parts = false;

    /**
     * @ORM\Column(type="text", nullable=false)
     */
    protected string $description = '';

    /**
     * @var Collection<int, ProjectAttachment>
     * @ORM\OneToMany(targetEntity="App\Entity\Attachments\ProjectAttachment", mappedBy="element", cascade={"persist", "remove"}, orphanRemoval=true)
     * @ORM\OrderBy({"name" = "ASC"})
     */
    protected $attachments;

    /** @var Collection<int, ProjectParameter>
     * @ORM\OneToMany(targetEntity="App\Entity\Parameters\ProjectParameter", mappedBy="element", cascade={"persist", "remove"}, orphanRemoval=true)
     * @ORM\OrderBy({"group" = "ASC" ,"name" = "ASC"})
     */
    protected $parameters;

    /********************************************************************************
     *
     *   Getters
     *
     *********************************************************************************/

    public function __construct()
    {
        parent::__construct();
        $this->bom_entries = new ArrayCollection();
    }

    public function __clone()
    {
        //When cloning this project, we have to clone each bom entry too.
        if ($this->id) {
            $bom_entries = $this->bom_entries;
            $this->bom_entries = new ArrayCollection();
            //Set master attachment is needed
            foreach ($bom_entries as $bom_entry) {
                $clone = clone $bom_entry;
                $this->bom_entries->add($clone);
            }
        }

        //Parent has to be last call, as it resets the ID
        parent::__clone();
    }

    /**
     *  Get the order quantity of this device.
     *
     * @return int the order quantity
     */
    public function getOrderQuantity(): int
    {
        return $this->order_quantity;
    }

    /**
     *  Get the "order_only_missing_parts" attribute.
     *
     * @return bool the "order_only_missing_parts" attribute
     */
    public function getOrderOnlyMissingParts(): bool
    {
        return $this->order_only_missing_parts;
    }

    /********************************************************************************
     *
     *   Setters
     *
     *********************************************************************************/

    /**
     *  Set the order quantity.
     *
     * @param int $new_order_quantity the new order quantity
     *
     * @return $this
     */
    public function setOrderQuantity(int $new_order_quantity): self
    {
        if ($new_order_quantity < 0) {
            throw new InvalidArgumentException('The new order quantity must not be negative!');
        }
        $this->order_quantity = $new_order_quantity;

        return $this;
    }

    /**
     *  Set the "order_only_missing_parts" attribute.
     *
     * @param bool $new_order_only_missing_parts the new "order_only_missing_parts" attribute
     *
     * @return Project
     */
    public function setOrderOnlyMissingParts(bool $new_order_only_missing_parts): self
    {
        $this->order_only_missing_parts = $new_order_only_missing_parts;

        return $this;
    }

    /**
     * @return Collection<int, ProjectBOMEntry>|ProjectBOMEntry[]
     */
    public function getBomEntries(): Collection
    {
        return $this->bom_entries;
    }

    /**
     * @param  ProjectBOMEntry  $entry
     * @return $this
     */
    public function addBomEntry(ProjectBOMEntry $entry): self
    {
        $entry->setProject($this);
        $this->bom_entries->add($entry);
        return $this;
    }

    /**
     * @param  ProjectBOMEntry  $entry
     * @return $this
     */
    public function removeBomEntry(ProjectBOMEntry $entry): self
    {
        $this->bom_entries->removeElement($entry);
        return $this;
    }

    /**
     * @return string
     */
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * @param  string  $description
     * @return Project
     */
    public function setDescription(string $description): Project
    {
        $this->description = $description;
        return $this;
    }

    /**
     * @return string
     */
    public function getStatus(): ?string
    {
        return $this->status;
    }

    /**
     * @param  string  $status
     */
    public function setStatus(?string $status): void
    {
        $this->status = $status;
    }

    /**
     * Checks if this project has a associated part representing the builds of this project in the stock.
     * @return bool
     */
    public function hasBuildPart(): bool
    {
        return $this->build_part !== null;
    }

    /**
     * Gets the part representing the builds of this project in the stock, if it is existing
     * @return Part|null
     */
    public function getBuildPart(): ?Part
    {
        return $this->build_part;
    }

    /**
     * Sets the part representing the builds of this project in the stock.
     * @param  Part|null  $build_part
     */
    public function setBuildPart(?Part $build_part): void
    {
        $this->build_part = $build_part;
        if ($build_part) {
            $build_part->setBuiltProject($this);
        }
    }



}
