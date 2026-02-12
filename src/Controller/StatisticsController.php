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
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

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
    public function cleanupAssemblyBOMEntries(EntityManagerInterface $em): JsonResponse
    {
        $this->denyAccessUnlessGranted('@tools.statistics');

        $qb = $em->createQueryBuilder();
        $qb->select('be', 'IDENTITY(be.part) AS part_id')
            ->from(AssemblyBOMEntry::class, 'be')
            ->leftJoin('be.part', 'p')
            ->where('be.part IS NOT NULL')
            ->andWhere('p.id IS NULL');

        $results = $qb->getQuery()->getResult();
        $count = count($results);

        foreach ($results as $result) {
            /** @var AssemblyBOMEntry $entry */
            $entry = $result[0];
            $part_id = $result['part_id'] ?? 'unknown';

            $entry->setPart(null);
            $entry->setName(sprintf('part-id=%s not found', $part_id));
        }

        $em->flush();

        return new JsonResponse(['success' => true, 'count' => $count]);
    }
}
