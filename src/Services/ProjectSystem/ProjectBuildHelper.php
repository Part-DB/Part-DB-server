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

use App\Entity\Parts\Part;
use App\Entity\ProjectSystem\Project;
use App\Entity\ProjectSystem\ProjectBOMEntry;
use App\Entity\PriceInformations\Currency;
use App\Helpers\Projects\ProjectBuildRequest;
use App\Services\Parts\PartLotWithdrawAddHelper;
use App\Services\Parts\PricedetailHelper;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * @see \App\Tests\Services\ProjectSystem\ProjectBuildHelperTest
 */
final readonly class ProjectBuildHelper
{
    public function __construct(
        private PartLotWithdrawAddHelper $withdraw_add_helper,
        private PricedetailHelper $pricedetailHelper,
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
        $bom_entries = $project->getBomEntries();
        if ($bom_entries->isEmpty()) {
            return 0;
        }
        $maximum_buildable_count = PHP_INT_MAX;
        foreach ($bom_entries as $bom_entry) {
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
     * Returns the maximum buildable amount of the given project as string, based on the stock of the used parts in the BOM.
     * If the maximum buildable count is infinite, the string '∞' is returned.
     * @param  Project  $project
     * @return string
     */
    public function getMaximumBuildableCountAsString(Project $project): string
    {
        $max_count = $this->getMaximumBuildableCount($project);
        if ($max_count === PHP_INT_MAX) {
            return '∞';
        }
        return (string) $max_count;
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
            if (!$part instanceof Part) {
                continue;
            }

            $amount_sum = $part->getAmountSum();

            if ($amount_sum < $bomEntry->getQuantity() * $number_of_builds) {
                $non_buildable_entries[] = $bomEntry;
            }
        }

        return $non_buildable_entries;
    }

    /**
     * Withdraw the parts from the stock using the given ProjectBuildRequest and create the build parts entries, if needed.
     * The ProjectBuildRequest has to be validated before!!
     * You have to flush changes to DB afterward
     */
    public function doBuild(ProjectBuildRequest $buildRequest): void
    {
        $message = $buildRequest->getComment();
        $message .= ' (Project build: '.$buildRequest->getProject()->getName().')';

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

    /**
     * Calculates the total price to build the given project N times, taking bulk pricing into account.
     * Returns null if no BOM entry has any pricing information.
     */
    public function calculateTotalBuildPrice(Project $project, int $number_of_builds = 1, ?Currency $currency = null): ?BigDecimal
    {
        $total = BigDecimal::zero();
        $has_price = false;

        foreach ($project->getBomEntries() as $entry) {
            $unit_price = $this->getBomEntryUnitPrice($entry, $number_of_builds, $currency);
            if ($unit_price === null) {
                continue;
            }
            $has_price = true;
            $total = $total->plus($unit_price->multipliedBy($entry->getQuantity())->multipliedBy($number_of_builds));
        }

        return $has_price ? $total : null;
    }

    /**
     * Calculates the price to build one unit of the given project when ordering for N builds in total.
     * Returns null if no BOM entry has any pricing information.
     */
    public function calculateUnitBuildPrice(Project $project, int $number_of_builds = 1, ?Currency $currency = null): ?BigDecimal
    {
        $total = $this->calculateTotalBuildPrice($project, $number_of_builds, $currency);
        if ($total === null) {
            return null;
        }
        return $total->dividedBy($number_of_builds, 10, RoundingMode::HALF_UP);
    }

    /**
     * Returns the total build price rounded up to 2 decimal places, ready for display.
     */
    public function roundedTotalBuildPrice(Project $project, int $number_of_builds = 1, ?Currency $currency = null): ?BigDecimal
    {
        return $this->calculateTotalBuildPrice($project, $number_of_builds, $currency)
            ?->toScale(2, RoundingMode::UP);
    }

    /**
     * Returns the unit build price rounded up to 2 decimal places, ready for display.
     */
    public function roundedUnitBuildPrice(Project $project, int $number_of_builds = 1, ?Currency $currency = null): ?BigDecimal
    {
        return $this->calculateUnitBuildPrice($project, $number_of_builds, $currency)
            ?->toScale(2, RoundingMode::UP);
    }

    /**
     * Returns the effective unit price for a single piece of the given BOM entry,
     * taking bulk pricing and minimum order amounts into account for N builds.
     * Returns BigDecimal::zero() when no pricing data is available.
     */
    public function getEntryUnitPrice(ProjectBOMEntry $entry, int $number_of_builds = 1, ?Currency $currency = null): BigDecimal
    {
        return $this->getBomEntryUnitPrice($entry, $number_of_builds, $currency) ?? BigDecimal::zero();
    }

    /**
     * Returns the effective unit price for a single piece of the given BOM entry,
     * taking bulk pricing into account for N builds.
     */
    private function getBomEntryUnitPrice(ProjectBOMEntry $entry, int $number_of_builds, ?Currency $currency): ?BigDecimal
    {
        if ($entry->getPart() instanceof Part) {
            $total_qty = $entry->getQuantity() * $number_of_builds;
            $min_order = $this->pricedetailHelper->getMinOrderAmount($entry->getPart());
            $effective_qty = ($min_order !== null) ? max($total_qty, $min_order) : $total_qty;
            return $this->pricedetailHelper->calculateAvgPrice($entry->getPart(), $effective_qty, $currency);
        }
        return $entry->getPrice();
    }
}
