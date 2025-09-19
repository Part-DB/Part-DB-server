<?php

declare(strict_types=1);

namespace App\Services\InfoProviderSystem;

use App\Entity\Base\AbstractPartsContainingDBElement;
use App\Entity\Parts\Part;
use App\Entity\Parts\Supplier;
use App\Services\InfoProviderSystem\DTOs\BulkSearchResultDTO;
use App\Services\InfoProviderSystem\DTOs\BulkSearchResponseDTO;
use App\Services\InfoProviderSystem\DTOs\FieldMappingDTO;
use App\Services\InfoProviderSystem\DTOs\PartSearchResultDTO;
use App\Services\InfoProviderSystem\DTOs\SearchResultWithMetadataDTO;
use App\Services\InfoProviderSystem\Providers\BatchInfoProviderInterface;
use App\Services\InfoProviderSystem\Providers\InfoProviderInterface;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpClient\Exception\ClientException;

final class BulkInfoProviderService
{
    /** @var array<string, Supplier|null> Cache for normalized supplier names */
    private array $supplierCache = [];

    public function __construct(
        private readonly PartInfoRetriever $infoRetriever,
        private readonly ExistingPartFinder $existingPartFinder,
        private readonly ProviderRegistry $providerRegistry,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger
    ) {}

    /**
     * Perform bulk search across multiple parts and providers.
     *
     * @param Part[] $parts Array of parts to search for
     * @param FieldMappingDTO[] $fieldMappings Array of field mappings defining search strategy
     * @param bool $prefetchDetails Whether to prefetch detailed information for results
     * @return BulkSearchResponseDTO Structured response containing all search results
     * @throws \InvalidArgumentException If no valid parts provided
     * @throws \RuntimeException If no search results found for any parts
     */
    public function performBulkSearch(array $parts, array $fieldMappings, bool $prefetchDetails = false): BulkSearchResponseDTO
    {
        if (empty($parts)) {
            throw new \InvalidArgumentException('No valid parts found for bulk import');
        }

        $partResults = [];
        $hasAnyResults = false;

        // Group providers by batch capability
        $batchProviders = [];
        $regularProviders = [];

        foreach ($fieldMappings as $mapping) {
            foreach ($mapping->providers as $providerKey) {
                if (!is_string($providerKey)) {
                    $this->logger->error('Invalid provider key type', [
                        'providerKey' => $providerKey,
                        'type' => gettype($providerKey)
                    ]);
                    continue;
                }

                $provider = $this->providerRegistry->getProviderByKey($providerKey);
                if ($provider instanceof BatchInfoProviderInterface) {
                    $batchProviders[$providerKey] = $provider;
                } else {
                    $regularProviders[$providerKey] = $provider;
                }
            }
        }

        // Process batch providers first (more efficient)
        $batchResults = $this->processBatchProviders($parts, $fieldMappings, $batchProviders);

        // Process regular providers
        $regularResults = $this->processRegularProviders($parts, $fieldMappings, $regularProviders, $batchResults);

        // Combine and format results for each part
        foreach ($parts as $part) {
            $searchResults = [];

            // Get results from batch and regular processing
            $allResults = array_merge(
                $batchResults[$part->getId()] ?? [],
                $regularResults[$part->getId()] ?? []
            );

            if (!empty($allResults)) {
                $hasAnyResults = true;
                $searchResults = $this->formatSearchResults($allResults);
            }

            $partResults[] = new PartSearchResultDTO(
                part: $part,
                searchResults: $searchResults,
                errors: []
            );
        }

        if (!$hasAnyResults) {
            throw new \RuntimeException('No search results found for any of the selected parts');
        }

        $response = new BulkSearchResponseDTO($partResults);

        // Prefetch details if requested
        if ($prefetchDetails) {
            $this->prefetchDetailsForResults($response);
        }

        return $response;
    }

