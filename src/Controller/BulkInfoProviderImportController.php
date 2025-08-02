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

use App\Entity\Parts\Part;
use App\Entity\Parts\Supplier;
use App\Form\InfoProviderSystem\GlobalFieldMappingType;
use App\Services\InfoProviderSystem\PartInfoRetriever;
use App\Services\InfoProviderSystem\ProviderRegistry;
use App\Services\InfoProviderSystem\ExistingPartFinder;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpClient\Exception\ClientException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

use function Symfony\Component\Translation\t;

#[Route('/tools/bulk-info-provider-import')]
class BulkInfoProviderImportController extends AbstractController
{
    public function __construct(
        private readonly ProviderRegistry $providerRegistry,
        private readonly PartInfoRetriever $infoRetriever,
        private readonly ExistingPartFinder $existingPartFinder,
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    #[Route('/step1', name: 'bulk_info_provider_step1')]
    public function step1(Request $request, LoggerInterface $exceptionLogger): Response
    {
        $this->denyAccessUnlessGranted('@info_providers.create_parts');

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
                ['field' => 'mpn', 'providers' => []]
            ]
        ];

        $form = $this->createForm(GlobalFieldMappingType::class, $initialData, [
            'field_choices' => $fieldChoices
        ]);
        $form->handleRequest($request);

        $searchResults = null;

        if ($form->isSubmitted() && $form->isValid()) {
            $fieldMappings = $form->getData()['field_mappings'];
            $searchResults = [];

            foreach ($parts as $part) {
                $partResult = [
                    'part' => $part,
                    'search_results' => [],
                    'errors' => []
                ];

                // Collect all DTOs from all applicable field mappings
                $allDtos = [];

                foreach ($fieldMappings as $mapping) {
                    $field = $mapping['field'];
                    $providers = $mapping['providers'] ?? [];

                    if (empty($providers)) {
                        continue;
                    }

                    $keyword = $this->getKeywordFromField($part, $field);

                    if ($keyword) {
                        try {
                            $dtos = $this->infoRetriever->searchByKeyword(
                                keyword: $keyword,
                                providers: $providers
                            );

                            // Add field info to each DTO for tracking
                            foreach ($dtos as $dto) {
                                $dto->_source_field = $field;
                                $dto->_source_keyword = $keyword;
                            }

                            $allDtos = array_merge($allDtos, $dtos);
                        } catch (ClientException $e) {
                            $partResult['errors'][] = "Error searching with {$field}: " . $e->getMessage();
                            $exceptionLogger->error('Error during bulk info provider search for part ' . $part->getId() . " field {$field}: " . $e->getMessage(), ['exception' => $e]);
                        }
                    }
                }

                // Remove duplicates based on provider_key + provider_id
                $uniqueDtos = [];
                $seenKeys = [];
                foreach ($allDtos as $dto) {
                    $key = $dto->provider_key . '|' . $dto->provider_id;
                    if (!in_array($key, $seenKeys)) {
                        $seenKeys[] = $key;
                        $uniqueDtos[] = $dto;
                    }
                }

                // Convert DTOs to result format
                $partResult['search_results'] = array_map(
                    fn($dto) => ['dto' => $dto, 'localPart' => $this->existingPartFinder->findFirstExisting($dto)],
                    $uniqueDtos
                );

                $searchResults[] = $partResult;
            }
        }

        return $this->render('info_providers/bulk_import/step1.html.twig', [
            'form' => $form,
            'parts' => $parts,
            'search_results' => $searchResults,
            'fieldChoices' => $fieldChoices
        ]);
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
}