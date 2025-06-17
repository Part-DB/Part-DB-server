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
namespace App\Services\AssemblySystem;

use App\Entity\AssemblySystem\Assembly;
use App\Entity\AssemblySystem\AssemblyBOMEntry;
use App\Entity\Parts\Part;
use App\Helpers\Assemblies\AssemblyBuildRequest;
use App\Services\Parts\PartLotWithdrawAddHelper;
use App\Services\ProjectSystem\ProjectBuildHelper;

/**
 * @see \App\Tests\Services\AssemblySystem\AssemblyBuildHelperTest
 */
class AssemblyBuildHelper
{
    public function __construct(
        private readonly PartLotWithdrawAddHelper   $withdraw_add_helper,
        private readonly ProjectBuildHelper         $projectBuildHelper
    ) {
    }

    /**
     * Returns the maximum buildable amount of the given BOM entry based on the stock of the used parts.
     * This function only works for BOM entries that are associated with a part.
     */
    public function getMaximumBuildableCountForBOMEntry(AssemblyBOMEntry $assemblyBOMEntry): int
    {
        $part = $assemblyBOMEntry->getPart();

        if (!$part instanceof Part) {
            throw new \InvalidArgumentException('This function cannot determine the maximum buildable count for a BOM entry without a part!');
        }

        if ($assemblyBOMEntry->getQuantity() <= 0) {
            throw new \RuntimeException('The quantity of the BOM entry must be greater than 0!');
        }

        $amount_sum = $part->getAmountSum();

        return (int) floor($amount_sum / $assemblyBOMEntry->getQuantity());
    }

    /**
     * Returns the maximum buildable amount of the given assembly, based on the stock of the used parts in the BOM.
     */
    public function getMaximumBuildableCount(Assembly $assembly): int
    {
        $maximum_buildable_count = PHP_INT_MAX;
        foreach ($assembly->getBomEntries() as $bom_entry) {
            //Skip BOM entries without a part (as we can not determine that)
            if (!$bom_entry->isPartBomEntry() && $bom_entry->getProject() === null) {
                continue;
            }

            //The maximum buildable count for the whole project is the minimum of all BOM entries
            if ($bom_entry->getPart() !== null) {
                $maximum_buildable_count = min($maximum_buildable_count, $this->getMaximumBuildableCountForBOMEntry($bom_entry));
            } elseif ($bom_entry->getProject() !== null) {
                $maximum_buildable_count = min($maximum_buildable_count, $this->projectBuildHelper->getMaximumBuildableCount($bom_entry->getProject()));
            }
        }

        return $maximum_buildable_count;
    }

    /**
     * Checks if the given assembly can be built with the current stock.
     * This means that the maximum buildable count is greater or equal than the requested $number_of_assemblies
     * @param int $number_of_builds
     */
    public function isAssemblyBuildable(Assembly $assembly, int $number_of_builds = 1): bool
    {
        return $this->getMaximumBuildableCount($assembly) >= $number_of_builds;
    }

    /**
     * Check if the given BOM entry can be built with the current stock.
     * This means that the maximum buildable count is greater or equal than the requested $number_of_assemblies
     */
    public function isBOMEntryBuildable(AssemblyBOMEntry $bom_entry, int $number_of_builds = 1): bool
    {
        return $this->getMaximumBuildableCountForBOMEntry($bom_entry) >= $number_of_builds;
    }

    /**
     * Returns the project BOM entries for which parts are missing in the stock for the given number of builds
     * @param  Assembly $assembly The assembly for which the BOM entries should be checked
     * @param  int  $number_of_builds How often should the assembly be build?
     * @return AssemblyBOMEntry[]
     */
    public function getNonBuildableAssemblyBomEntries(Assembly $assembly, int $number_of_builds = 1): array
    {
        if ($number_of_builds < 1) {
            throw new \InvalidArgumentException('The number of builds must be greater than 0!');
        }

        $nonBuildableEntries = [];

        foreach ($assembly->getBomEntries() as $bomEntry) {
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
                $nonBuildableAssemblyEntries = $this->projectBuildHelper->getNonBuildableProjectBomEntries($bomEntry->getProject(), $number_of_builds);
                $nonBuildableEntries = array_merge($nonBuildableEntries, $nonBuildableAssemblyEntries);
            }
        }

        return $nonBuildableEntries;
    }

    /**
     * Withdraw the parts from the stock using the given AssemblyBuildRequest and create the build parts entries, if needed.
     * The AssemblyBuildRequest has to be validated before!!
     * You have to flush changes to DB afterward
     */
    public function doBuild(AssemblyBuildRequest $buildRequest): void
    {
        $message = $buildRequest->getComment();
        $message .= ' (Assembly build: '.$buildRequest->getAssembly()->getName().')';

        foreach ($buildRequest->getPartBomEntries() as $bom_entry) {
            foreach ($buildRequest->getPartLotsForBOMEntry($bom_entry) as $part_lot) {
                $amount = $buildRequest->getLotWithdrawAmount($part_lot);
                if ($amount > 0) {
                    $this->withdraw_add_helper->withdraw($part_lot, $amount, $message);
                }
            }
        }

        if ($buildRequest->getAddBuildsToBuildsPart()) {
            $this->withdraw_add_helper->add($buildRequest->getBuildsPartLot(), $buildRequest->getNumberOfBuilds(), $message);
        }
    }
}
