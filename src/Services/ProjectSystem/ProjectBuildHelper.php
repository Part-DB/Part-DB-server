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

namespace App\Services\ProjectSystem;

use App\Entity\ProjectSystem\Project;
use App\Entity\ProjectSystem\ProjectBOMEntry;

class ProjectBuildHelper
{

    /**
     * Returns the maximum buildable amount of the given BOM entry based on the stock of the used parts.
     * This function only works for BOM entries that are associated with a part.
     * @param  ProjectBOMEntry  $projectBOMEntry
     * @return int
     */
    public function getMaximumBuildableCountForBOMEntry(ProjectBOMEntry $projectBOMEntry): int
    {
        $part = $projectBOMEntry->getPart();

        if ($part === null) {
            throw new \InvalidArgumentException('This function cannot determine the maximum buildable count for a BOM entry without a part!');
        }

        if ($projectBOMEntry->getQuantity() <= 0) {
            throw new \RuntimeException('The quantity of the BOM entry must be greater than 0!');
        }

        $amount_sum = $part->getAmountSum();

        return (int) floor($amount_sum / $projectBOMEntry->getQuantity());
    }

    /**
     * Returns the maximum buildable amount of the given project, based on the stock of the used parts in the BOM.
     * @param  Project  $project
     * @return int
     */
    public function getMaximumBuildableCount(Project $project): int
    {
        $maximum_buildable_count = PHP_INT_MAX;
        foreach ($project->getBOMEntries() as $bom_entry) {
            //Skip BOM entries without a part (as we can not determine that)
            if (!$bom_entry->isPartBomEntry()) {
                continue;
            }

            //The maximum buildable count for the whole project is the minimum of all BOM entries
            $maximum_buildable_count = min($maximum_buildable_count, $this->getMaximumBuildableCountForBOMEntry($bom_entry));
        }

        return $maximum_buildable_count;
    }

    /**
     * Checks if the given project can be build with the current stock.
     * This means that the maximum buildable count is greater or equal than the requested $number_of_projects
     * @param  Project  $project
     * @parm int $number_of_builds
     * @return bool
     */
    public function isProjectBuildable(Project $project, int $number_of_builds = 1): bool
    {
        return $this->getMaximumBuildableCount($project) >= $number_of_builds;
    }

    /**
     * Check if the given BOM entry can be build with the current stock.
     * This means that the maximum buildable count is greater or equal than the requested $number_of_projects
     * @param  ProjectBOMEntry  $bom_entry
     * @param  int  $number_of_builds
     * @return bool
     */
    public function isBOMEntryBuildable(ProjectBOMEntry $bom_entry, int $number_of_builds = 1): bool
    {
        return $this->getMaximumBuildableCountForBOMEntry($bom_entry) >= $number_of_builds;
    }

    /**
     * Returns the project BOM entries for which parts are missing in the stock for the given number of builds
     * @param  Project  $project The project for which the BOM entries should be checked
     * @param  int  $number_of_builds How often should the project be build?
     * @return ProjectBOMEntry[]
     */
    public function getNonBuildableProjectBomEntries(Project $project, int $number_of_builds = 1): array
    {
        if ($number_of_builds < 1) {
            throw new \InvalidArgumentException('The number of builds must be greater than 0!');
        }

        $non_buildable_entries = [];

        foreach ($project->getBomEntries() as $bomEntry) {
            $part = $bomEntry->getPart();

            //Skip BOM entries without a part (as we can not determine that)
            if ($part === null) {
                continue;
            }

            $amount_sum = $part->getAmountSum();

            if ($amount_sum < $bomEntry->getQuantity() * $number_of_builds) {
                $non_buildable_entries[] = $bomEntry;
            }
        }

        return $non_buildable_entries;
    }
}