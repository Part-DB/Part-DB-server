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

namespace App\Controller;

use App\Services\ImportExportSystem\EntityExporter;
use App\Services\Parts\PartsTableActionHandler;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class PartImportExportController extends AbstractController
{
    private EntityManagerInterface $entityManager;
    private PartsTableActionHandler $partsTableActionHandler;

    public function __construct(EntityManagerInterface $entityManager, PartsTableActionHandler $partsTableActionHandler)
    {
        $this->entityManager = $entityManager;
        $this->partsTableActionHandler = $partsTableActionHandler;
    }

    /**
     * @Route("/parts/export", name="parts_export", methods={"GET"})
     * @return Response
     */
    public function exportParts(Request $request, EntityExporter $entityExporter): Response
    {
        $ids = $request->query->get('ids', '');
        $parts = $this->partsTableActionHandler->idStringToArray($ids);

        if (empty($parts)) {
            throw new \RuntimeException('No parts found!');
        }

        //Ensure that we have access to the parts
        foreach ($parts as $part) {
            $this->denyAccessUnlessGranted('read', $part);
        }

        return $entityExporter->exportEntityFromRequest($parts, $request);
    }
}