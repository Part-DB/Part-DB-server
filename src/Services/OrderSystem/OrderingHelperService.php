<?php

declare(strict_types=1);

/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2026 Jan Böhmer (https://github.com/jbtronics)
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

namespace App\Services\OrderSystem;

use App\Entity\OrderSystem\Order;
use App\Entity\OrderSystem\OrderItem;
use App\Entity\Parts\Part;
use App\Entity\ProjectSystem\Project;
use App\Entity\ProjectSystem\ProjectBOMEntry;

/**
 * Computes the list of parts that need to be ordered to build a given set of projects.
 *
 * The computation subtracts existing stock from the required quantities, then merges
 * duplicate parts across all selected projects.
 */
final class OrderingHelperService
{
    /**
     * Computes a list of OrderItems needed to build all given projects at their specified build counts.
     *
     * @param array<array{project: Project, build_count: int}> $projectBuildRequests
     * @return OrderItem[]
     */
    public function computeNeededItems(array $projectBuildRequests): array
    {
        // Accumulate needed quantities by Part ID
        /** @var array<int, float> $neededByPartId */
        $neededByPartId = [];
        /** @var array<int, Part> $partsById */
        $partsById = [];

        foreach ($projectBuildRequests as $request) {
            $project = $request['project'];
            $buildCount = max(1, (int) $request['build_count']);

            foreach ($project->getBomEntries() as $bomEntry) {
                if (!$bomEntry instanceof ProjectBOMEntry) {
                    continue;
                }
                $part = $bomEntry->getPart();
                if (!$part instanceof Part) {
                    // Non-part BOM entries (notes) are skipped
                    continue;
                }

                $needed = $bomEntry->getQuantity() * $buildCount;
                $inStock = max(0.0, $part->getAmountSum());
                $toOrder = max(0.0, $needed - $inStock);

                if ($toOrder <= 0) {
                    continue;
                }

                $partId = $part->getID();
                if (!isset($neededByPartId[$partId])) {
                    $neededByPartId[$partId] = 0.0;
                    $partsById[$partId] = $part;
                }
                $neededByPartId[$partId] += $toOrder;
            }
        }

        // Convert to OrderItems
        $items = [];
        foreach ($neededByPartId as $partId => $qty) {
            $part = $partsById[$partId];
            $item = new OrderItem();
            $item->setPart($part);
            $item->setName($part->getName());
            $item->setQuantity($qty);

            // Pre-populate supplier + SKU from the first non-obsolete Orderdetail
            foreach ($part->getOrderdetails(true) as $orderdetail) {
                $item->setSupplier($orderdetail->getSupplier());
                $item->setSupplierPartNr($orderdetail->getSupplierPartNr());
                break;
            }

            $items[] = $item;
        }

        // Sort by part name for consistent output
        usort($items, static fn(OrderItem $a, OrderItem $b) => strcmp($a->getName(), $b->getName()));

        return $items;
    }

    /**
     * Creates a new Order populated with the computed items.
     *
     * @param array<array{project: Project, build_count: int}> $projectBuildRequests
     */
    public function createOrderFromProjects(array $projectBuildRequests, string $name): Order
    {
        $order = new Order();
        $order->setName($name);

        foreach ($this->computeNeededItems($projectBuildRequests) as $item) {
            $order->addItem($item);
        }

        return $order;
    }
}
