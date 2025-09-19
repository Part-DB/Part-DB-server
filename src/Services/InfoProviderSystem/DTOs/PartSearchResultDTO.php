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

use App\Entity\Parts\Part;

/**
 * Represents the search results for a single part from bulk info provider search.
 * This DTO provides type safety and clear structure for part search results.
 */
readonly class PartSearchResultDTO
{
    /**
     * @param Part $part The part that was searched for
     * @param SearchResultWithMetadataDTO[] $searchResults Array of search results found for this part
     * @param string[] $errors Array of error messages encountered during search
     */
    public function __construct(
        public Part $part,
        public array $searchResults = [],
        public array $errors = []
    ) {}

    /**
     * Create from legacy array format for backwards compatibility.
     * @param array{part: Part, search_results: array, errors: string[]} $data
     */
    public static function fromArray(array $data): self
    {
        $searchResults = [];
        foreach ($data['search_results'] as $result) {
            $searchResults[] = SearchResultWithMetadataDTO::fromArray($result);
        }

        return new self(
            part: $data['part'],
            searchResults: $searchResults,
            errors: $data['errors'] ?? []
        );
    }

    /**
     * Convert to legacy array format for backwards compatibility.
     * @return array{part: Part, search_results: array, errors: string[]}
     */
    public function toArray(): array
    {
        $searchResults = [];
        foreach ($this->searchResults as $result) {
            $searchResults[] = $result->toArray();
        }

        return [
            'part' => $this->part,
            'search_results' => $searchResults,
            'errors' => $this->errors,
        ];
    }

    /**
     * Check if this part has any search results.
     */
    public function hasResults(): bool
    {
        return !empty($this->searchResults);
    }

    /**
     * Check if this part has any errors.
     */
    public function hasErrors(): bool
    {
        return !empty($this->errors);
    }

    /**
     * Get the number of search results for this part.
     */
    public function getResultCount(): int
    {
        return count($this->searchResults);
    }

    /**
     * Get search results sorted by priority (ascending).
     * @return SearchResultWithMetadataDTO[]
     */
    public function getResultsSortedByPriority(): array
    {
        $results = $this->searchResults;
        usort($results, fn($a, $b) => $a->getPriority() <=> $b->getPriority());
        return $results;
    }
}