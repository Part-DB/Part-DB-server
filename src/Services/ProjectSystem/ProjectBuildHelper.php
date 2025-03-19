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
namespace App\Services\ProjectSystem;

use App\Entity\AssemblySystem\AssemblyBOMEntry;
use App\Entity\Parts\Part;
use App\Entity\ProjectSystem\Project;
use App\Entity\ProjectSystem\ProjectBOMEntry;
use App\Helpers\Assemblies\AssemblyBuildRequest;
use App\Helpers\Projects\ProjectBuildRequest;
use App\Services\AssemblySystem\AssemblyBuildHelper;
use App\Services\Parts\PartLotWithdrawAddHelper;

/**
 * @see \App\Tests\Services\ProjectSystem\ProjectBuildHelperTest
 */
class ProjectBuildHelper
{
    public function __construct(
        private readonly PartLotWithdrawAddHelper $withdrawAddHelper,
        private readonly AssemblyBuildHelper      $assemblyBuildHelper
    ) {
    }

    /**
     * Returns the maximum buildable amount of the given BOM entry based on the stock of the used parts.
     * This function only works for BOM entries that are associated with a part.
     */
    public function getMaximumBuildableCountForBOMEntry(ProjectBOMEntry $projectBOMEntry): int
    {
        $part = $projectBOMEntry->getPart();

        if (!$part instanceof Part) {
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
     */
    public function getMaximumBuildableCount(Project $project): int
    {
        $maximum_buildable_count = PHP_INT_MAX;
        foreach ($project->getBomEntries() as $bom_entry) {
            //Skip BOM entries without a part (as we can not determine that)
            if (!$bom_entry->isPartBomEntry() && $bom_entry->getAssembly() === null) {
                continue;
            }

            //The maximum buildable count for the whole project is the minimum of all BOM entries
            if ($bom_entry->getPart() !== null) {
                $maximum_buildable_count = min($maximum_buildable_count, $this->getMaximumBuildableCountForBOMEntry($bom_entry));
            } elseif ($bom_entry->getAssembly() !== null) {
                $maximum_buildable_count = min($maximum_buildable_count, $this->assemblyBuildHelper->getMaximumBuildableCount($bom_entry->getAssembly()));
            }
        }

        return $maximum_buildable_count;
    }

    /**
     * Checks if the given project can be built with the current stock.
     * This means that the maximum buildable count is greater or equal than the requested $number_of_projects
     * @param int $number_of_builds
     */
    public function isProjectBuildable(Project $project, int $number_of_builds = 1): bool
    {
        return $this->getMaximumBuildableCount($project) >= $number_of_builds;
    }

    /**
     * Check if the given BOM entry can be built with the current stock.
     * This means that the maximum buildable count is greater or equal than the requested $number_of_projects
     */
    public function isBOMEntryBuildable(ProjectBOMEntry $bom_entry, int $number_of_builds = 1): bool
    {
        return $this->getMaximumBuildableCountForBOMEntry($bom_entry) >= $number_of_builds;
    }

    /**
     * Returns the project or assembly BOM entries for which parts are missing in the stock for the given number of builds
     * @param  Project  $project The project for which the BOM entries should be checked
     * @param  int  $number_of_builds How often should the project be build?
     * @return ProjectBOMEntry[]|AssemblyBOMEntry[]
     */
    public function getNonBuildableProjectBomEntries(Project $project, int $number_of_builds = 1): array
    {
        if ($number_of_builds < 1) {
            throw new \InvalidArgumentException('The number of builds must be greater than 0!');
        }

        $nonBuildableEntries = [];

        foreach ($project->getBomEntries() as $bomEntry) {
            $part = $bomEntry->getPart();

            //Skip BOM entries without a part (as we can not determine that)
            if (!$part instanceof Part && $bomEntry->getAssembly() === null) {
                continue;
            }

            if ($bomEntry->getPart() !== null) {
                $amount_sum = $part->getAmountSum();

                if ($amount_sum < $bomEntry->getQuantity() * $number_of_builds) {
                    $nonBuildableEntries[] = $bomEntry;
                }
            } elseif ($bomEntry->getAssembly() !== null) {
                $nonBuildableAssemblyEntries = $this->assemblyBuildHelper->getNonBuildableAssemblyBomEntries($bomEntry->getAssembly(), $number_of_builds);
                $nonBuildableEntries = array_merge($nonBuildableEntries, $nonBuildableAssemblyEntries);
            }
        }

        return $nonBuildableEntries;
    }

    /**
     * Withdraw the parts from the stock using the given ProjectBuildRequest and create the build parts entries, if needed.
     * The ProjectBuildRequest has to be validated before!!
     * You have to flush changes to DB afterward
     */
    public function doBuild(ProjectBuildRequest $projectBuildRequest): void
    {
        $message = $projectBuildRequest->getComment();
        $message .= ' (Project build: '.$projectBuildRequest->getProject()->getName().')';

        foreach ($projectBuildRequest->getPartBomEntries() as $bomEntry) {
            foreach ($projectBuildRequest->getPartLotsForBOMEntry($bomEntry) as $partLot) {
                $amount = $projectBuildRequest->getLotWithdrawAmount($partLot);
                if ($amount > 0) {
                    $this->withdrawAddHelper->withdraw($partLot, $amount, $message);
                }
            }
        }

        foreach ($projectBuildRequest->getAssemblyBomEntries() as $bomEntry) {
            $assemblyBuildRequest = new AssemblyBuildRequest($bomEntry->getAssembly(), $projectBuildRequest->getNumberOfBuilds());

            //Add fields for assembly bom entries
            foreach ($assemblyBuildRequest->getPartBomEntries() as $partBomEntry) {
                foreach ($assemblyBuildRequest->getPartLotsForBOMEntry($partBomEntry) as $partLot) {
                    //Read amount from build configuration of the projectBuildRequest
                    $amount = $projectBuildRequest->getLotWithdrawAmount($partLot);
                    if ($amount > 0) {
                        $this->withdrawAddHelper->withdraw($partLot, $amount, $message);
                    }
                }
            }
        }

        if ($projectBuildRequest->getAddBuildsToBuildsPart()) {
            $this->withdrawAddHelper->add($projectBuildRequest->getBuildsPartLot(), $projectBuildRequest->getNumberOfBuilds(), $message);
        }
    }
}
