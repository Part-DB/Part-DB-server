<?php

declare(strict_types=1);

namespace App\Services\InfoProviderSystem;

use App\Entity\Parts\Part;
use App\Entity\Parts\Supplier;
use App\Services\InfoProviderSystem\DTOs\BulkSearchPartResultDTO;
use App\Services\InfoProviderSystem\DTOs\BulkSearchResponseDTO;
use App\Services\InfoProviderSystem\DTOs\BulkSearchFieldMappingDTO;
use App\Services\InfoProviderSystem\DTOs\BulkSearchPartResultsDTO;
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
     * @param BulkSearchFieldMappingDTO[] $fieldMappings Array of field mappings defining search strategy
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
        
        $this->logger->info("BulkInfoProvider: Starting bulk search for " . count($parts) . " parts.");

        $partResults = [];
        $hasAnyResults = false;

        // Group providers by batch capability
        $batchProviders = [];
        $regularProviders = [];

        // [INICIO DE PARCHE 11 - Lógica de limpieza de clave]
        foreach ($fieldMappings as $mapping) {
            foreach ($mapping->providers as $providerKey) {

                // Lógica de limpieza de claves de proveedor
                if (!is_string($providerKey)) {
                    if (is_array($providerKey) || is_object($providerKey)) {
                        // 1. Extrae la clave del array (puede tener null bytes, ej: \0App\...\LCSCProvider\0)
                        $classNameKey = array_key_first((array) $providerKey);

                        if (is_string($classNameKey)) {

                            // 2. ¡¡¡LIMPIAR NULL BYTES!!!
                            $cleanedKey = str_replace("\0", '', $classNameKey);

                            // 3. Usa Regex en la clave limpia para extraer el *último* nombre de la clase
                            //    El '.*\\\' busca de forma codiciosa hasta la última barra invertida.
                            if (preg_match('/.*\\\\([\w]+Provider)/', $cleanedKey, $matches)) {
                                // $matches[1] ahora SÍ será "LCSCProvider"
                                $className = $matches[1];

                                // 4. Convierte "LCSCProvider" a "lcsc"
                                $newKey = strtolower(str_replace('Provider', '', $className));
                                $this->logger->info("BulkInfoProvider: Converted provider key '{$classNameKey}' to '{$newKey}'");
                                $providerKey = $newKey; // $providerKey ahora es un string
                            } else {
                                $this->logger->warning("BulkInfoProvider: Regex failed to match on cleaned key", ['key' => $cleanedKey]);
                            }
                        }
                    }
                }
                // Fin de la lógica de limpieza

                // Si después de la limpieza aún no es una cadena, ahora sí es un error
                if (!is_string($providerKey)) {
                    $this->logger->error('Invalid provider key type AFTER cleanup', [
                        'providerKey' => $providerKey,
                        'type' => gettype($providerKey)
                    ]);
                    continue; // Saltar este proveedor mal formado
                }

                $provider = $this->providerRegistry->getProviderByKey($providerKey);
                if ($provider instanceof BatchInfoProviderInterface) {
                    $batchProviders[$providerKey] = $provider;
                } else {
                    $regularProviders[$providerKey] = $provider;
                }
            }
        }
        // [FIN DE PARCHE 11]

        $this->logger->info("BulkInfoProvider: Identified " . count($batchProviders) . " batch providers and " . count($regularProviders) . " regular providers.");
        if (!empty($batchProviders)) {
            $this->logger->debug("BulkInfoProvider: Batch providers found: " . implode(', ', array_keys($batchProviders)));
        }
        if (!empty($regularProviders)) {
            $this->logger->debug("BulkInfoProvider: Regular providers found: " . implode(', ', array_keys($regularProviders)));
        }

        // Process batch providers first (more efficient)
        $batchResults = $this->processBatchProviders($parts, $fieldMappings, $batchProviders);
        $this->logger->info("BulkInfoProvider: Completed batch processing. Batch results count: " . count($batchResults));


        // Process regular providers
        $regularResults = $this->processRegularProviders($parts, $fieldMappings, $regularProviders, $batchResults);
        $this->logger->info("BulkInfoProvider: Completed regular processing. Regular results count: " . count($regularResults));

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

            $partResults[] = new BulkSearchPartResultsDTO(
                part: $part,
                searchResults: $searchResults,
                errors: []
            );
        }

        if (!$hasAnyResults) {
            $this->logger->warning("BulkInfoProvider: No search results found for any parts after processing.");
            throw new \RuntimeException('No search results found for any of the selected parts');
        }

        $response = new BulkSearchResponseDTO($partResults);

        // Prefetch details if requested
        if ($prefetchDetails) {
            $this->prefetchDetailsForResults($response);
        }
        
        $this->logger->info("BulkInfoProvider: Bulk search completed successfully with results.");
        return $response;
    }
    
    /**
     * [INICIO PARCHE 11]
     * Helper function to check for provider key match, handling corrupted keys.
     */
    private function providerKeyMatches(string $cleanedKey, array $providerList): bool
    {
        foreach ($providerList as $providerKey) {
            if (is_string($providerKey)) {
                if ($providerKey === $cleanedKey) {
                    return true;
                }
                continue;
            }
            
            // Start of our cleaning logic
            if (is_array($providerKey) || is_object($providerKey)) {
                $classNameKey = array_key_first((array) $providerKey);
                if (is_string($classNameKey)) {
                    $cleanedListKey = str_replace("\0", '', $classNameKey);
                    if (preg_match('/.*\\\\([\w]+Provider)/', $cleanedListKey, $matches)) {
                        $className = $matches[1];
                        $newKey = strtolower(str_replace('Provider', '', $className));
                        if ($newKey === $cleanedKey) {
                            return true;
                        }
                    }
                }
            }
        }
        return false;
    }
    // [FIN PARCHE 11]

    /**
     * Process parts using batch-capable info providers.
     *
     * @param Part[] $parts Array of parts to search for
     * @param BulkSearchFieldMappingDTO[] $fieldMappings Array of field mapping configurations
     * @param array<string, BatchInfoProviderInterface> $batchProviders Batch providers indexed by key
     * @return array<int, BulkSearchPartResultDTO[]> Results indexed by part ID
     */
    private function processBatchProviders(array $parts, array $fieldMappings, array $batchProviders): array
    {
        $batchResults = [];

        foreach ($batchProviders as $providerKey => $provider) {
            $keywords = $this->collectKeywordsForProvider($parts, $fieldMappings, $providerKey);
            $this->logger->info("BulkInfoProvider: processBatchProviders: Collected " . count($keywords) . " keywords for provider '{$providerKey}'");

            if (empty($keywords)) {
                continue;
            }
            
            $this->logger->debug("BulkInfoProvider: processBatchProviders: Keywords for '{$providerKey}':", $keywords);

            try {
                $providerResults = $provider->searchByKeywordsBatch($keywords);
                $this->logger->info("BulkInfoProvider: processBatchProviders: Received " . count($providerResults) . " results from '{$providerKey}' batch search.");

                // Map results back to parts
                foreach ($parts as $part) {
                    foreach ($fieldMappings as $mapping) {
                        
                        // [INICIO PARCHE 11] - Corrección de la comprobación
                        if (!$this->providerKeyMatches($providerKey, $mapping->providers)) {
                        // [FIN PARCHE 11]
                            continue;
                        }

                        $keyword = $this->getKeywordFromField($part, $mapping->field);
                        if ($keyword && isset($providerResults[$keyword])) {
                            foreach ($providerResults[$keyword] as $dto) {
                                $batchResults[$part->getId()][] = new BulkSearchPartResultDTO(
                                    searchResult: $dto,
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
     * @param BulkSearchFieldMappingDTO[] $fieldMappings Array of field mapping configurations
     * @param array<string, InfoProviderInterface> $regularProviders Regular providers indexed by key
     * @param array<int, BulkSearchPartResultDTO[]> $excludeResults Results to exclude (from batch processing)
     * @return array<int, BulkSearchPartResultDTO[]> Results indexed by part ID
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
                
                // [INICIO PARCHE 11] - Corrección de la intersección
                $providers = [];
                foreach (array_keys($regularProviders) as $regProviderKey) {
                    if ($this->providerKeyMatches($regProviderKey, $mapping->providers)) {
                        $providers[] = $regProviderKey;
                    }
                }
                // [FIN PARCHE 11]
                
                if (empty($providers)) {
                    continue;
                }

                $keyword = $this->getKeywordFromField($part, $mapping->field);
                if (!$keyword) {
                    continue;
                }
                
                $this->logger->info("BulkInfoProvider: processRegularProviders: Searching for keyword '{$keyword}' with providers: " . implode(',', $providers));

                try {
                    $dtos = $this->infoRetriever->searchByKeyword($keyword, $providers);
                    $this->logger->info("BulkInfoProvider: processRegularProviders: Found " . count($dtos) . " results for keyword '{$keyword}'");

                    foreach ($dtos as $dto) {
                        $regularResults[$part->getId()][] = new BulkSearchPartResultDTO(
                            searchResult: $dto,
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
     * @param BulkSearchFieldMappingDTO[] $fieldMappings Array of field mapping configurations
     * @param string $providerKey The provider key to collect keywords for
     * @return string[] Array of unique keywords
     */
    private function collectKeywordsForProvider(array $parts, array $fieldMappings, string $providerKey): array
    {
        $keywords = [];

        foreach ($parts as $part) {
            foreach ($fieldMappings as $mapping) {
                
                // [INICIO PARCHE 11] - Corrección de la comprobación
                if (!$this->providerKeyMatches($providerKey, $mapping->providers)) {
                // [FIN PARCHE 11]
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
     * @param BulkSearchPartResultDTO[] $bulkResults Array of bulk search results
     * @return BulkSearchPartResultDTO[] Array of formatted search results with metadata
     */
    private function formatSearchResults(array $bulkResults): array
    {
        // Sort by priority and remove duplicates
        usort($bulkResults, fn($a, $b) => $a->priority <=> $b->priority);

        $uniqueResults = [];
        $seenKeys = [];

        foreach ($bulkResults as $result) {
            $key = "{$result->searchResult->provider_key}|{$result->searchResult->provider_id}";
            if (!in_array($key, $seenKeys, true)) {
                $seenKeys[] = $key;
                $uniqueResults[] = $result;
            }
        }

        return $uniqueResults;
    }

    /**
     * Prefetch detailed information for search results.
     *
     * @param BulkSearchResponseDTO $searchResults Search results (supports both new DTO and legacy array format)
     */
    public function prefetchDetailsForResults(BulkSearchResponseDTO $searchResults): void
    {
        $prefetchCount = 0;

        // Handle both new DTO format and legacy array format for backwards compatibility
        foreach ($searchResults->partResults as $partResult) {
            foreach ($partResult->searchResults as $result) {
                $dto = $result->searchResult;

                try {
                    $this->infoRetriever->getDetails($dto->provider_key, $dto->provider_id);
                    $prefetchCount++;
                } catch (\Exception $e) {
                    $this->logger->warning('Failed to prefetch details for provider part', [
                        'provider_key' => $dto->provider_key,
                        'provider_id' => $dto->provider_id,
                        'error' => $e->getMessage()
                    ]);
                }
            }
        }

        $this->logger->info("Prefetched details for {$prefetchCount} search results");
    }
}
