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

use App\Entity\Attachments\StorelocationAttachment;
use App\Entity\Base\AbstractPartsContainingDBElement;
use App\Entity\Parameters\StorelocationParameter;
use App\Entity\UserSystem\User;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Class Store location.
 *
 * @ORM\Entity(repositoryClass="App\Repository\Parts\StorelocationRepository")
 * @ORM\Table("`storelocations`", indexes={
 *     @ORM\Index(name="location_idx_name", columns={"name"}),
 *     @ORM\Index(name="location_idx_parent_name", columns={"parent_id", "name"}),
 * })
 */
class Storelocation extends AbstractPartsContainingDBElement
{
    /**
     * @ORM\OneToMany(targetEntity="Storelocation", mappedBy="parent")
     * @ORM\OrderBy({"name" = "ASC"})
     * @var Collection
     */
    protected $children;

    /**
     * @ORM\ManyToOne(targetEntity="Storelocation", inversedBy="children")
     * @ORM\JoinColumn(name="parent_id", referencedColumnName="id")
     */
    protected $parent;

    /**
     * @var MeasurementUnit|null The measurement unit, which parts can be stored in here
     * @ORM\ManyToOne(targetEntity="MeasurementUnit")
     * @ORM\JoinColumn(name="storage_type_id", referencedColumnName="id")
     */
    protected ?MeasurementUnit $storage_type = null;

    /** @var Collection<int, StorelocationParameter>
     * @ORM\OneToMany(targetEntity="App\Entity\Parameters\StorelocationParameter", mappedBy="element", cascade={"persist", "remove"}, orphanRemoval=true)
     * @ORM\OrderBy({"group" = "ASC" ,"name" = "ASC"})
     * @Assert\Valid()
     */
    protected $parameters;

    /**
     * @var bool
     * @ORM\Column(type="boolean")
     * @Groups({"full", "import"})
     */
    protected bool $is_full = false;

    /**
     * @var bool
     * @ORM\Column(type="boolean")
     * @Groups({"full", "import"})
     */
    protected bool $only_single_part = false;

    /**
     * @var bool
     * @ORM\Column(type="boolean")
     * @Groups({"full", "import"})
     */
    protected bool $limit_to_existing_parts = false;

    /**
     * @var User|null The owner of this storage location
     * @ORM\ManyToOne(targetEntity="App\Entity\UserSystem\User")
     * @ORM\JoinColumn(name="id_owner", referencedColumnName="id", nullable=true, onDelete="SET NULL")
     * @Assert\Expression("this.getOwner() == null or this.getOwner().isAnonymousUser() === false", message="validator.part_lot.owner_must_not_be_anonymous")
     */
    protected ?User $owner = null;

    /**
     * @var bool If this is set to true, only parts lots, which are owned by the same user as the store location are allowed to be stored here.
     * @ORM\Column(type="boolean", options={"default":false})
     */
    protected bool $part_owner_must_match = false;

    /**
     * @var Collection<int, StorelocationAttachment>
     * @ORM\OneToMany(targetEntity="App\Entity\Attachments\StorelocationAttachment", mappedBy="element", cascade={"persist", "remove"}, orphanRemoval=true)
     * @Assert\Valid()
     */
    protected $attachments;

    /********************************************************************************
     *
     *   Getters
     *
     *********************************************************************************/

    /**
     * Get the "is full" attribute.
     *
     * When this attribute is set, it is not possible to add additional parts or increase the instock of existing parts.
     *
     * @return bool * true if the store location is full
     *              * false if the store location isn't full
     */
    public function isFull(): bool
    {
        return $this->is_full;
    }

    /**
     * When this property is set, only one part (but many instock) is allowed to be stored in this store location.
     */
    public function isOnlySinglePart(): bool
    {
        return $this->only_single_part;
    }

    /**
     * @return Storelocation
     */
    public function setOnlySinglePart(bool $only_single_part): self
    {
        $this->only_single_part = $only_single_part;

        return $this;
    }

    /**
     * When this property is set, it is only possible to increase the instock of parts, that are already stored here.
     */
    public function isLimitToExistingParts(): bool
    {
        return $this->limit_to_existing_parts;
    }

    /**
     * @return Storelocation
     */
    public function setLimitToExistingParts(bool $limit_to_existing_parts): self
    {
        $this->limit_to_existing_parts = $limit_to_existing_parts;

        return $this;
    }

    public function getStorageType(): ?MeasurementUnit
    {
        return $this->storage_type;
    }

    /**
     * @return Storelocation
     */
    public function setStorageType(?MeasurementUnit $storage_type): self
    {
        $this->storage_type = $storage_type;

        return $this;
    }

    /**
     * Returns the owner of this storage location
     * @return User|null
     */
    public function getOwner(): ?User
    {
        return $this->owner;
    }

    /**
     * Sets the owner of this storage location
     * @param  User|null  $owner
     * @return Storelocation
     */
    public function setOwner(?User $owner): Storelocation
    {
        $this->owner = $owner;
        return $this;
    }

    /**
     * If this is set to true, only parts lots, which are owned by the same user as the store location are allowed to be stored here.
     * @return bool
     */
    public function isPartOwnerMustMatch(): bool
    {
        return $this->part_owner_must_match;
    }

    /**
     * If this is set to true, only parts lots, which are owned by the same user as the store location are allowed to be stored here.
     * @param  bool  $part_owner_must_match
     * @return Storelocation
     */
    public function setPartOwnerMustMatch(bool $part_owner_must_match): Storelocation
    {
        $this->part_owner_must_match = $part_owner_must_match;
        return $this;
    }




    /********************************************************************************
     *
     *   Setters
     *
     *********************************************************************************/

    /**
     * Change the "is full" attribute of this store location.
     *
     *     "is_full" = true means that there is no more space in this storelocation.
     *     This attribute is only for information, it has no effect.
     *
     * @param bool $new_is_full * true means that the storelocation is full
     *                          * false means that the storelocation isn't full
     *
     * @return Storelocation
     */
    public function setIsFull(bool $new_is_full): self
    {
        $this->is_full = $new_is_full;

        return $this;
    }
}
