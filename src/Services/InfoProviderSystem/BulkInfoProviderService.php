<?php

declare(strict_types=1);

namespace App\Services\InfoProviderSystem;

use App\Entity\BulkInfoProviderImportJob;
use App\Entity\Parts\Part;
use App\Entity\Parts\Supplier;
use App\Services\InfoProviderSystem\DTOs\BulkSearchRequestDTO;
use App\Services\InfoProviderSystem\DTOs\BulkSearchResultDTO;
use App\Services\InfoProviderSystem\Providers\BatchInfoProviderInterface;
use App\Services\InfoProviderSystem\Providers\InfoProviderInterface;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpClient\Exception\ClientException;

final class BulkInfoProviderService
{
    public function __construct(
        private readonly PartInfoRetriever $infoRetriever,
        private readonly ExistingPartFinder $existingPartFinder,
        private readonly ProviderRegistry $providerRegistry,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger
    ) {}

    public function performBulkSearch(BulkSearchRequestDTO $request): array
    {
        // Convert string IDs to integers
        $partIds = array_map('intval', $request->partIds);
        
        $partRepository = $this->entityManager->getRepository(Part::class);
        $parts = $partRepository->getElementsFromIDArray($partIds);

        if (empty($parts)) {
            throw new \InvalidArgumentException('No valid parts found for bulk import');
        }

        $searchResults = [];
        $hasAnyResults = false;

        // Group providers by batch capability
        $batchProviders = [];
        $regularProviders = [];
        
        foreach ($request->fieldMappings as $mapping) {
            $providers = $mapping['providers'] ?? [];
            foreach ($providers as $providerKey) {
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
        $batchResults = $this->processBatchProviders($parts, $request->fieldMappings, $batchProviders);
        
        // Process regular providers
        $regularResults = $this->processRegularProviders($parts, $request->fieldMappings, $regularProviders, $batchResults);

        // Combine and format results
        foreach ($parts as $part) {
            $partResult = [
                'part' => $part,
                'search_results' => [],
                'errors' => []
            ];

            // Get results from batch and regular processing
            $allResults = array_merge(
                $batchResults[$part->getId()] ?? [],
                $regularResults[$part->getId()] ?? []
            );

            if (!empty($allResults)) {
                $hasAnyResults = true;
                $partResult['search_results'] = $this->formatSearchResults($allResults);
            }

            $searchResults[] = $partResult;
        }

        if (!$hasAnyResults) {
            throw new \RuntimeException('No search results found for any of the selected parts');
        }

        return $searchResults;
    }

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
                        if (!in_array($providerKey, $mapping['providers'] ?? [], true)) {
                            continue;
                        }
                        
                        $keyword = $this->getKeywordFromField($part, $mapping['field']);
                        if ($keyword && isset($providerResults[$keyword])) {
                            foreach ($providerResults[$keyword] as $dto) {
                                $batchResults[$part->getId()][] = new BulkSearchResultDTO(
                                    baseDto: $dto,
                                    sourceField: $mapping['field'],
                                    sourceKeyword: $keyword,
                                    localPart: $this->existingPartFinder->findFirstExisting($dto),
                                    priority: $mapping['priority'] ?? 1
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
                $field = $mapping['field'];
                $providers = array_intersect($mapping['providers'] ?? [], array_keys($regularProviders));
                
                if (empty($providers)) {
                    continue;
                }

                $keyword = $this->getKeywordFromField($part, $field);
                if (!$keyword) {
                    continue;
                }

                try {
                    $dtos = $this->infoRetriever->searchByKeyword($keyword, $providers);
                    
                    foreach ($dtos as $dto) {
                        $regularResults[$part->getId()][] = new BulkSearchResultDTO(
                            baseDto: $dto,
                            sourceField: $field,
                            sourceKeyword: $keyword,
                            localPart: $this->existingPartFinder->findFirstExisting($dto),
                            priority: $mapping['priority'] ?? 1
                        );
                    }
                } catch (ClientException $e) {
                    $this->logger->error('Regular search failed', [
                        'part_id' => $part->getId(),
                        'field' => $field,
                        'error' => $e->getMessage()
                    ]);
                }
            }
        }

        return $regularResults;
    }

    private function collectKeywordsForProvider(array $parts, array $fieldMappings, string $providerKey): array
    {
        $keywords = [];
        
        foreach ($parts as $part) {
            foreach ($fieldMappings as $mapping) {
                if (!in_array($providerKey, $mapping['providers'] ?? [], true)) {
                    continue;
                }
                
                $keyword = $this->getKeywordFromField($part, $mapping['field']);
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
        $suppliers = $this->entityManager->getRepository(Supplier::class)->findAll();

        foreach ($suppliers as $supplier) {
            $normalizedName = strtolower(str_replace([' ', '-', '_'], '_', $supplier->getName()));
            if ($normalizedName === $supplierKey) {
                $orderDetail = $part->getOrderdetails()->filter(
                    fn($od) => $od->getSupplier()?->getId() === $supplier->getId()
                )->first();

                return $orderDetail ? $orderDetail->getSupplierpartnr() : null;
            }
        }

        return null;
    }

    private function formatSearchResults(array $bulkResults): array
    {
        // Sort by priority and remove duplicates
        usort($bulkResults, fn($a, $b) => $a->priority <=> $b->priority);
        
        $uniqueResults = [];
        $seenKeys = [];
        
        foreach ($bulkResults as $result) {
            $key = "{$result->provider_key}|{$result->provider_id}";
            if (!in_array($key, $seenKeys, true)) {
                $seenKeys[] = $key;
                $uniqueResults[] = [
                    'dto' => $result,
                    'localPart' => $result->localPart,
                    'source_field' => $result->sourceField,
                    'source_keyword' => $result->sourceKeyword
                ];
            }
        }

        return $uniqueResults;
    }

    public function prefetchDetailsForResults(array $searchResults): void
    {
        $prefetchCount = 0;

        foreach ($searchResults as $partResult) {
            foreach ($partResult['search_results'] as $result) {
                $dto = $result['dto'];

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