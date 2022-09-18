<?php
/**
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 * Copyright (C) 2019 - 2020 Jan Böhmer (https://github.com/jbtronics)
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

/**
 * part-db version 0.1
 * Copyright (C) 2005 Christoph Lechner
 * http://www.cl-projects.de/.
 *
 * part-db version 0.2+
 * Copyright (C) 2009 K. Jacobs and others (see authors.php)
 * http://code.google.com/p/part-db/
 *
 * Part-DB Version 0.4+
 * Copyright (C) 2016 - 2019 Jan Böhmer
 * https://github.com/jbtronics
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA
 */

namespace App\Entity\Parts;

use App\Entity\Attachments\StorelocationAttachment;
use App\Entity\Base\AbstractPartsContainingDBElement;
use App\Entity\Parameters\StorelocationParameter;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Class Store location.
 *
 * @ORM\Entity(repositoryClass="App\Repository\Parts\StorelocationRepository")
 * @ORM\Table("`storelocations`")
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
     */
    protected bool $is_full = false;

    /**
     * @var bool
     * @ORM\Column(type="boolean")
     */
    protected bool $only_single_part = false;

    /**
     * @var bool
     * @ORM\Column(type="boolean")
     */
    protected bool $limit_to_existing_parts = false;
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
        return (bool) $this->is_full;
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
