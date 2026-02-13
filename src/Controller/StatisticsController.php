<?php
/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2022 Jan Böhmer (https://github.com/jbtronics)
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

/**
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 * Copyright (C) 2019 - 2022 Jan Böhmer (https://github.com/jbtronics)
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

namespace App\Controller;

use App\Services\Tools\StatisticsHelper;
use App\Entity\AssemblySystem\AssemblyBOMEntry;
use App\Entity\AssemblySystem\Assembly;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

class StatisticsController extends AbstractController
{
    #[Route(path: '/statistics', name: 'statistics_view')]
    public function showStatistics(StatisticsHelper $helper): Response
    {
        $this->denyAccessUnlessGranted('@tools.statistics');

        return $this->render('tools/statistics/statistics.html.twig', [
            'helper' => $helper,
        ]);
    }

    #[Route(path: '/statistics/cleanup-assembly-bom-entries', name: 'statistics_cleanup_assembly_bom_entries', methods: ['POST'])]
    public function cleanupAssemblyBOMEntries(
        EntityManagerInterface $em,
        StatisticsHelper $helper,
        TranslatorInterface $translator
    ): JsonResponse {
        $this->denyAccessUnlessGranted('@tools.statistics');

        try {
            // We fetch the IDs of the entries that have a non-existent part.
            // We use a raw SQL approach or a more robust DQL to avoid proxy initialization issues.
            $qb = $em->createQueryBuilder();
            $qb->select('be.id', 'IDENTITY(be.part) AS part_id')
                ->from(AssemblyBOMEntry::class, 'be')
                ->leftJoin('be.part', 'p')
                ->where('be.part IS NOT NULL')
                ->andWhere('p.id IS NULL');

            $results = $qb->getQuery()->getResult();
            $count = count($results);

            foreach ($results as $result) {
                $entryId = $result['id'];
                $partId = $result['part_id'] ?? 'unknown';

                $entry = $em->find(AssemblyBOMEntry::class, $entryId);
                if ($entry instanceof AssemblyBOMEntry) {
                    $entry->setPart(null);
                    $entry->setName(sprintf('part-id=%s not found', $partId));
                }
            }

            $em->flush();

            return new JsonResponse([
                'success' => true,
                'count' => $count,
                'message' => $translator->trans('statistics.cleanup_assembly_bom_entries.success', [
                    '%count%' => $count,
                ]),
                'new_count' => $helper->getInvalidPartBOMEntriesCount(),
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => $translator->trans('statistics.cleanup_assembly_bom_entries.error') . ' ' . $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route(path: '/statistics/cleanup-assembly-preview-attachments', name: 'statistics_cleanup_assembly_preview_attachments', methods: ['POST'])]
    public function cleanupAssemblyPreviewAttachments(
        EntityManagerInterface $em,
        StatisticsHelper $helper,
        TranslatorInterface $translator
    ): JsonResponse {
        $this->denyAccessUnlessGranted('@tools.statistics');

        try {
            $qb = $em->createQueryBuilder();
            $qb->select('a')
                ->from(Assembly::class, 'a')
                ->leftJoin('a.master_picture_attachment', 'm')
                ->where('a.master_picture_attachment IS NOT NULL')
                ->andWhere('m.id IS NULL');

            $assemblies = $qb->getQuery()->getResult();
            $count = count($assemblies);

            foreach ($assemblies as $assembly) {
                if ($assembly instanceof Assembly) {
                    $assembly->setMasterPictureAttachment(null);
                }
            }

            $em->flush();

            return new JsonResponse([
                'success' => true,
                'count' => $count,
                'message' => $translator->trans('statistics.cleanup_assembly_preview_attachments.success', [
                    '%count%' => $count,
                ]),
                'new_count' => $helper->getInvalidAssemblyPreviewAttachmentsCount(),
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => $translator->trans('statistics.cleanup_assembly_preview_attachments.error') . ' ' . $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
