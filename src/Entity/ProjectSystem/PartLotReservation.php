<?php

declare(strict_types=1);

/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2023 Jan Böhmer (https://github.com/jbtronics)
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU Affero General Public License as published
 *  by the Free Software Foundation, either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU Affero General Public License for more details.
 *
 *  You should have received a copy of the GNU Affero General Public License
 *  along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */
namespace App\Entity\ProjectSystem;

use App\Entity\Base\AbstractDBElement;
use App\Entity\Parts\PartLot;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Records that a PlannedProject has reserved a specific amount of stock from a specific PartLot, on behalf of
 * one of the source project's BOM entries. This is the sole source of truth for "reserved quantity" - it is
 * never denormalized onto PartLot, it is always summed on demand (see PartLot::getReservedAmount()).
 *
 * @see \App\Tests\Entity\ProjectSystem\PartLotReservationTest
 */
#[ORM\Entity]
#[ORM\Table(name: 'part_lot_reservations')]
class PartLotReservation extends AbstractDBElement
{
    #[ORM\ManyToOne(targetEntity: PlannedProject::class, inversedBy: 'reservations')]
    #[ORM\JoinColumn(name: 'id_planned_project', nullable: false, onDelete: 'CASCADE')]
    protected PlannedProject $plannedProject;

    /**
     * @var ProjectBOMEntry The BOM entry of the source project this reservation was made for.
     *                      Deleting a BOM entry that still has reservations is blocked at the application level
     *                      (see ProjectController::deleteBOMEntry()); no cascade is configured here on purpose.
     */
    #[ORM\ManyToOne(targetEntity: ProjectBOMEntry::class, inversedBy: 'reservations')]
    #[ORM\JoinColumn(name: 'id_bom_entry', nullable: false)]
    protected ProjectBOMEntry $bomEntry;

    /**
     * @var PartLot The part lot this reservation reserves stock from. Deleting a lot that still has reservations
     *              is blocked at the application level; no cascade is configured here on purpose.
     */
    #[ORM\ManyToOne(targetEntity: PartLot::class, inversedBy: 'reservations')]
    #[ORM\JoinColumn(name: 'id_part_lot', nullable: false)]
    protected PartLot $partLot;

    #[Assert\Positive]
    #[ORM\Column(type: Types::FLOAT)]
    protected float $amount;

    public function __construct(PlannedProject $plannedProject, ProjectBOMEntry $bomEntry, PartLot $partLot, float $amount)
    {
        $this->plannedProject = $plannedProject;
        $this->bomEntry = $bomEntry;
        $this->partLot = $partLot;
        $this->amount = $amount;
    }

    public function getPlannedProject(): PlannedProject
    {
        return $this->plannedProject;
    }

    public function getBomEntry(): ProjectBOMEntry
    {
        return $this->bomEntry;
    }

    public function getPartLot(): PartLot
    {
        return $this->partLot;
    }

    public function getAmount(): float
    {
        return $this->amount;
    }

    public function setAmount(float $amount): self
    {
        $this->amount = $amount;
        return $this;
    }
}
