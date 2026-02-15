<?php

declare(strict_types=1);

/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2024 Jan Böhmer (https://github.com/jbtronics)
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

namespace App\DataTables\DTO;

use App\Entity\Parts\Part;
use App\Entity\Parts\PartLot;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;

/**
 * Builds PartDTO objects from database query results.
 * Handles the hydration of lightweight DTOs instead of full Part entities.
 */
class PartDTOHydrator
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Build PartDTO objects from a query result set.
     * Expects results from the optimized detail query that selects specific fields.
     *
     * @param array $queryResults Array of results from the detail query
     * @return PartDTO[]
     */
    public function hydrateFromQueryResults(array $queryResults): array
    {
        $dtos = [];
        $partLotsGrouped = [];
        $attachmentsGrouped = [];
        $projectsGrouped = [];

        // First pass: Group related data by part ID
        foreach ($queryResults as $row) {
            $partId = $row['id'];

            // Group part lots by part ID
            if (isset($row['partLot_id']) && $row['partLot_id'] !== null) {
                if (!isset($partLotsGrouped[$partId])) {
                    $partLotsGrouped[$partId] = [];
                }
                $lotKey = $row['partLot_id'];
                if (!isset($partLotsGrouped[$partId][$lotKey])) {
                    $partLotsGrouped[$partId][$lotKey] = new PartLotDTO(
                        id: $row['partLot_id'],
                        storage_location_id: $row['storageLocation_id'] ?? null,
                        storage_location_name: $row['storageLocation_name'] ?? null,
                        storage_location_fullPath: $row['storageLocation_fullPath'] ?? null,
                    );
                }
            }

            // Group attachments by part ID
            if (isset($row['attachment_id']) && $row['attachment_id'] !== null) {
                if (!isset($attachmentsGrouped[$partId])) {
                    $attachmentsGrouped[$partId] = [];
                }
                $attachmentsGrouped[$partId][$row['attachment_id']] = $row['attachment_id'];
            }

            // Group projects by part ID
            if (isset($row['project_id']) && $row['project_id'] !== null) {
                if (!isset($projectsGrouped[$partId])) {
                    $projectsGrouped[$partId] = [];
                }
                $projectKey = $row['project_id'];
                if (!isset($projectsGrouped[$partId][$projectKey])) {
                    $projectsGrouped[$partId][$projectKey] = [
                        'id' => $row['project_id'],
                        'name' => $row['project_name'] ?? '',
                    ];
                }
            }
        }

        // Second pass: Create DTOs (one per part, using first row's data)
        $processedParts = [];
        foreach ($queryResults as $row) {
            $partId = $row['id'];

            // Skip if we've already processed this part
            if (isset($processedParts[$partId])) {
                continue;
            }
            $processedParts[$partId] = true;

            $dto = new PartDTO(
                id: $row['id'],
                name: $row['name'],
                ipn: $row['ipn'] ?? null,
                description: $row['description'] ?? null,
                minamount: $row['minamount'] ?? 0.0,
                manufacturer_product_number: $row['manufacturer_product_number'] ?? null,
                mass: $row['mass'] ?? null,
                gtin: $row['gtin'] ?? null,
                tags: $row['tags'] ?? '',
                favorite: $row['favorite'] ?? false,
                needs_review: $row['needs_review'] ?? false,
                addedDate: $row['addedDate'] ?? null,
                lastModified: $row['lastModified'] ?? null,
                manufacturing_status: $row['manufacturing_status'] ?? null,
                category_id: $row['category_id'] ?? null,
                category_name: $row['category_name'] ?? null,
                footprint_id: $row['footprint_id'] ?? null,
                footprint_name: $row['footprint_name'] ?? null,
                manufacturer_id: $row['manufacturer_id'] ?? null,
                manufacturer_name: $row['manufacturer_name'] ?? null,
                partUnit_id: $row['partUnit_id'] ?? null,
                partUnit_name: $row['partUnit_name'] ?? null,
                partUnit_unit: $row['partUnit_unit'] ?? null,
                partCustomState_id: $row['partCustomState_id'] ?? null,
                partCustomState_name: $row['partCustomState_name'] ?? null,
                master_picture_attachment_id: $row['master_picture_attachment_id'] ?? null,
                master_picture_attachment_filename: $row['master_picture_attachment_filename'] ?? null,
                master_picture_attachment_name: $row['master_picture_attachment_name'] ?? null,
                footprint_attachment_id: $row['footprint_attachment_id'] ?? null,
                builtProject_id: $row['builtProject_id'] ?? null,
                builtProject_name: $row['builtProject_name'] ?? null,
                amountSum: $row['amountSum'] ?? 0.0,
                expiredAmountSum: $row['expiredAmountSum'] ?? 0.0,
                hasUnknownAmount: $row['hasUnknownAmount'] ?? false,
            );

            // Attach grouped data
            if (isset($partLotsGrouped[$partId])) {
                $dto->setPartLots(array_values($partLotsGrouped[$partId]));
            }
            if (isset($attachmentsGrouped[$partId])) {
                $dto->setAttachments(array_values($attachmentsGrouped[$partId]));
            }
            if (isset($projectsGrouped[$partId])) {
                $dto->setProjects(array_values($projectsGrouped[$partId]));
            }

            $dtos[] = $dto;
        }

        return $dtos;
    }
}
