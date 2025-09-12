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
namespace App\Helpers\Assemblies;

use App\Entity\AssemblySystem\Assembly;
use App\Entity\Parts\Part;

class AssemblyPartAggregator
{
    /**
     * Aggregate the required parts and their total quantities for an assembly.
     *
     * @param Assembly $assembly The assembly to process.
     * @param float $multiplier The quantity multiplier from the parent assembly.
     * @return array Array of parts with their aggregated quantities, keyed by Part ID.
     */
    public function getAggregatedParts(Assembly $assembly, float $multiplier): array
    {
        $aggregatedParts = [];

        // Start processing the assembly recursively
        $this->processAssembly($assembly, $multiplier, $aggregatedParts);

        // Return the final aggregated list of parts
        return $aggregatedParts;
    }

    /**
     * Recursive helper to process an assembly and all its BOM entries.
     *
     * @param Assembly $assembly The current assembly to process.
     * @param float $multiplier The quantity multiplier from the parent assembly.
     * @param array &$aggregatedParts The array to accumulate parts and their quantities.
     */
    private function processAssembly(Assembly $assembly, float $multiplier, array &$aggregatedParts): void
    {
        foreach ($assembly->getBomEntries() as $bomEntry) {
            // If the BOM entry refers to a part, add its quantity
            if ($bomEntry->getPart() instanceof Part) {
                $part = $bomEntry->getPart();

                if (!isset($aggregatedParts[$part->getId()])) {
                    $aggregatedParts[$part->getId()] = [
                        'part' => $part,
                        'assembly' => $assembly,
                        'quantity' => $bomEntry->getQuantity(),
                        'multiplier' => $multiplier,
                    ];
                }
            } elseif ($bomEntry->getReferencedAssembly() instanceof Assembly) {
                // If the BOM entry refers to another assembly, process it recursively
                $this->processAssembly($bomEntry->getReferencedAssembly(), $bomEntry->getQuantity(), $aggregatedParts);
            } else {
                $aggregatedParts[] = [
                    'part' => null,
                    'assembly' => $assembly,
                    'quantity' => $bomEntry->getQuantity(),
                    'multiplier' => $multiplier,
                ];
            }
        }
    }
}
