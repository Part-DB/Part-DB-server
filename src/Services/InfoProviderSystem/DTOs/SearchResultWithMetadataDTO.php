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
 * Represents a search result with additional metadata about how it was found.
 * This DTO encapsulates both the search result data and the context of the search.
 */
readonly class SearchResultWithMetadataDTO
{
    public function __construct(
        /** The search result DTO containing part information from the provider */
        public BulkSearchResultDTO $searchResult,
        /** Local part that matches this search result, if any */
        public ?Part $localPart = null,
        /** The field that was used to find this result (e.g., 'mpn', 'name') */
        public ?string $sourceField = null,
        /** The actual keyword/value that was searched for */
        public ?string $sourceKeyword = null
    ) {}

    /**
     * Create from legacy array format for backwards compatibility.
     * @param array{dto: BulkSearchResultDTO, localPart: ?Part, source_field: string, source_keyword: string} $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            searchResult: $data['dto'],
            localPart: $data['localPart'] ?? null,
            sourceField: $data['source_field'] ?? null,
            sourceKeyword: $data['source_keyword'] ?? null
        );
    }

    /**
     * Convert to legacy array format for backwards compatibility.
     * @return array{dto: BulkSearchResultDTO, localPart: ?Part, source_field: ?string, source_keyword: ?string}
     */
    public function toArray(): array
    {
        return [
            'dto' => $this->searchResult,
            'localPart' => $this->localPart,
            'source_field' => $this->sourceField,
            'source_keyword' => $this->sourceKeyword,
        ];
    }

    /**
     * Get the priority of this search result.
     */
    public function getPriority(): int
    {
        return $this->searchResult->priority;
    }

    /**
     * Get the provider key from the search result.
     */
    public function getProviderKey(): string
    {
        return $this->searchResult->getProviderKey();
    }

    /**
     * Get the provider ID from the search result.
     */
    public function getProviderId(): string
    {
        return $this->searchResult->getProviderId();
    }
}