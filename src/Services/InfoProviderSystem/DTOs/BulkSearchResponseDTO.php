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
use Doctrine\ORM\EntityManagerInterface;

/**
 * Represents the complete response from a bulk info provider search operation.
 * It contains a list of PartSearchResultDTOs, one for each part searched.
 */
readonly class BulkSearchResponseDTO implements \ArrayAccess
{
    /**
     * @param BulkSearchPartResultsDTO[] $partResults Array of search results for each part
     */
    public function __construct(
        public array $partResults
    ) {}

    /**
     * Replaces the search results for a specific part, and returns a new instance.
     * @param  Part|int  $part
     * @param  BulkSearchPartResultsDTO  $new_results
     * @return BulkSearchResponseDTO
     */
    public function replaceResultsForPart(Part|int $part, BulkSearchPartResultsDTO $new_results): self
    {
        $array = $this->partResults;
        foreach ($array as $index => $partResult) {
            if (($part instanceof Part && $partResult->part->getId() === $part->getId()) ||
                ($partResult->part->getId() === $part)) {
                $array[$index] = $new_results;
                break;
            }
        }

        return new self($array);
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
     * @return BulkSearchPartResultsDTO[]
     */
    public function getPartsWithResults(): array
    {
        return array_filter($this->partResults, fn($result) => $result->hasResults());
    }

    /**
     * Get all parts that have errors.
     * @return BulkSearchPartResultsDTO[]
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

    /**
     * Merge multiple BulkSearchResponseDTO instances into one.
     * @param  BulkSearchResponseDTO  ...$responses
     * @return BulkSearchResponseDTO
     */
    public static function merge(BulkSearchResponseDTO ...$responses): BulkSearchResponseDTO
    {
        $mergedResults = [];
        foreach ($responses as $response) {
            foreach ($response->partResults as $partResult) {
                $mergedResults[] = $partResult;
            }
        }
        return new BulkSearchResponseDTO($mergedResults);
    }

    /**
     * Convert this DTO to a serializable representation suitable for storage in the database
     * @return array
     */
    public function toSerializableRepresentation(): array
    {
        $serialized = [];

        foreach ($this->partResults as $partResult) {
            $partData = [
                'part_id' => $partResult->part->getId(),
                'search_results' => [],
                'errors' => $partResult->errors ?? []
            ];

            foreach ($partResult->searchResults as $result) {
                $partData['search_results'][] = [
                    'dto' => $result->searchResult->toNormalizedSearchResultArray(),
                    'source_field' => $result->sourceField ?? null,
                    'source_keyword' => $result->sourceKeyword ?? null,
                    'localPart' => $result->localPart?->getId(),
                    'priority' => $result->priority
                ];
            }

            $serialized[] = $partData;
        }

        return $serialized;
    }

    /**
     * Creates a BulkSearchResponseDTO from a serializable representation.
     * @param  array  $data
     * @param  EntityManagerInterface  $entityManager
     * @return BulkSearchResponseDTO
     * @throws \Doctrine\ORM\Exception\ORMException
     */
    public static function fromSerializableRepresentation(array $data, EntityManagerInterface $entityManager): BulkSearchResponseDTO
    {
        $partResults = [];
        foreach ($data as $partData) {
            $partResults[] = new BulkSearchPartResultsDTO(
                part: $entityManager->getReference(Part::class, $partData['part_id']),
                searchResults: array_map(fn($result) => new BulkSearchPartResultDTO(
                    searchResult: SearchResultDTO::fromNormalizedSearchResultArray($result['dto']),
                    sourceField: $result['source_field'] ?? null,
                    sourceKeyword: $result['source_keyword'] ?? null,
                    localPart: isset($result['localPart']) ? $entityManager->getReference(Part::class, $result['localPart']) : null,
                    priority: $result['priority'] ?? null
                ), $partData['search_results'] ?? []),
                errors: $partData['errors'] ?? []
            );
        }

        return new BulkSearchResponseDTO($partResults);
    }

    public function offsetExists(mixed $offset): bool
    {
        if (!is_int($offset)) {
            throw new \InvalidArgumentException("Offset must be an integer.");
        }
        return isset($this->partResults[$offset]);
    }

    public function offsetGet(mixed $offset): ?BulkSearchPartResultsDTO
    {
        if (!is_int($offset)) {
            throw new \InvalidArgumentException("Offset must be an integer.");
        }
        return $this->partResults[$offset] ?? null;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new \LogicException("BulkSearchResponseDTO is immutable.");
    }

    public function offsetUnset(mixed $offset): void
    {
        throw new \LogicException('BulkSearchResponseDTO is immutable.');
    }
}
