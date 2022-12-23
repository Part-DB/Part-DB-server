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
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use InvalidArgumentException;

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
     */
    protected $bom_entries;

    /**
     * @ORM\Column(type="integer")
     */
    protected int $order_quantity = 0;

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


}