    /**
     * Process parts using batch-capable info providers.
     *
     * @param Part[] $parts Array of parts to search for
     * @param FieldMappingDTO[] $fieldMappings Array of field mapping configurations
     * @param array<string, BatchInfoProviderInterface> $batchProviders Batch providers indexed by key
     * @return array<int, BulkSearchResultDTO[]> Results indexed by part ID
     */
    private function processBatchProviders(array $parts, array $fieldMappings, array $batchProviders): array
    {
        $batchResults = [];

        foreach ($batchProviders as $providerKey => $provider) {
            $keywords = $this->collectKeywordsForProvider($parts, $fieldMappings, $providerKey);

            if (empty($keywords)) {
                continue;
            }

            try {
                $providerResults = $provider->searchByKeywordsBatch($keywords);

                // Map results back to parts
                foreach ($parts as $part) {
                    foreach ($fieldMappings as $mapping) {
                        if (!in_array($providerKey, $mapping->providers, true)) {
                            continue;
                        }

                        $keyword = $this->getKeywordFromField($part, $mapping->field);
                        if ($keyword && isset($providerResults[$keyword])) {
                            foreach ($providerResults[$keyword] as $dto) {
                                $batchResults[$part->getId()][] = new BulkSearchResultDTO(
                                    baseDto: $dto,
                                    sourceField: $mapping->field,
                                    sourceKeyword: $keyword,
                                    localPart: $this->existingPartFinder->findFirstExisting($dto),
                                    priority: $mapping->priority
                                );
                            }
                        }
                    }
                }
            } catch (\Exception $e) {
                $this->logger->error('Batch search failed for provider ' . $providerKey, [
                    'error' => $e->getMessage(),
                    'provider' => $providerKey
                ]);
            }
        }

        return $batchResults;
    }

    /**
     * Process parts using regular (non-batch) info providers.
     *
     * @param Part[] $parts Array of parts to search for
     * @param FieldMappingDTO[] $fieldMappings Array of field mapping configurations
     * @param array<string, InfoProviderInterface> $regularProviders Regular providers indexed by key
     * @param array<int, BulkSearchResultDTO[]> $excludeResults Results to exclude (from batch processing)
     * @return array<int, BulkSearchResultDTO[]> Results indexed by part ID
     */
    private function processRegularProviders(array $parts, array $fieldMappings, array $regularProviders, array $excludeResults): array
    {
        $regularResults = [];

        foreach ($parts as $part) {
            $regularResults[$part->getId()] = [];

            // Skip if we already have batch results for this part
            if (!empty($excludeResults[$part->getId()] ?? [])) {
                continue;
            }

            foreach ($fieldMappings as $mapping) {
                $providers = array_intersect($mapping->providers, array_keys($regularProviders));

                if (empty($providers)) {
                    continue;
                }

                $keyword = $this->getKeywordFromField($part, $mapping->field);
                if (!$keyword) {
                    continue;
                }

                try {
                    $dtos = $this->infoRetriever->searchByKeyword($keyword, $providers);

                    foreach ($dtos as $dto) {
                        $regularResults[$part->getId()][] = new BulkSearchResultDTO(
                            baseDto: $dto,
                            sourceField: $mapping->field,
                            sourceKeyword: $keyword,
                            localPart: $this->existingPartFinder->findFirstExisting($dto),
                            priority: $mapping->priority
                        );
                    }
                } catch (ClientException $e) {
                    $this->logger->error('Regular search failed', [
                        'part_id' => $part->getId(),
                        'field' => $mapping->field,
                        'error' => $e->getMessage()
                    ]);
                }
            }
        }

        return $regularResults;
    }

    /**
     * Collect unique keywords for a specific provider from all parts and field mappings.
     *
     * @param Part[] $parts Array of parts to collect keywords from
     * @param FieldMappingDTO[] $fieldMappings Array of field mapping configurations
     * @param string $providerKey The provider key to collect keywords for
     * @return string[] Array of unique keywords
     */
    private function collectKeywordsForProvider(array $parts, array $fieldMappings, string $providerKey): array
    {
        $keywords = [];

        foreach ($parts as $part) {
            foreach ($fieldMappings as $mapping) {
                if (!in_array($providerKey, $mapping->providers, true)) {
                    continue;
                }

                $keyword = $this->getKeywordFromField($part, $mapping->field);
                if ($keyword && !in_array($keyword, $keywords, true)) {
                    $keywords[] = $keyword;
                }
            }
        }

        return $keywords;
    }

    private function getKeywordFromField(Part $part, string $field): ?string
    {
        return match ($field) {
            'mpn' => $part->getManufacturerProductNumber(),
            'name' => $part->getName(),
            default => $this->getSupplierPartNumber($part, $field)
        };
    }

