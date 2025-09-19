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

declare(strict_types=1);

namespace App\Services\InfoProviderSystem\DTOs;

/**
 * Represents the complete response from a bulk info provider search operation.
 * This DTO provides type safety and clear structure instead of complex arrays.
 */
readonly class BulkSearchResponseDTO
{
    /**
     * @param PartSearchResultDTO[] $partResults Array of search results for each part
     */
    public function __construct(
        public array $partResults
    ) {}

    /**
     * Create from legacy array format for backwards compatibility.
     * @param array $data Array of part result arrays in legacy format
     */
    public static function fromArray(array $data): self
    {
        $partResults = [];
        foreach ($data as $partData) {
            $partResults[] = PartSearchResultDTO::fromArray($partData);
        }

        return new self($partResults);
    }

    /**
     * Convert to legacy array format for backwards compatibility.
     */
    public function toArray(): array
    {
        $result = [];
        foreach ($this->partResults as $partResult) {
            $result[] = $partResult->toArray();
        }
        return $result;
    }

    /**
     * Check if any parts have search results.
     */
    public function hasAnyResults(): bool
    {
        foreach ($this->partResults as $partResult) {
            if ($partResult->hasResults()) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get the total number of search results across all parts.
     */
    public function getTotalResultCount(): int
    {
        $count = 0;
        foreach ($this->partResults as $partResult) {
            $count += $partResult->getResultCount();
        }
        return $count;
    }

    /**
     * Get all parts that have search results.
     * @return PartSearchResultDTO[]
     */
    public function getPartsWithResults(): array
    {
        return array_filter($this->partResults, fn($result) => $result->hasResults());
    }

    /**
     * Get all parts that have errors.
     * @return PartSearchResultDTO[]
     */
    public function getPartsWithErrors(): array
    {
        return array_filter($this->partResults, fn($result) => $result->hasErrors());
    }

    /**
     * Get the number of parts processed.
     */
    public function getPartCount(): int
    {
        return count($this->partResults);
    }

    /**
     * Get the number of parts with successful results.
     */
    public function getSuccessfulPartCount(): int
    {
        return count($this->getPartsWithResults());
    }
}