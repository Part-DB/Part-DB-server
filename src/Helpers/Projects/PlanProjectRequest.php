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
namespace App\Helpers\Projects;

use App\Entity\Parts\Part;
use App\Entity\Parts\PartLot;
use App\Entity\ProjectSystem\Project;
use App\Entity\ProjectSystem\ProjectBOMEntry;
use App\Validator\Constraints\ProjectSystem\ValidPlanProjectRequest;

/**
 * Represents a request to reserve stock for a future build of a project (i.e. to create a PlannedProject).
 * This is the planning-time analogue of ProjectBuildRequest: an unpersisted, per-request value object that
 * is validated and then handed to ProjectPlanningHelper::createPlan(). Unlike a build, planning never touches
 * stock levels directly (only reservations) and never adds to a builds part. Planning always succeeds: if
 * there is not enough available stock for a BOM entry, whatever is available gets reserved (partial
 * reservation) instead of blocking the plan from being created.
 *
 * @see \App\Tests\Helpers\Projects\PlanProjectRequestTest
 */
#[ValidPlanProjectRequest]
final class PlanProjectRequest
{
    private readonly int $number_of_builds;

    private string $name = '';

    private string $comment = '';

    /**
     * @var array<int, float>
     */
    private array $reservation_amounts = [];

    /**
     * @param  Project  $project  The project for which stock should be reserved
     * @param  int  $number_of_builds The number of builds that should be planned for
     */
    public function __construct(private readonly Project $project, int $number_of_builds)
    {
        if ($number_of_builds < 1) {
            throw new \InvalidArgumentException('Number of builds must be at least 1!');
        }
        $this->number_of_builds = $number_of_builds;

        $this->initializeArray();
    }

    private function initializeArray(): void
    {
        $this->reservation_amounts = [];

        foreach ($this->getPartBomEntries() as $bom_entry) {
            $remaining_amount = $this->getNeededAmountForBOMEntry($bom_entry);
            foreach ($this->getPartLotsForBOMEntry($bom_entry) as $lot) {
                $id = $lot->getID() ?? throw new \RuntimeException("Part lot needs to have an ID!");

                $this->reservation_amounts[$id] = max(0.0, min($remaining_amount, $lot->getAvailableAmount()));
                $remaining_amount -= $this->reservation_amounts[$id];
            }
        }
    }

    /**
     * Ensure that the projectBOMEntry belongs to the project, otherwise throw an exception.
     */
    private function ensureBOMEntryValid(ProjectBOMEntry $entry): void
    {
        if ($entry->getProject() !== $this->project) {
            throw new \InvalidArgumentException('The given BOM entry does not belong to the project!');
        }
    }

    public function getProject(): Project
    {
        return $this->project;
    }

    public function getNumberOfBuilds(): int
    {
        return $this->number_of_builds;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getComment(): string
    {
        return $this->comment;
    }

    public function setComment(string $comment): self
    {
        $this->comment = $comment;
        return $this;
    }

    /**
     * Returns the amount of parts that should be reserved from the given lot for the corresponding BOM entry.
     */
    public function getLotReservationAmount(PartLot|int $lot): float
    {
        $lot_id = $lot instanceof PartLot ? $lot->getID() : $lot;

        if ($lot_id === null) {
            throw new \InvalidArgumentException('The given lot must have an ID!');
        }

        if (!array_key_exists($lot_id, $this->reservation_amounts)) {
            throw new \InvalidArgumentException('The given lot is not in the reservation amounts array!');
        }

        return $this->reservation_amounts[$lot_id];
    }

    /**
     * Sets the amount of parts that should be reserved from the given lot for the corresponding BOM entry.
     * @return $this
     */
    public function setLotReservationAmount(PartLot|int $lot, float $amount): self
    {
        $lot_id = $lot instanceof PartLot ? $lot->getID() : $lot;

        if ($lot_id === null) {
            throw new \InvalidArgumentException('The given lot must have an ID!');
        }

        $this->reservation_amounts[$lot_id] = $amount;

        return $this;
    }

    /**
     * Returns the sum of all reservation amounts for the given BOM entry.
     */
    public function getReservationAmountSum(ProjectBOMEntry $entry): float
    {
        $this->ensureBOMEntryValid($entry);

        $sum = 0;
        foreach ($this->getPartLotsForBOMEntry($entry) as $lot) {
            $sum += $this->getLotReservationAmount($lot);
        }

        if ($entry->getPart() && !$entry->getPart()->useFloatAmount()) {
            $sum = round($sum);
        }

        return $sum;
    }

    /**
     * Returns the available lots to reserve stock from for the given BOM entry.
     * @return PartLot[]|null Returns null if the entry is a non-part BOM entry
     */
    public function getPartLotsForBOMEntry(ProjectBOMEntry $projectBOMEntry): ?array
    {
        $this->ensureBOMEntryValid($projectBOMEntry);

        if (!$projectBOMEntry->getPart() instanceof Part) {
            return null;
        }

        //Filter out all lots which have unknown instock
        return $projectBOMEntry->getPart()->getPartLots()->filter(fn (PartLot $lot) => !$lot->isInstockUnknown())->toArray();
    }

    /**
     * Returns the needed amount of parts for the given BOM entry, based on the number of planned builds.
     */
    public function getNeededAmountForBOMEntry(ProjectBOMEntry $entry): float
    {
        $this->ensureBOMEntryValid($entry);

        return $entry->getQuantity() * $this->number_of_builds;
    }

    /**
     * Returns the list of all bom entries of the project.
     * @return ProjectBOMEntry[]
     */
    public function getBomEntries(): array
    {
        return $this->project->getBomEntries()->toArray();
    }

    /**
     * Returns all part-associated BOM entries of the project (only these need stock reservations).
     * @return ProjectBOMEntry[]
     */
    public function getPartBomEntries(): array
    {
        return $this->project->getBomEntries()->filter(fn (ProjectBOMEntry $entry) => $entry->isPartBomEntry())->toArray();
    }
}
