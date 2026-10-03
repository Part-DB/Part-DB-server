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
use App\Entity\Parts\PartLot;
use App\Entity\ProjectSystem\PartLotReservation;
use App\Entity\ProjectSystem\PlannedProject;
use App\Entity\ProjectSystem\PlannedProjectStatus;
use App\Entity\ProjectSystem\Project;
use App\Entity\ProjectSystem\ProjectBOMEntry;
use App\Helpers\Projects\BuildPlannedProjectRequest;
use App\Helpers\Projects\PlanProjectRequest;
use App\Helpers\Projects\PlannedProjectPartRow;
use App\Services\Parts\PartLotWithdrawAddHelper;
use Doctrine\ORM\EntityManagerInterface;

/**
 * This service handles the whole lifecycle of a PlannedProject: creating a plan (reserving stock without
 * touching amounts), building it (consuming the reservation) and cancelling it (releasing the reservation).
 *
 * @see \App\Tests\Services\ProjectSystem\ProjectPlanningHelperTest
 */
final readonly class ProjectPlanningHelper
{
    public function __construct(
        private PartLotWithdrawAddHelper $withdrawAddHelper,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Returns the maximum number of times the given BOM entry could be planned for, based on the currently
     * available (i.e. not already reserved) stock of the used part.
     */
    public function getMaximumPlannableCountForBOMEntry(ProjectBOMEntry $projectBOMEntry): int
    {
        $part = $projectBOMEntry->getPart();

        if (!$part instanceof Part) {
            throw new \InvalidArgumentException('This function cannot determine the maximum plannable count for a BOM entry without a part!');
        }

        if ($projectBOMEntry->getQuantity() <= 0) {
            throw new \RuntimeException('The quantity of the BOM entry must be greater than 0!');
        }

        return (int) floor($part->getAvailableAmountSum() / $projectBOMEntry->getQuantity());
    }

    /**
     * Returns the maximum number of times the given project could be planned for, based on the currently
     * available stock of the used parts in the BOM.
     */
    public function getMaximumPlannableCount(Project $project): int
    {
        $bom_entries = $project->getBomEntries();
        if ($bom_entries->isEmpty()) {
            return 0;
        }

        $maximum_plannable_count = PHP_INT_MAX;
        foreach ($bom_entries as $bom_entry) {
            if (!$bom_entry->isPartBomEntry()) {
                continue;
            }
            $maximum_plannable_count = min($maximum_plannable_count, $this->getMaximumPlannableCountForBOMEntry($bom_entry));
        }

        return $maximum_plannable_count;
    }

    public function isProjectPlannable(Project $project, int $number_of_builds = 1): bool
    {
        return $this->getMaximumPlannableCount($project) >= $number_of_builds;
    }

    /**
     * Returns the project BOM entries for which not enough available (non-reserved) stock exists for the
     * given number of planned builds.
     * @return ProjectBOMEntry[]
     */
    public function getNonPlannableProjectBomEntries(Project $project, int $number_of_builds = 1): array
    {
        if ($number_of_builds < 1) {
            throw new \InvalidArgumentException('The number of builds must be greater than 0!');
        }

        $non_plannable_entries = [];

        foreach ($project->getBomEntries() as $bomEntry) {
            $part = $bomEntry->getPart();

            if (!$part instanceof Part) {
                continue;
            }

            if ($part->getAvailableAmountSum() < $bomEntry->getQuantity() * $number_of_builds) {
                $non_plannable_entries[] = $bomEntry;
            }
        }

        return $non_plannable_entries;
    }

    /**
     * Returns how much of the part of the given BOM entry the given planned project can currently draw on: what
     * it already has reserved, plus whatever else is currently available (not reserved by some other planned
     * project). A shortfall between what is reserved and what is needed is not by itself a problem - planning
     * allows partial reservation (not enough available stock at the time of planning), and the plan may since
     * have been "topped up" by restocking, see doBuild().
     */
    private function getUsableAmountForBomEntry(PlannedProject $plannedProject, ProjectBOMEntry $bomEntry, Part $part): float
    {
        //getAvailableAmountSum() already excludes this plan's own reservation for this entry, so adding it back
        //gives the total stock this plan can actually draw on. This stays correct even if the specific lot(s)
        //this plan reserved from lost stock in the meantime (e.g. via a stocktake) - a lot's available amount
        //can go negative in that case, which correctly reduces what is usable here too.
        return $plannedProject->getReservedAmountForBOMEntry($bomEntry) + $part->getAvailableAmountSum();
    }

    /**
     * Returns how much of the given BOM entry's part is still missing to build the given planned project, even
     * counting stock that is available but not (yet) reserved by it. 0 if the entry can currently be provided
     * (or is not a part BOM entry).
     */
    public function getShortfallForBomEntry(PlannedProject $plannedProject, ProjectBOMEntry $bomEntry): float
    {
        $part = $bomEntry->getPart();
        if (!$bomEntry->isPartBomEntry() || !$part instanceof Part) {
            return 0.0;
        }

        $required = $plannedProject->getRequiredAmountForBOMEntry($bomEntry);

        return max(0.0, $required - $this->getUsableAmountForBomEntry($plannedProject, $bomEntry, $part));
    }

    /**
     * Returns the BOM entries of the given planned project's source project that can not currently be built,
     * counting both what this plan already has reserved AND whatever stock is otherwise currently available.
     * An entry only ends up in the returned list if there really is not enough usable stock for it right now,
     * whether because there simply is not enough stock (or no stock lot exists at all), or because a lot's
     * physical stock was reduced below what is reserved on it since (e.g. via a stocktake) and nothing else made
     * up for it. Empty if the plan is currently buildable, or if its source project no longer exists.
     * @return ProjectBOMEntry[]
     */
    public function getMissingBomEntriesForPlan(PlannedProject $plannedProject): array
    {
        $project = $plannedProject->getProject();
        if (!$project instanceof Project) {
            return [];
        }

        $missing = [];
        foreach ($project->getBomEntries() as $bomEntry) {
            if ($this->getShortfallForBomEntry($plannedProject, $bomEntry) > 0) {
                $missing[] = $bomEntry;
            }
        }

        return $missing;
    }

    /**
     * Checks whether the given planned project can currently be built: its reservations plus whatever stock is
     * currently available (not reserved by some other plan) together cover everything its source project's BOM
     * needs for the planned number of builds. See getMissingBomEntriesForPlan().
     */
    public function isFullyReserved(PlannedProject $plannedProject): bool
    {
        return [] === $this->getMissingBomEntriesForPlan($plannedProject);
    }

    /**
     * Returns the parts list of the given planned project: one row per part the plan needs, with the needed
     * amount and the amount reserved (while the plan is planned) or consumed (once it is built), including the
     * lots they come from. Also lists parts that have no stock lot (yet), so they are never hidden from the
     * list just because nothing could be reserved for them.
     * @param  string  $sortBy One of 'name', 'storage', 'needed', 'reserved', 'amount'
     * @return PlannedProjectPartRow[]
     */
    public function getPartRows(PlannedProject $plannedProject, string $sortBy = 'name', bool $ascending = true): array
    {
        if (PlannedProjectStatus::BUILT === $plannedProject->getStatus() && null !== $plannedProject->getBuiltParts()) {
            $rows = $this->getPartRowsFromBuiltSnapshot($plannedProject);
        } else {
            $rows = $this->getPartRowsFromProject($plannedProject);
        }

        $key = static fn (PlannedProjectPartRow $row): string|float => match ($sortBy) {
            'storage' => $row->getSortableStorageLocation(),
            'needed' => $row->needed,
            'reserved' => $row->reserved,
            'amount' => $row->stock ?? -1.0,
            default => $row->partName,
        };

        usort($rows, static function (PlannedProjectPartRow $a, PlannedProjectPartRow $b) use ($key, $ascending): int {
            $a_key = $key($a);
            $b_key = $key($b);

            $result = is_string($a_key) ? strnatcasecmp($a_key, (string) $b_key) : $a_key <=> $b_key;
            if (0 === $result) {
                //Keep the order deterministic for equal values
                $result = strnatcasecmp($a->partName, $b->partName);
            }

            return $ascending ? $result : -$result;
        });

        return $rows;
    }

    /**
     * @return PlannedProjectPartRow[]
     */
    private function getPartRowsFromProject(PlannedProject $plannedProject): array
    {
        $is_planned = PlannedProjectStatus::PLANNED === $plannedProject->getStatus();

        //Normally we list the parts the source project needs. If it does not exist anymore, fall back to the
        //parts that something is reserved for.
        $bom_entries = [];
        $project = $plannedProject->getProject();
        if ($project instanceof Project) {
            $bom_entries = $project->getBomEntries()->filter(static fn (ProjectBOMEntry $entry): bool => $entry->isPartBomEntry())->toArray();
        } else {
            foreach ($plannedProject->getReservations() as $reservation) {
                $bom_entries[spl_object_id($reservation->getBomEntry())] = $reservation->getBomEntry();
            }
        }

        $rows = [];
        foreach ($bom_entries as $bomEntry) {
            $part = $bomEntry->getPart();
            if (!$part instanceof Part) {
                continue;
            }

            $lots = [];
            foreach ($plannedProject->getReservations() as $reservation) {
                if ($reservation->getBomEntry() !== $bomEntry) {
                    continue;
                }
                $lot = $reservation->getPartLot();
                $lots[] = [
                    'name' => $lot->getName(),
                    'storage_location' => $lot->getStorageLocation(),
                    'storage_location_name' => $lot->getStorageLocation()?->getFullPath(),
                    'amount' => $reservation->getAmount(),
                    'over_committed' => $lot->getAvailableAmount() < 0,
                ];
            }

            $rows[] = new PlannedProjectPartRow(
                part: $part,
                partName: $part->getName(),
                bomEntryName: $bomEntry->getName(),
                mountnames: $bomEntry->getMountnames(),
                needed: $plannedProject->getRequiredAmountForBOMEntry($bomEntry),
                reserved: $plannedProject->getReservedAmountForBOMEntry($bomEntry),
                lots: $lots,
                stock: $part->getAmountSum(),
                missing: $is_planned && $this->getShortfallForBomEntry($plannedProject, $bomEntry) > 0,
            );
        }

        return $rows;
    }

    /**
     * @return PlannedProjectPartRow[]
     */
    private function getPartRowsFromBuiltSnapshot(PlannedProject $plannedProject): array
    {
        $snapshot = $plannedProject->getBuiltParts() ?? [];

        //The parts might have been deleted since, so look up which of them still exist (in a single query)
        $part_ids = array_values(array_filter(array_column($snapshot, 'part_id'), static fn (?int $id): bool => null !== $id));
        $parts = [];
        if ([] !== $part_ids) {
            foreach ($this->entityManager->getRepository(Part::class)->findBy(['id' => $part_ids]) as $part) {
                $parts[$part->getID()] = $part;
            }
        }

        $rows = [];
        foreach ($snapshot as $entry) {
            $part = isset($entry['part_id']) ? ($parts[$entry['part_id']] ?? null) : null;

            $lots = [];
            $consumed = 0.0;
            foreach ($entry['lots'] as $lot) {
                $consumed += $lot['amount'];
                $lots[] = [
                    'name' => $lot['name'],
                    'storage_location' => null,
                    'storage_location_name' => $lot['storage_location'],
                    'amount' => $lot['amount'],
                    'over_committed' => false,
                ];
            }

            $rows[] = new PlannedProjectPartRow(
                part: $part,
                partName: $entry['part_name'],
                bomEntryName: $entry['bom_entry_name'],
                mountnames: $entry['mountnames'],
                needed: $entry['needed'],
                reserved: $consumed,
                lots: $lots,
                stock: $part?->getAmountSum(),
                missing: false,
            );
        }

        return $rows;
    }

    /**
     * Creates a new PlannedProject (and its PartLotReservations) from the given, already-validated request.
     * This does not flush changes to DB, you have to do this yourself. No stock amount is ever changed here -
     * only reservations are created.
     */
    public function createPlan(PlanProjectRequest $request): PlannedProject
    {
        $plannedProject = new PlannedProject();
        //setName() is declared on AbstractNamedDBElement (returns self=AbstractNamedDBElement), so it can not
        //be chained together with PlannedProject-specific setters without breaking static return typing.
        $plannedProject->setName($request->getName());
        $plannedProject->setProject($request->getProject())
            ->setComment($request->getComment())
            ->setNumberOfBuilds($request->getNumberOfBuilds())
            ->setStatus(PlannedProjectStatus::PLANNED);

        foreach ($request->getPartBomEntries() as $bomEntry) {
            foreach ($request->getPartLotsForBOMEntry($bomEntry) as $lot) {
                $amount = $request->getLotReservationAmount($lot);
                if ($amount > 0) {
                    $reservation = new PartLotReservation($plannedProject, $bomEntry, $lot, $amount);
                    $plannedProject->addReservation($reservation);
                    $bomEntry->addReservation($reservation);
                    $lot->addReservation($reservation);
                    $this->entityManager->persist($reservation);
                }
            }
        }

        $this->entityManager->persist($plannedProject);

        return $plannedProject;
    }

    /**
     * Releases every reservation of the given planned project, updating all three in-memory collections
     * synchronously (no flush needed in between) so that PartLot::getAvailableAmount() reflects the release
     * immediately.
     */
    private function releaseAllReservations(PlannedProject $plannedProject): void
    {
        //Snapshot to an array first, as we mutate the live Collection while iterating.
        foreach ($plannedProject->getReservations()->toArray() as $reservation) {
            $plannedProject->removeReservation($reservation);
            $reservation->getBomEntry()->removeReservation($reservation);
            $reservation->getPartLot()->removeReservation($reservation);
            $this->entityManager->remove($reservation);
        }
    }

    /**
     * Cancels the given planned project: releases all its reservations (returning the reserved amounts back
     * to "available") and marks it as cancelled. No stock amount is ever changed. You have to flush changes
     * to DB afterward.
     */
    public function cancel(PlannedProject $plannedProject): void
    {
        if (PlannedProjectStatus::PLANNED !== $plannedProject->getStatus()) {
            throw new \RuntimeException('Only planned (not yet built or cancelled) planned projects can be cancelled!');
        }

        $this->releaseAllReservations($plannedProject);
        $plannedProject->setStatus(PlannedProjectStatus::CANCELLED);
    }

    /**
     * Builds the given planned project (via its BuildPlannedProjectRequest): withdraws the full amount its
     * source project's BOM needs for the planned number of builds and marks the plan as BUILT. The plan is kept
     * (it is not deleted) as a record of who planned and built it and what was consumed: the lots actually used
     * are stored on the plan as a snapshot, see PlannedProject::getBuiltParts(). Optionally also adds the
     * finished builds to the project's builds part. You have to flush changes to DB afterward.
     *
     * The withdrawal is re-derived from current stock rather than simply consuming the plan's existing
     * reservations as-is. This is what allows a plan that was only partially reserved at planning time (not
     * enough available stock back then, or no stock lot at all) to still be built once the part has since been
     * restocked - see getMissingBomEntriesForPlan() for the matching feasibility check. The plan's reservations
     * are released first: PartLot::getAvailableAmount() is computed from the in-memory reservations collection,
     * so releasing first means the FIFO allocation below already sees the freed amount - otherwise it would
     * reject the very stock that was reserved for this plan.
     *
     * @throws \RuntimeException if the plan is not in the PLANNED status, or if it can not currently be built
     *                           (see getMissingBomEntriesForPlan()) - building a plan that falls short would
     *                           silently produce fewer completed units than requested, so it is refused
     *                           outright instead.
     */
    public function doBuild(BuildPlannedProjectRequest $buildRequest): void
    {
        $plannedProject = $buildRequest->getPlannedProject();

        if (PlannedProjectStatus::PLANNED !== $plannedProject->getStatus()) {
            throw new \RuntimeException('Only planned (not yet built or cancelled) planned projects can be built!');
        }

        if (!$this->isFullyReserved($plannedProject)) {
            throw new \RuntimeException('This planned project does not have enough parts reserved or available to cover its full bill of materials and can not be built yet!');
        }

        $project = $plannedProject->getProject();
        $message = $buildRequest->getComment() . ' (Planned project build: ' . $plannedProject->getName() . ')';

        $this->releaseAllReservations($plannedProject);

        $built_parts = [];
        if ($project instanceof Project) {
            foreach ($project->getBomEntries() as $bomEntry) {
                $part = $bomEntry->getPart();
                if (!$bomEntry->isPartBomEntry() || !$part instanceof Part) {
                    continue;
                }

                $required = $plannedProject->getRequiredAmountForBOMEntry($bomEntry);
                $used_lots = $required > 0 ? $this->withdrawFromAvailableLots($part, $required, $message) : [];

                $built_parts[] = [
                    'part_id' => $part->getID(),
                    'part_name' => $part->getName(),
                    'bom_entry_name' => $bomEntry->getName(),
                    'mountnames' => $bomEntry->getMountnames(),
                    'needed' => $required,
                    'lots' => array_map(static fn (array $used): array => [
                        'name' => $used['lot']->getName(),
                        'storage_location' => $used['lot']->getStorageLocation()?->getFullPath(),
                        'amount' => $used['amount'],
                    ], $used_lots),
                ];
            }
        }

        if ($buildRequest->getAddBuildsToBuildsPart() && $buildRequest->getBuildsPartLot() instanceof PartLot) {
            $this->withdrawAddHelper->add($buildRequest->getBuildsPartLot(), $plannedProject->getNumberOfBuilds(), $message);
        }

        $plannedProject->setBuiltParts($built_parts);
        $plannedProject->setStatus(PlannedProjectStatus::BUILT);
    }

    /**
     * Withdraws the given amount of the given part from its currently available lots (FIFO across lots, skipping
     * ones with unknown instock - mirrors PlanProjectRequest's allocation order).
     * @return array<int, array{lot: PartLot, amount: float}> The lots that were withdrawn from, with the amounts
     */
    private function withdrawFromAvailableLots(Part $part, float $amount, string $message): array
    {
        $used = [];

        $remaining = $amount;
        foreach ($part->getPartLots() as $lot) {
            if ($remaining <= 0) {
                break;
            }
            if ($lot->isInstockUnknown()) {
                continue;
            }

            $take = min($remaining, $lot->getAvailableAmount());
            if ($take <= 0) {
                continue;
            }

            $this->withdrawAddHelper->withdraw($lot, $take, $message);
            $used[] = ['lot' => $lot, 'amount' => $take];

            $remaining -= $take;
        }

        if ($remaining > 0) {
            //Should not happen - getMissingBomEntriesForPlan() already checked feasibility upfront.
            throw new \RuntimeException('Not enough available stock to build this planned project!');
        }

        return $used;
    }
}
