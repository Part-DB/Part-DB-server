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

namespace App\Controller;

use App\DataTables\ProjectBomEntriesDataTable;
use App\Entity\ProjectSystem\Project;
use Omines\DataTablesBundle\DataTableFactory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/project")
 */
class ProjectController extends AbstractController
{
    private DataTableFactory $dataTableFactory;

    public function __construct(DataTableFactory $dataTableFactory)
    {
        $this->dataTableFactory = $dataTableFactory;
    }

    /**
     * @Route("/{id}", name="project_info")
     */
    public function info(Project $project, Request $request)
    {
        $this->denyAccessUnlessGranted('read', $project);

        $table = $this->dataTableFactory->createFromType(ProjectBomEntriesDataTable::class, ['project' => $project])
            ->handleRequest($request);

        if ($table->isCallback()) {
            return $table->getResponse();
        }

        return $this->render('Projects/info.html.twig', [
            'datatable' => $table,
            'project' => $project,
        ]);
    }
}