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
use Doctrine\Common\Collections\Collection;

final class ProjectBuildRequest
{
    private Project $project;
    private int $number_of_builds;

    /**
     * @param  Project  $project  The project that should be build
     * @param  int  $number_of_builds The number of builds that should be created
     */
    public function __construct(Project $project, int $number_of_builds)
    {
        $this->project = $project;
        $this->number_of_builds = $number_of_builds;
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
     * Returns the number of available lots to take stock from for the given BOM entry.
     * @parm ProjectBOMEntry $entry
     * @return PartLot[]|null Returns null if the entry is a non-part BOM entry
     */
    public function getPartLotsForBOMEntry(ProjectBOMEntry $projectBOMEntry): ?array
    {
        $this->ensureBOMEntryValid($projectBOMEntry);

        if ($projectBOMEntry->getPart() === null) {
            return null;
        }

        return $projectBOMEntry->getPart()->getPartLots()->toArray();
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