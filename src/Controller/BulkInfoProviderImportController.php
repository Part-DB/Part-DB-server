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

namespace App\Controller;

use App\Entity\BulkInfoProviderImportJob;
use App\Entity\BulkInfoProviderImportJobPart;
use App\Entity\BulkImportJobStatus;
use App\Entity\Parts\Part;
use App\Entity\Parts\Supplier;
use App\Form\InfoProviderSystem\GlobalFieldMappingType;
use App\Services\InfoProviderSystem\PartInfoRetriever;
use App\Services\InfoProviderSystem\ExistingPartFinder;
use App\Services\InfoProviderSystem\ProviderRegistry;
use App\Services\InfoProviderSystem\Providers\LCSCProvider;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpClient\Exception\ClientException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Entity\UserSystem\User;

#[Route('/tools/bulk-info-provider-import')]
class BulkInfoProviderImportController extends AbstractController
{
    public function __construct(
        private readonly PartInfoRetriever $infoRetriever,
        private readonly LCSCProvider $LCSCProvider,
        private readonly ExistingPartFinder $existingPartFinder,
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    #[Route('/step1', name: 'bulk_info_provider_step1')]
    public function step1(Request $request, LoggerInterface $exceptionLogger): Response
    {
        $this->denyAccessUnlessGranted('@info_providers.create_parts');

        // Increase execution time for bulk operations
        set_time_limit(600); // 10 minutes for large batches

        $ids = $request->query->get('ids');
        if (!$ids) {
            $this->addFlash('error', 'No parts selected for bulk import');
            return $this->redirectToRoute('homepage');
        }

        // Get the selected parts
        $partIds = explode(',', $ids);
        $partRepository = $this->entityManager->getRepository(Part::class);
        $parts = $partRepository->getElementsFromIDArray($partIds);

        if (empty($parts)) {
            $this->addFlash('error', 'No valid parts found for bulk import');
            return $this->redirectToRoute('homepage');
        }

        // Warn about large batches
        if (count($parts) > 50) {
            $this->addFlash('warning', 'Processing ' . count($parts) . ' parts may take several minutes and could timeout. Consider processing smaller batches.');
        }

        // Generate field choices
        $fieldChoices = [
            'info_providers.bulk_search.field.mpn' => 'mpn',
            'info_providers.bulk_search.field.name' => 'name',
        ];

        // Add dynamic supplier fields
        $suppliers = $this->entityManager->getRepository(Supplier::class)->findAll();
        foreach ($suppliers as $supplier) {
            $supplierKey = strtolower(str_replace([' ', '-', '_'], '_', $supplier->getName()));
            $fieldChoices["Supplier: " . $supplier->getName() . " (SPN)"] = $supplierKey . '_spn';
        }

        // Initialize form with useful default mappings
        $initialData = [
            'field_mappings' => [
                ['field' => 'mpn', 'providers' => [], 'priority' => 1]
            ],
            'prefetch_details' => false
        ];

        $form = $this->createForm(GlobalFieldMappingType::class, $initialData, [
            'field_choices' => $fieldChoices
        ]);
        $form->handleRequest($request);

        $searchResults = null;

        if ($form->isSubmitted() && $form->isValid()) {
            $formData = $form->getData();
            $fieldMappings = $formData['field_mappings'];
            $prefetchDetails = $formData['prefetch_details'] ?? false;

            // Debug logging
            $exceptionLogger->info('Form data received', [
                'prefetch_details' => $prefetchDetails,
                'prefetch_details_type' => gettype($prefetchDetails)
            ]);

            // Create and save the job
            $job = new BulkInfoProviderImportJob();
            $job->setFieldMappings($fieldMappings);
            $job->setPrefetchDetails($prefetchDetails);
            $user = $this->getUser();
            if (!$user instanceof User) {
                throw new \RuntimeException('User must be authenticated and of type User');
            }
            $job->setCreatedBy($user);

            // Create job parts for each part
            foreach ($parts as $part) {
                $jobPart = new BulkInfoProviderImportJobPart($job, $part);
                $job->addJobPart($jobPart);
            }

            $this->entityManager->persist($job);
            $this->entityManager->flush();

            $searchResults = [];
            $hasAnyResults = false;

            try {
                // Optimize: Use batch async requests for LCSC provider
                $lcscKeywords = [];
                $keywordToPartField = [];

                // First, collect all LCSC keywords for batch processing
                foreach ($parts as $part) {
                    foreach ($fieldMappings as $mapping) {
                        $field = $mapping['field'];
                        $providers = $mapping['providers'] ?? [];

                        if (in_array('lcsc', $providers, true)) {
                            $keyword = $this->getKeywordFromField($part, $field);
                            if ($keyword) {
                                $lcscKeywords[] = $keyword;
                                $keywordToPartField[$keyword] = [
                                    'part' => $part,
                                    'field' => $field
                                ];
                            }
                        }
                    }
                }

                // Batch search LCSC keywords asynchronously
                $lcscBatchResults = [];
                if (!empty($lcscKeywords)) {
                    try {
                        // Try to get LCSC provider and use batch method if available
                        $lcscBatchResults = $this->searchLcscBatch($lcscKeywords);
                    } catch (\Exception $e) {
                        $exceptionLogger->warning('LCSC batch search failed, falling back to individual requests', [
                            'error' => $e->getMessage()
                        ]);
                    }
                }

                // Now process each part
                foreach ($parts as $part) {
                    $partResult = [
                        'part' => $part,
                        'search_results' => [],
                        'errors' => []
                    ];

                    // Collect all DTOs using priority-based search
                    $allDtos = [];
                    $dtoMetadata = []; // Store source field info separately

                    // Group mappings by priority (lower number = higher priority)
                    $mappingsByPriority = [];
                    foreach ($fieldMappings as $mapping) {
                        $priority = $mapping['priority'] ?? 1;
                        $mappingsByPriority[$priority][] = $mapping;
                    }
                    ksort($mappingsByPriority); // Sort by priority (1, 2, 3...)

                    // Try each priority level until we find results
                    foreach ($mappingsByPriority as $priority => $mappings) {
                        $priorityResults = [];

                        // For same priority, search all and combine results
                        foreach ($mappings as $mapping) {
                            $field = $mapping['field'];
                            $providers = $mapping['providers'] ?? [];

                            if (empty($providers)) {
                                continue;
                            }

                            $keyword = $this->getKeywordFromField($part, $field);

                            if ($keyword) {
                                try {
                                    // Use batch results for LCSC if available
                                    if (in_array('lcsc', $providers, true) && isset($lcscBatchResults[$keyword])) {
                                        $dtos = $lcscBatchResults[$keyword];
                                    } else {
                                        // Fall back to regular search for non-LCSC providers
                                        $dtos = $this->infoRetriever->searchByKeyword(
                                            keyword: $keyword,
                                            providers: $providers
                                        );
                                    }

                                    // Store field info for each DTO separately
                                    foreach ($dtos as $dto) {
                                        $dtoKey = $dto->provider_key . '|' . $dto->provider_id;
                                        $dtoMetadata[$dtoKey] = [
                                            'source_field' => $field,
                                            'source_keyword' => $keyword,
                                            'priority' => $priority
                                        ];
                                    }

                                    $priorityResults = array_merge($priorityResults, $dtos);
                                } catch (ClientException $e) {
                                    $partResult['errors'][] = "Error searching with {$field} (priority {$priority}): " . $e->getMessage();
                                    $exceptionLogger->error('Error during bulk info provider search for part ' . $part->getId() . " field {$field}: " . $e->getMessage(), ['exception' => $e]);
                                }
                            }
                        }

                        // If we found results at this priority level, use them and stop
                        if (!empty($priorityResults)) {
                            $allDtos = $priorityResults;
                            break;
                        }
                    }

                    // Remove duplicates based on provider_key + provider_id
                    $uniqueDtos = [];
                    $seenKeys = [];
                    foreach ($allDtos as $dto) {
                        if ($dto === null || !isset($dto->provider_key, $dto->provider_id)) {
                            continue;
                        }
                        $key = "{$dto->provider_key}|{$dto->provider_id}";
                        if (!in_array($key, $seenKeys, true)) {
                            $seenKeys[] = $key;
                            $uniqueDtos[] = $dto;
                        }
                    }

                    // Convert DTOs to result format with metadata
                    $partResult['search_results'] = array_map(
                        function ($dto) use ($dtoMetadata) {
                            $dtoKey = $dto->provider_key . '|' . $dto->provider_id;
                            $metadata = $dtoMetadata[$dtoKey] ?? [];
                            return [
                                'dto' => $dto,
                                'localPart' => $this->existingPartFinder->findFirstExisting($dto),
                                'source_field' => $metadata['source_field'] ?? null,
                                'source_keyword' => $metadata['source_keyword'] ?? null
                            ];
                        },
                        $uniqueDtos
                    );

                    if (!empty($partResult['search_results'])) {
                        $hasAnyResults = true;
                    }

                    $searchResults[] = $partResult;
                }

                // Check if search was successful
                if (!$hasAnyResults) {
                    $exceptionLogger->warning('Bulk import search returned no results for any parts', [
                        'job_id' => $job->getId(),
                        'parts_count' => count($parts)
                    ]);

                    // Delete the job since it has no useful results
                    $this->entityManager->remove($job);
                    $this->entityManager->flush();

                    $this->addFlash('error', 'No search results found for any of the selected parts. Please check your field mappings and provider selections.');
                    return $this->redirectToRoute('bulk_info_provider_step1', ['ids' => implode(',', $partIds)]);
                }

                // Save search results to job
                $job->setSearchResults($this->serializeSearchResults($searchResults));
                $job->markAsInProgress();
                $this->entityManager->flush();

            } catch (\Exception $e) {
                $exceptionLogger->error('Critical error during bulk import search', [
                    'job_id' => $job->getId(),
                    'error' => $e->getMessage(),
                    'exception' => $e
                ]);

                // Delete the job on critical failure
                $this->entityManager->remove($job);
                $this->entityManager->flush();

                $this->addFlash('error', 'Search failed due to an error: ' . $e->getMessage());
                return $this->redirectToRoute('bulk_info_provider_step1', ['ids' => implode(',', $partIds)]);
            }

            // Prefetch details if requested
            if ($prefetchDetails) {
                $exceptionLogger->info('Prefetch details requested, starting prefetch for ' . count($searchResults) . ' parts');
                $this->prefetchDetailsForResults($searchResults, $exceptionLogger);
            } else {
                $exceptionLogger->info('Prefetch details not requested, skipping prefetch');
            }

            // Redirect to step 2 with the job
            return $this->redirectToRoute('bulk_info_provider_step2', ['jobId' => $job->getId()]);
        }

        // Get existing in-progress jobs for current user
        $existingJobs = $this->entityManager->getRepository(BulkInfoProviderImportJob::class)
            ->findBy(['createdBy' => $this->getUser(), 'status' => BulkImportJobStatus::IN_PROGRESS], ['createdAt' => 'DESC'], 10);

        return $this->render('info_providers/bulk_import/step1.html.twig', [
            'form' => $form,
            'parts' => $parts,
            'search_results' => $searchResults,
            'existing_jobs' => $existingJobs,
            'fieldChoices' => $fieldChoices
        ]);
    }

    #[Route('/manage', name: 'bulk_info_provider_manage')]
    public function manageBulkJobs(): Response
    {
        // Get all jobs for current user
        $allJobs = $this->entityManager->getRepository(BulkInfoProviderImportJob::class)
            ->findBy([], ['createdAt' => 'DESC']);

        // Check and auto-complete jobs that should be completed
        // Also clean up jobs with no results (failed searches)
        $updatedJobs = false;
        $jobsToDelete = [];

        foreach ($allJobs as $job) {
            if ($job->isAllPartsCompleted() && !$job->isCompleted()) {
                $job->markAsCompleted();
                $updatedJobs = true;
            }

            // Mark jobs with no results for deletion (failed searches)
            if ($job->getResultCount() === 0 && $job->isInProgress()) {
                $jobsToDelete[] = $job;
            }
        }

        // Delete failed jobs
        foreach ($jobsToDelete as $job) {
            $this->entityManager->remove($job);
            $updatedJobs = true;
        }

        // Flush changes if any jobs were updated
        if ($updatedJobs) {
            $this->entityManager->flush();

            if (!empty($jobsToDelete)) {
                $this->addFlash('info', 'Cleaned up ' . count($jobsToDelete) . ' failed job(s) with no results.');
            }
        }

        return $this->render('info_providers/bulk_import/manage.html.twig', [
            'jobs' => $this->entityManager->getRepository(BulkInfoProviderImportJob::class)
                ->findBy([], ['createdAt' => 'DESC']) // Refetch after cleanup
        ]);
    }

    #[Route('/job/{jobId}/delete', name: 'bulk_info_provider_delete', methods: ['DELETE'])]
    public function deleteJob(int $jobId): Response
    {
        $job = $this->entityManager->getRepository(BulkInfoProviderImportJob::class)->find($jobId);

        if (!$job || $job->getCreatedBy() !== $this->getUser()) {
            return $this->json(['error' => 'Job not found or access denied'], 404);
        }

        // Only allow deletion of completed, failed, or stopped jobs
        if (!$job->isCompleted() && !$job->isFailed() && !$job->isStopped()) {
            return $this->json(['error' => 'Cannot delete active job'], 400);
        }

        $this->entityManager->remove($job);
        $this->entityManager->flush();

        return $this->json(['success' => true]);
    }

    #[Route('/job/{jobId}/stop', name: 'bulk_info_provider_stop', methods: ['POST'])]
    public function stopJob(int $jobId): Response
    {
        $job = $this->entityManager->getRepository(BulkInfoProviderImportJob::class)->find($jobId);

        if (!$job || $job->getCreatedBy() !== $this->getUser()) {
            return $this->json(['error' => 'Job not found or access denied'], 404);
        }

        // Only allow stopping of pending or in-progress jobs
        if (!$job->canBeStopped()) {
            return $this->json(['error' => 'Cannot stop job in current status'], 400);
        }

        $job->markAsStopped();
        $this->entityManager->flush();

        return $this->json(['success' => true]);
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
        // Check if this is a supplier SPN field
        if (!str_ends_with($field, '_spn')) {
            return null;
        }

        // Extract supplier key (remove _spn suffix)
        $supplierKey = substr($field, 0, -4);

        // Get all suppliers to find matching one
        $suppliers = $this->entityManager->getRepository(Supplier::class)->findAll();

        foreach ($suppliers as $supplier) {
            $normalizedName = strtolower(str_replace([' ', '-', '_'], '_', $supplier->getName()));
            if ($normalizedName === $supplierKey) {
                // Find order detail for this supplier
                $orderDetail = $part->getOrderdetails()->filter(
                    fn($od) => $od->getSupplier()?->getId() === $supplier->getId()
                )->first();

                return $orderDetail ? $orderDetail->getSupplierpartnr() : null;
            }
        }

        return null;
    }

    /**
     * Prefetch details for all search results to populate cache
     */
    private function prefetchDetailsForResults(array $searchResults, LoggerInterface $logger): void
    {
        $prefetchCount = 0;

        foreach ($searchResults as $partResult) {
            foreach ($partResult['search_results'] as $result) {
                $dto = $result['dto'];

                try {
                    // This call will cache the details for later use
                    $this->infoRetriever->getDetails($dto->provider_key, $dto->provider_id);
                    $prefetchCount++;
                } catch (\Exception $e) {
                    $logger->warning('Failed to prefetch details for provider part', [
                        'provider_key' => $dto->provider_key,
                        'provider_id' => $dto->provider_id,
                        'error' => $e->getMessage()
                    ]);
                }
            }
        }

        if ($prefetchCount > 0) {
            $this->addFlash('success', "Prefetched details for {$prefetchCount} search results");
        }
    }

    #[Route('/step2/{jobId}', name: 'bulk_info_provider_step2')]
    public function step2(int $jobId): Response
    {
        $job = $this->entityManager->getRepository(BulkInfoProviderImportJob::class)->find($jobId);

        if (!$job) {
            $this->addFlash('error', 'Bulk import job not found');
            return $this->redirectToRoute('bulk_info_provider_step1');
        }

        // Check if user owns this job
        if ($job->getCreatedBy() !== $this->getUser()) {
            $this->addFlash('error', 'Access denied to this bulk import job');
            return $this->redirectToRoute('bulk_info_provider_step1');
        }

        // Get the parts and deserialize search results
        $parts = $job->getJobParts()->map(fn($jobPart) => $jobPart->getPart())->toArray();
        $searchResults = $this->deserializeSearchResults($job->getSearchResults(), $parts);

        return $this->render('info_providers/bulk_import/step2.html.twig', [
            'job' => $job,
            'parts' => $parts,
            'search_results' => $searchResults,
        ]);
    }

    private function serializeSearchResults(array $searchResults): array
    {
        $serialized = [];

        foreach ($searchResults as $partResult) {
            $partData = [
                'part_id' => $partResult['part']->getId(),
                'search_results' => [],
                'errors' => $partResult['errors']
            ];

            foreach ($partResult['search_results'] as $result) {
                $dto = $result['dto'];
                $partData['search_results'][] = [
                    'dto' => [
                        'provider_key' => $dto->provider_key,
                        'provider_id' => $dto->provider_id,
                        'name' => $dto->name,
                        'description' => $dto->description,
                        'manufacturer' => $dto->manufacturer,
                        'mpn' => $dto->mpn,
                        'provider_url' => $dto->provider_url,
                        'preview_image_url' => $dto->preview_image_url,
                        '_source_field' => $result['source_field'] ?? null,
                        '_source_keyword' => $result['source_keyword'] ?? null,
                    ],
                    'localPart' => $result['localPart'] ? $result['localPart']->getId() : null
                ];
            }

            $serialized[] = $partData;
        }

        return $serialized;
    }

    private function deserializeSearchResults(array $serializedResults, array $parts): array
    {
        $partsById = [];
        foreach ($parts as $part) {
            $partsById[$part->getId()] = $part;
        }

        $searchResults = [];

        foreach ($serializedResults as $partData) {
            $part = $partsById[$partData['part_id']] ?? null;
            if (!$part) {
                continue;
            }

            $partResult = [
                'part' => $part,
                'search_results' => [],
                'errors' => $partData['errors']
            ];

            foreach ($partData['search_results'] as $resultData) {
                $dtoData = $resultData['dto'];

                $dto = new \App\Services\InfoProviderSystem\DTOs\SearchResultDTO(
                    provider_key: $dtoData['provider_key'],
                    provider_id: $dtoData['provider_id'],
                    name: $dtoData['name'],
                    description: $dtoData['description'],
                    manufacturer: $dtoData['manufacturer'],
                    mpn: $dtoData['mpn'],
                    provider_url: $dtoData['provider_url'],
                    preview_image_url: $dtoData['preview_image_url']
                );

                $localPart = null;
                if ($resultData['localPart']) {
                    $localPart = $this->entityManager->getRepository(Part::class)->find($resultData['localPart']);
                }

                $partResult['search_results'][] = [
                    'dto' => $dto,
                    'localPart' => $localPart,
                    'source_field' => $dtoData['_source_field'] ?? null,
                    'source_keyword' => $dtoData['_source_keyword'] ?? null
                ];
            }

            $searchResults[] = $partResult;
        }

        return $searchResults;
    }

    /**
     * Perform batch LCSC search using async HTTP requests
     */
    private function searchLcscBatch(array $keywords): array
    {
        return $this->LCSCProvider->searchByKeywordsBatch($keywords);
    }

    #[Route('/job/{jobId}/part/{partId}/mark-completed', name: 'bulk_info_provider_mark_completed', methods: ['POST'])]
    public function markPartCompleted(int $jobId, int $partId): Response
    {
        $job = $this->entityManager->getRepository(BulkInfoProviderImportJob::class)->find($jobId);

        if (!$job || $job->getCreatedBy() !== $this->getUser()) {
            return $this->json(['error' => 'Job not found or access denied'], 404);
        }

        $job->markPartAsCompleted($partId);

        // Auto-complete job if all parts are done
        if ($job->isAllPartsCompleted() && !$job->isCompleted()) {
            $job->markAsCompleted();
        }

        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'progress' => $job->getProgressPercentage(),
            'completed_count' => $job->getCompletedPartsCount(),
            'total_count' => $job->getPartCount(),
            'job_completed' => $job->isCompleted()
        ]);
    }

    #[Route('/job/{jobId}/part/{partId}/mark-skipped', name: 'bulk_info_provider_mark_skipped', methods: ['POST'])]
    public function markPartSkipped(int $jobId, int $partId, Request $request): Response
    {
        $job = $this->entityManager->getRepository(BulkInfoProviderImportJob::class)->find($jobId);

        if (!$job || $job->getCreatedBy() !== $this->getUser()) {
            return $this->json(['error' => 'Job not found or access denied'], 404);
        }

        $reason = $request->request->get('reason', '');
        $job->markPartAsSkipped($partId, $reason);

        // Auto-complete job if all parts are done
        if ($job->isAllPartsCompleted() && !$job->isCompleted()) {
            $job->markAsCompleted();
        }

        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'progress' => $job->getProgressPercentage(),
            'completed_count' => $job->getCompletedPartsCount(),
            'skipped_count' => $job->getSkippedPartsCount(),
            'total_count' => $job->getPartCount(),
            'job_completed' => $job->isCompleted()
        ]);
    }

    #[Route('/job/{jobId}/part/{partId}/mark-pending', name: 'bulk_info_provider_mark_pending', methods: ['POST'])]
    public function markPartPending(int $jobId, int $partId): Response
    {
        $job = $this->entityManager->getRepository(BulkInfoProviderImportJob::class)->find($jobId);

        if (!$job || $job->getCreatedBy() !== $this->getUser()) {
            return $this->json(['error' => 'Job not found or access denied'], 404);
        }

        $job->markPartAsPending($partId);
        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'progress' => $job->getProgressPercentage(),
            'completed_count' => $job->getCompletedPartsCount(),
            'skipped_count' => $job->getSkippedPartsCount(),
            'total_count' => $job->getPartCount(),
            'job_completed' => $job->isCompleted()
        ]);
    }
}
