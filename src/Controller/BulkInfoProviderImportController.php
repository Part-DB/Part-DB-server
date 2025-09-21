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
use App\Services\InfoProviderSystem\BulkInfoProviderService;
use App\Services\InfoProviderSystem\DTOs\BulkSearchResponseDTO;
use App\Services\InfoProviderSystem\DTOs\BulkSearchFieldMappingDTO;
use App\Services\InfoProviderSystem\DTOs\BulkSearchPartResultsDTO;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use App\Entity\UserSystem\User;

#[Route('/tools/bulk-info-provider-import')]
class BulkInfoProviderImportController extends AbstractController
{
    public function __construct(
        private readonly BulkInfoProviderService $bulkService,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
        #[Autowire(param: 'partdb.bulk_import.batch_size')]
        private readonly int $bulkImportBatchSize,
        #[Autowire(param: 'partdb.bulk_import.max_parts_per_operation')]
        private readonly int $bulkImportMaxParts
    ) {
    }

    /**
     * Convert field mappings from array format to FieldMappingDTO[].
     *
     * @param array $fieldMappings Array of field mapping arrays
     * @return BulkSearchFieldMappingDTO[] Array of FieldMappingDTO objects
     */
    private function convertFieldMappingsToDto(array $fieldMappings): array
    {
        $dtos = [];
        foreach ($fieldMappings as $mapping) {
            $dtos[] = new BulkSearchFieldMappingDTO(field: $mapping['field'], providers: $mapping['providers'], priority: $mapping['priority'] ?? 1);
        }
        return $dtos;
    }

    private function createErrorResponse(string $message, int $statusCode = 400, array $context = []): JsonResponse
    {
        $this->logger->warning('Bulk import operation failed', array_merge([
            'error' => $message,
            'user' => $this->getUser()?->getUserIdentifier(),
        ], $context));

        return $this->json([
            'success' => false,
            'error' => $message
        ], $statusCode);
    }

    private function validateJobAccess(int $jobId): ?BulkInfoProviderImportJob
    {
        $this->denyAccessUnlessGranted('@info_providers.create_parts');

        $job = $this->entityManager->getRepository(BulkInfoProviderImportJob::class)->find($jobId);

        if (!$job) {
            return null;
        }

        if ($job->getCreatedBy() !== $this->getUser()) {
            return null;
        }

        return $job;
    }

    private function updatePartSearchResults(BulkInfoProviderImportJob $job, int $partId, ?BulkSearchPartResultsDTO $newResults): void
    {
        if ($newResults === null) {
            return;
        }

        // Only deserialize and update if we have new results
        $allResults = $job->getSearchResults($this->entityManager);

        // Find and update the results for this specific part
        $allResults = $allResults->replaceResultsForPart($partId, $newResults);

        // Save updated results back to job
        $job->setSearchResults($allResults);
    }

