<?php
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

use App\Entity\Parts\PartLot;
use App\Entity\ProjectSystem\Project;
use App\Entity\ProjectSystem\ProjectBOMEntry;
use App\Validator\Constraints\ProjectSystem\ValidProjectBuildRequest;

/**
 * @ValidProjectBuildRequest()
 */
final class ProjectBuildRequest
{
    private Project $project;
    private int $number_of_builds;

    /**
     * @var array<int, float>
     */
    private array $withdraw_amounts = [];

    /**
     * @param  Project  $project  The project that should be build
     * @param  int  $number_of_builds The number of builds that should be created
     */
    public function __construct(Project $project, int $number_of_builds)
    {
        $this->project = $project;
        $this->number_of_builds = $number_of_builds;

        $this->initializeArray();
    }

    private function initializeArray(): void
    {
        //Completely reset the array
        $this->withdraw_amounts = [];

        //Now create an array for each BOM entry
        foreach ($this->getPartBomEntries() as $bom_entry) {
            $remaining_amount = $this->getNeededAmountForBOMEntry($bom_entry);
            foreach($this->getPartLotsForBOMEntry($bom_entry) as $lot) {
                //If the lot has instock use it for the build
                $this->withdraw_amounts[$lot->getID()] = min($remaining_amount, $lot->getAmount());
                $remaining_amount -= max(0, $this->withdraw_amounts[$lot->getID()]);
            }
        }
    }

    /**
     * Ensure that the projectBOMEntry belongs to the project, otherwise throw an exception.
     * @param  ProjectBOMEntry  $entry
     * @return void
     */
    private function ensureBOMEntryValid(ProjectBOMEntry $entry): void
    {
        if ($entry->getProject() !== $this->project) {
            throw new \InvalidArgumentException('The given BOM entry does not belong to the project!');
        }
    }

    /**
     * Returns the amount of parts that should be withdrawn from the given lot for the corresponding BOM entry.
     * @param PartLot|int $lot The part lot (or the ID of the part lot) for which the withdraw amount should be get
     * @return float
     */
    public function getLotWithdrawAmount($lot): float
    {
        if ($lot instanceof PartLot) {
            $lot_id = $lot->getID();
        } elseif (is_int($lot)) {
            $lot_id = $lot;
        } else {
            throw new \InvalidArgumentException('The given lot must be an instance of PartLot or an ID of a PartLot!');
        }

        if (! array_key_exists($lot_id, $this->withdraw_amounts)) {
            throw new \InvalidArgumentException('The given lot is not in the withdraw amounts array!');
        }

        return $this->withdraw_amounts[$lot_id];
    }

    /**
     * Sets the amount of parts that should be withdrawn from the given lot for the corresponding BOM entry.
     * @param PartLot|int $lot The part lot (or the ID of the part lot) for which the withdraw amount should be get
     * @param  float  $amount
     * @return $this
     */
    public function setLotWithdrawAmount($lot, float $amount): self
    {
        if ($lot instanceof PartLot) {
            $lot_id = $lot->getID();
        } elseif (is_int($lot)) {
            $lot_id = $lot;
        } else {
            throw new \InvalidArgumentException('The given lot must be an instance of PartLot or an ID of a PartLot!');
        }

        $this->withdraw_amounts[$lot_id] = $amount;

        return $this;
    }

    /**
     * Returns the sum of all withdraw amounts for the given BOM entry.
     * @param  ProjectBOMEntry  $entry
     * @return float
     */
    public function getWithdrawAmountSum(ProjectBOMEntry $entry): float
    {
        $this->ensureBOMEntryValid($entry);

        $sum = 0;
        foreach ($this->getPartLotsForBOMEntry($entry) as $lot) {
            $sum += $this->getLotWithdrawAmount($lot);
        }

        if ($entry->getPart() && !$entry->getPart()->useFloatAmount()) {
            $sum = round($sum);
        }

        return $sum;
    }

    /**
     * Returns the number of available lots to take stock from for the given BOM entry.
     * @param ProjectBOMEntry $entry
     * @return PartLot[]|null Returns null if the entry is a non-part BOM entry
     */
    public function getPartLotsForBOMEntry(ProjectBOMEntry $projectBOMEntry): ?array
    {
        $this->ensureBOMEntryValid($projectBOMEntry);

        if ($projectBOMEntry->getPart() === null) {
            return null;
        }

        //Filter out all lots which have unknown instock
        return $projectBOMEntry->getPart()->getPartLots()->filter(fn (PartLot $lot) => !$lot->isInstockUnknown())->toArray();
    }

    /**
     * Returns the needed amount of parts for the given BOM entry.
     * @param  ProjectBOMEntry  $entry
     * @return float
     */
    public function getNeededAmountForBOMEntry(ProjectBOMEntry $entry): float
    {
        $this->ensureBOMEntryValid($entry);

        return $entry->getQuantity() * $this->number_of_builds;
    }

    /**
     * Returns the list of all bom entries that have to be build.
     * @return ProjectBOMEntry[]
     */
    public function getBomEntries(): array
    {
        return $this->project->getBomEntries()->toArray();
    }

    /**
     * Returns the all part bom entries that have to be build.
     * @return ProjectBOMEntry[]
     */
    public function getPartBomEntries(): array
    {
        return $this->project->getBomEntries()->filter(function (ProjectBOMEntry $entry) {
            return $entry->isPartBomEntry();
        })->toArray();
    }

    /**
     * Returns which project should be build
     * @return Project
     */
    public function getProject(): Project
    {
        return $this->project;
    }

    /**
     * Returns the number of builds that should be created.
     * @return int
     */
    public function getNumberOfBuilds(): int
    {
        return $this->number_of_builds;
    }
}