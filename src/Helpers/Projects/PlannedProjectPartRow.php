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
use App\Entity\Parts\StorageLocation;

/**
 * One line of the parts list of a planned build: a part the planned build needs, how much of it is needed, and
 * how much is reserved (while the plan is still planned) or was consumed (once it has been built).
 *
 * @see \App\Services\ProjectSystem\ProjectPlanningHelper::getPartRows()
 */
final readonly class PlannedProjectPartRow
{
    /**
     * @param  Part|null  $part The part, or null if it does not exist anymore (only possible for built plans)
     * @param  string  $partName The name of the part (kept separately, as the part itself might not exist anymore)
     * @param  string|null  $bomEntryName The name of the BOM entry, if it has one
     * @param  string  $mountnames The mountnames of the BOM entry
     * @param  float  $needed The amount that is needed for the planned number of builds
     * @param  float  $reserved The amount that is reserved for this plan (if still planned) or that was consumed (if built)
     * @param  array<int, array{name: string, storage_location: StorageLocation|null, storage_location_name: string|null, amount: float, over_committed: bool}>  $lots The lots the reserved/consumed amount comes from. The storage location is only available as an entity while the lot still exists (planned plans), the name is always given
     * @param  float|null  $stock The current total stock of the part, or null if the part does not exist anymore
     * @param  bool  $missing True if the part can currently not be provided in the needed amount (only for planned plans)
     */
    public function __construct(
        public ?Part $part,
        public string $partName,
        public ?string $bomEntryName,
        public string $mountnames,
        public float $needed,
        public float $reserved,
        public array $lots,
        public ?float $stock,
        public bool $missing,
    ) {
    }

    /**
     * The (first) storage location name, used to sort by storage location.
     */
    public function getSortableStorageLocation(): string
    {
        foreach ($this->lots as $lot) {
            if (null !== $lot['storage_location_name'] && '' !== $lot['storage_location_name']) {
                return $lot['storage_location_name'];
            }
        }

        return '';
    }
}
