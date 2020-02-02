<?php

declare(strict_types=1);

/**
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 * Copyright (C) 2019 Jan Böhmer (https://github.com/jbtronics)
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

namespace App\Entity\Parts\PartTraits;

use App\Entity\Parts\MeasurementUnit;
use App\Entity\Parts\PartLot;
use App\Security\Annotations\ColumnSecurity;
use Doctrine\Common\Collections\Collection;

/**
 * This trait collects all aspects of a part related to instock, part lots.
 */
trait InstockTrait
{
    /**
     * @var Collection|PartLot[] A list of part lots where this part is stored
     * @ORM\OneToMany(targetEntity="PartLot", mappedBy="part", cascade={"persist", "remove"}, orphanRemoval=true)
     * @Assert\Valid()
     * @ColumnSecurity(type="collection", prefix="lots")
     */
    protected $partLots;

    /**
     * @var float The minimum amount of the part that has to be instock, otherwise more is ordered.
     *            Given in the partUnit.
     * @ORM\Column(type="float")
     * @Assert\PositiveOrZero()
     * @ColumnSecurity(prefix="minamount", type="integer")
     */
    protected $minamount = 0;

    /**
     * @var ?MeasurementUnit the unit in which the part's amount is measured
     * @ORM\ManyToOne(targetEntity="MeasurementUnit", inversedBy="parts")
     * @ORM\JoinColumn(name="id_part_unit", referencedColumnName="id", nullable=true)
     * @ColumnSecurity(type="object", prefix="unit")
     */
    protected $partUnit;

    /**
     * Get all part lots where this part is stored.
     *
     * @return PartLot[]|Collection
     */
    public function getPartLots(): Collection
    {
        return $this->partLots;
    }

    /**
     * Adds the given part lot, to the list of part lots.
     * The part lot is assigned to this part.
     * @param  PartLot  $lot
     * @return $this
     */
    public function addPartLot(PartLot $lot): self
    {
        $lot->setPart($this);
        $this->partLots->add($lot);

        return $this;
    }

    /**
     * Removes the given part lot from the list of part lots.
     *
     * @param PartLot $lot the part lot that should be deleted
     * @return $this
     */
    public function removePartLot(PartLot $lot): self
    {
        $this->partLots->removeElement($lot);

        return $this;
    }

    /**
     * Gets the measurement unit in which the part's amount should be measured.
     * Returns null if no specific unit was that. That means the parts are measured simply in quantity numbers.
     */
    public function getPartUnit(): ?MeasurementUnit
    {
        return $this->partUnit;
    }

    /**
     * Sets the measurement unit in which the part's amount should be measured.
     * Set to null, if the part should be measured in quantities.
     * @param  MeasurementUnit|null  $partUnit
     * @return $this
     */
    public function setPartUnit(?MeasurementUnit $partUnit): self
    {
        $this->partUnit = $partUnit;

        return $this;
    }

    /**
     *  Get the count of parts which must be in stock at least.
     * If a integer-based part unit is selected, the value will be rounded to integers.
     *
     * @return float count of parts which must be in stock at least
     */
    public function getMinAmount(): float
    {
        if ($this->useFloatAmount()) {
            return $this->minamount;
        }

        return round($this->minamount);
    }

    /**
     * Checks if this part uses the float amount .
     * This setting is based on the part unit (see MeasurementUnit->isInteger()).
     *
     * @return bool True if the float amount field should be used. False if the integer instock field should be used.
     */
    public function useFloatAmount(): bool
    {
        if ($this->partUnit instanceof MeasurementUnit) {
            return ! $this->partUnit->isInteger();
        }

        //When no part unit is set, treat it as part count, and so use the integer value.
        return false;
    }

    /**
     * Returns the summed amount of this part (over all part lots)
     * Part Lots that have unknown value or are expired, are not used for this value.
     *
     * @return float The amount of parts given in partUnit
     *
     */
    public function getAmountSum(): float
    {
        //TODO: Find a method to do this natively in SQL, the current method could be a bit slow
        $sum = 0;
        foreach ($this->getPartLots() as $lot) {
            //Dont use the instock value, if it is unkown
            if ($lot->isInstockUnknown() || $lot->isExpired() ?? false) {
                continue;
            }

            $sum += $lot->getAmount();
        }

        if ($this->useFloatAmount()) {
            return $sum;
        }

        return round($sum);
    }

    /**
     * Set the minimum amount of parts that have to be instock.
     * See getPartUnit() for the associated unit.
     *
     * @param float $new_minamount the new count of parts which should be in stock at least
     * @return $this
     */
    public function setMinAmount(float $new_minamount): self
    {
        $this->minamount = $new_minamount;

        return $this;
    }
}