    #[Route('/step1', name: 'bulk_info_provider_step1')]
    public function step1(Request $request): Response
    {
        $this->denyAccessUnlessGranted('@info_providers.create_parts');

        set_time_limit(600);

        $ids = $request->query->get('ids');
        if (!$ids) {
            $this->addFlash('error', 'No parts selected for bulk import');
            return $this->redirectToRoute('homepage');
        }

        $partIds = explode(',', $ids);
        $partRepository = $this->entityManager->getRepository(Part::class);
        $parts = $partRepository->getElementsFromIDArray($partIds);

        if (empty($parts)) {
            $this->addFlash('error', 'No valid parts found for bulk import');
            return $this->redirectToRoute('homepage');
        }

        // Validate against configured maximum
        if (count($parts) > $this->bulkImportMaxParts) {
            $this->addFlash('error', sprintf(
                'Too many parts selected (%d). Maximum allowed is %d parts per operation.',
                count($parts),
                $this->bulkImportMaxParts
            ));
            return $this->redirectToRoute('homepage');
        }

        if (count($parts) > ($this->bulkImportMaxParts / 2)) {
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
            $fieldMappingDtos = $this->convertFieldMappingsToDto($formData['field_mappings']);
            $prefetchDetails = $formData['prefetch_details'] ?? false;

            $user = $this->getUser();
            if (!$user instanceof User) {
                throw new \RuntimeException('User must be authenticated and of type User');
            }

            // Validate part count against configuration limit
            if (count($parts) > $this->bulkImportMaxParts) {
                $this->addFlash('error', "Too many parts selected. Maximum allowed: {$this->bulkImportMaxParts}");
                $partIds = array_map(fn($part) => $part->getId(), $parts);
                return $this->redirectToRoute('bulk_info_provider_step1', ['ids' => implode(',', $partIds)]);
            }

            // Create and save the job
            $job = new BulkInfoProviderImportJob();
            $job->setFieldMappings($fieldMappingDtos);
            $job->setPrefetchDetails($prefetchDetails);
            $job->setCreatedBy($user);

            foreach ($parts as $part) {
                $jobPart = new BulkInfoProviderImportJobPart($job, $part);
                $job->addJobPart($jobPart);
            }

            $this->entityManager->persist($job);
            $this->entityManager->flush();

            try {
                $searchResultsDto = $this->bulkService->performBulkSearch($parts, $fieldMappingDtos, $prefetchDetails);

                // Save search results to job
                $job->setSearchResults($searchResultsDto);
                $job->markAsInProgress();
                $this->entityManager->flush();

                // Prefetch details if requested
                if ($prefetchDetails) {
                    $this->bulkService->prefetchDetailsForResults($searchResultsDto);
                }

                return $this->redirectToRoute('bulk_info_provider_step2', ['jobId' => $job->getId()]);

            } catch (\Exception $e) {
                $this->logger->error('Critical error during bulk import search', [
                    'job_id' => $job->getId(),
                    'error' => $e->getMessage(),
                    'exception' => $e
                ]);

                $this->entityManager->remove($job);
                $this->entityManager->flush();

                $this->addFlash('error', 'Search failed due to an error: ' . $e->getMessage());
                $partIds = array_map(fn($part) => $part->getId(), $parts);
                return $this->redirectToRoute('bulk_info_provider_step1', ['ids' => implode(',', $partIds)]);
            }
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
        $this->denyAccessUnlessGranted('@info_providers.create_parts');

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
        $job = $this->validateJobAccess($jobId);
        if (!$job) {
            return $this->createErrorResponse('Job not found or access denied', 404, ['job_id' => $jobId]);
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
        $job = $this->validateJobAccess($jobId);
        if (!$job) {
            return $this->createErrorResponse('Job not found or access denied', 404, ['job_id' => $jobId]);
        }

        // Only allow stopping of pending or in-progress jobs
        if (!$job->canBeStopped()) {
            return $this->json(['error' => 'Cannot stop job in current status'], 400);
        }

        $job->markAsStopped();
        $this->entityManager->flush();

        return $this->json(['success' => true]);
    }


    #[Route('/step2/{jobId}', name: 'bulk_info_provider_step2')]
    public function step2(int $jobId): Response
    {
        $this->denyAccessUnlessGranted('@info_providers.create_parts');

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
        $searchResults = $job->getSearchResults($this->entityManager);

        return $this->render('info_providers/bulk_import/step2.html.twig', [
            'job' => $job,
            'parts' => $parts,
            'search_results' => $searchResults,
        ]);
    }


    #[Route('/job/{jobId}/part/{partId}/mark-completed', name: 'bulk_info_provider_mark_completed', methods: ['POST'])]
    public function markPartCompleted(int $jobId, int $partId): Response
    {
        $job = $this->validateJobAccess($jobId);
        if (!$job) {
            return $this->createErrorResponse('Job not found or access denied', 404, ['job_id' => $jobId]);
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
        $job = $this->validateJobAccess($jobId);
        if (!$job) {
            return $this->createErrorResponse('Job not found or access denied', 404, ['job_id' => $jobId]);
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
        $job = $this->validateJobAccess($jobId);
        if (!$job) {
            return $this->createErrorResponse('Job not found or access denied', 404, ['job_id' => $jobId]);
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

    #[Route('/job/{jobId}/part/{partId}/research', name: 'bulk_info_provider_research_part', methods: ['POST'])]
    public function researchPart(int $jobId, int $partId): JsonResponse
    {
        $job = $this->validateJobAccess($jobId);
        if (!$job) {
            return $this->createErrorResponse('Job not found or access denied', 404, ['job_id' => $jobId]);
        }

        $part = $this->entityManager->getRepository(Part::class)->find($partId);
        if (!$part) {
            return $this->createErrorResponse('Part not found', 404, ['part_id' => $partId]);
        }

        // Only refresh if the entity might be stale (optional optimization)
        if ($this->entityManager->getUnitOfWork()->isScheduledForUpdate($part)) {
            $this->entityManager->refresh($part);
        }

        try {
            // Use the job's field mappings to perform the search
            $fieldMappings = $job->getFieldMappings();
            $prefetchDetails = $job->isPrefetchDetails();

            $fieldMappingDtos = $this->convertFieldMappingsToDto($fieldMappings);

            try {
                $searchResultsDto = $this->bulkService->performBulkSearch([$part], $fieldMappingDtos, $prefetchDetails);
            } catch (\Exception $searchException) {
                // Handle "no search results found" as a normal case, not an error
                if (str_contains($searchException->getMessage(), 'No search results found')) {
                    $searchResultsDto = null;
                } else {
                    throw $searchException;
                }
            }

            // Update the job's search results for this specific part efficiently
            $this->updatePartSearchResults($job, $partId, $searchResultsDto[0] ?? null);

            // Prefetch details if requested
            if ($prefetchDetails && $searchResultsDto !== null) {
                $this->bulkService->prefetchDetailsForResults($searchResultsDto);
            }

            $this->entityManager->flush();

            // Return the new results for this part
            $newResults = $searchResultsDto[0] ?? null;

            return $this->json([
                'success' => true,
                'part_id' => $partId,
                'results_count' => $newResults ? $newResults->getResultCount() : 0,
                'errors_count' => $newResults ? $newResults->getErrorCount() : 0,
                'message' => 'Part research completed successfully'
            ]);

        } catch (\Exception $e) {
            return $this->createErrorResponse(
                'Research failed: ' . $e->getMessage(),
                500,
                [
                    'job_id' => $jobId,
                    'part_id' => $partId,
                    'exception' => $e->getMessage()
                ]
            );
        }
    }

    #[Route('/job/{jobId}/research-all', name: 'bulk_info_provider_research_all', methods: ['POST'])]
    public function researchAllParts(int $jobId): JsonResponse
    {
        $job = $this->validateJobAccess($jobId);
        if (!$job) {
            return $this->createErrorResponse('Job not found or access denied', 404, ['job_id' => $jobId]);
        }

        // Get all parts that are not completed or skipped
        $parts = [];
        foreach ($job->getJobParts() as $jobPart) {
            if (!$jobPart->isCompleted() && !$jobPart->isSkipped()) {
                $parts[] = $jobPart->getPart();
            }
        }

        if (empty($parts)) {
            return $this->json([
                'success' => true,
                'message' => 'No parts to research',
                'researched_count' => 0
            ]);
        }

        try {
            $fieldMappings = $job->getFieldMappings();
            $fieldMappingDtos = $this->convertFieldMappingsToDto($fieldMappings);
            $prefetchDetails = $job->isPrefetchDetails();

            // Process in batches to reduce memory usage for large operations
            $allResults = new BulkSearchResponseDTO(partResults: []);
            $batches = array_chunk($parts, $this->bulkImportBatchSize);

            foreach ($batches as $batch) {
                $batchResultsDto = $this->bulkService->performBulkSearch($batch, $fieldMappingDtos, $prefetchDetails);
                $allResults =  BulkSearchResponseDTO::merge($allResults, $batchResultsDto);

                // Properly manage entity manager memory without losing state
                $jobId = $job->getId();
                $this->entityManager->clear();
                $job = $this->entityManager->find(BulkInfoProviderImportJob::class, $jobId);
            }

            // Update the job's search results
            $job->setSearchResults($allResults);

            // Prefetch details if requested
            if ($prefetchDetails) {
                $this->bulkService->prefetchDetailsForResults($allResults);
            }

            $this->entityManager->flush();

            return $this->json([
                'success' => true,
                'researched_count' => count($parts),
                'message' => sprintf('Successfully researched %d parts', count($parts))
            ]);

        } catch (\Exception $e) {
            return $this->createErrorResponse(
                'Bulk research failed: ' . $e->getMessage(),
                500,
                [
                    'job_id' => $jobId,
                    'part_count' => count($parts),
                    'exception' => $e->getMessage()
                ]
            );
        }
    }
}