    private function getSupplierPartNumber(Part $part, string $field): ?string
    {
        if (!str_ends_with($field, '_spn')) {
            return null;
        }

        $supplierKey = substr($field, 0, -4);
        $supplier = $this->getSupplierByNormalizedName($supplierKey);

        if (!$supplier) {
            return null;
        }

        $orderDetail = $part->getOrderdetails()->filter(
            fn($od) => $od->getSupplier()?->getId() === $supplier->getId()
        )->first();

        return $orderDetail !== false ? $orderDetail->getSupplierpartnr() : null;
    }

    /**
     * Get supplier by normalized name with caching to prevent N+1 queries.
     *
     * @param string $normalizedKey The normalized supplier key to search for
     * @return Supplier|null The matching supplier or null if not found
     */
    private function getSupplierByNormalizedName(string $normalizedKey): ?Supplier
    {
        // Check cache first
        if (isset($this->supplierCache[$normalizedKey])) {
            return $this->supplierCache[$normalizedKey];
        }

        // Use efficient database query with PHP normalization
        // Since DQL doesn't support REPLACE, we'll load all suppliers once and cache the normalization
        if (empty($this->supplierCache)) {
            $this->loadSuppliersIntoCache();
        }

        $supplier = $this->supplierCache[$normalizedKey] ?? null;

        // Cache the result (including null results to prevent repeated queries)
        $this->supplierCache[$normalizedKey] = $supplier;

        return $supplier;
    }

    /**
     * Load all suppliers into cache with normalized names to avoid N+1 queries.
     */
    private function loadSuppliersIntoCache(): void
    {
        /** @var Supplier[] $suppliers */
        $suppliers = $this->entityManager->getRepository(Supplier::class)->findAll();

        foreach ($suppliers as $supplier) {
            $normalizedName = strtolower(str_replace([' ', '-', '_'], '_', $supplier->getName()));
            $this->supplierCache[$normalizedName] = $supplier;
        }
    }

    /**
     * Format and deduplicate search results.
     *
     * @param BulkSearchResultDTO[] $bulkResults Array of bulk search results
     * @return SearchResultWithMetadataDTO[] Array of formatted search results with metadata
     */
    private function formatSearchResults(array $bulkResults): array
    {
        // Sort by priority and remove duplicates
        usort($bulkResults, fn($a, $b) => $a->priority <=> $b->priority);

        $uniqueResults = [];
        $seenKeys = [];

        foreach ($bulkResults as $result) {
            $key = "{$result->getProviderKey()}|{$result->getProviderId()}";
            if (!in_array($key, $seenKeys, true)) {
                $seenKeys[] = $key;
                $uniqueResults[] = new SearchResultWithMetadataDTO(
                    searchResult: $result,
                    localPart: $result->localPart,
                    sourceField: $result->sourceField,
                    sourceKeyword: $result->sourceKeyword
                );
            }
        }

        return $uniqueResults;
    }

    /**
     * Prefetch detailed information for search results.
     *
     * @param BulkSearchResponseDTO|array $searchResults Search results (supports both new DTO and legacy array format)
     */
    public function prefetchDetailsForResults($searchResults): void
    {
        $prefetchCount = 0;

        // Handle both new DTO format and legacy array format for backwards compatibility
        if ($searchResults instanceof BulkSearchResponseDTO) {
            foreach ($searchResults->partResults as $partResult) {
                foreach ($partResult->searchResults as $result) {
                    $dto = $result->searchResult;

                    try {
                        $this->infoRetriever->getDetails($dto->getProviderKey(), $dto->getProviderId());
                        $prefetchCount++;
                    } catch (\Exception $e) {
                        $this->logger->warning('Failed to prefetch details for provider part', [
                            'provider_key' => $dto->getProviderKey(),
                            'provider_id' => $dto->getProviderId(),
                            'error' => $e->getMessage()
                        ]);
                    }
                }
            }
        } else {
            // Legacy array format support
            foreach ($searchResults as $partResult) {
                foreach ($partResult['search_results'] as $result) {
                    $dto = $result['dto'];

                    try {
                        $this->infoRetriever->getDetails($dto->getProviderKey(), $dto->getProviderId());
                        $prefetchCount++;
                    } catch (\Exception $e) {
                        $this->logger->warning('Failed to prefetch details for provider part', [
                            'provider_key' => $dto->getProviderKey(),
                            'provider_id' => $dto->getProviderId(),
                            'error' => $e->getMessage()
                        ]);
                    }
                }
            }
        }

        $this->logger->info("Prefetched details for {$prefetchCount} search results");
    }
}
