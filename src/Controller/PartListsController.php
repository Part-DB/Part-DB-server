<?php
/**
 * part-db version 0.1
 * Copyright (C) 2005 Christoph Lechner
 * http://www.cl-projects.de/.
 *
 * part-db version 0.2+
 * Copyright (C) 2009 K. Jacobs and others (see authors.php)
 * http://code.google.com/p/part-db/
 *
 * Part-DB Version 0.4+
 * Copyright (C) 2016 - 2019 Jan Böhmer
 * https://github.com/jbtronics
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA
 */

namespace App\Controller;

use App\DataTables\PartsDataTable;
use App\Entity\Parts\Category;
use Omines\DataTablesBundle\DataTableFactory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class PartListsController extends AbstractController
{
    /**
     * @Route("/category/{id}/parts")
     *
     * @param $id int The id of the category
     *
     * @return \Symfony\Component\HttpFoundation\JsonResponse|\Symfony\Component\HttpFoundation\Response
     */
    public function showCategory(Category $category, Request $request, DataTableFactory $dataTable)
    {
        $table = $dataTable->createFromType(PartsDataTable::class, ['category' => $category])
                    ->handleRequest($request);

        if ($table->isCallback()) {
            return $table->getResponse();
        }

        return $this->render('parts_list.html.twig', ['datatable' => $table]);
    }

    /**
     * @Route("/parts/by_tag/{tag}", name="part_list_tags")
     * @param string $tag
     * @param Request $request
     * @param DataTableFactory $dataTable
     * @return \Symfony\Component\HttpFoundation\JsonResponse|\Symfony\Component\HttpFoundation\Response
     */
    public function showTag(string $tag, Request $request, DataTableFactory $dataTable)
    {
        $table = $dataTable->createFromType(PartsDataTable::class, ['tag' => $tag])
            ->handleRequest($request);

        if ($table->isCallback()) {
            return $table->getResponse();
        }

        return $this->render('parts_list.html.twig', ['datatable' => $table]);
    }

    /**
     * @Route("/parts/search/{keyword}", name="parts_search")
     */
    public function showSearch(Request $request, DataTableFactory $dataTable, string $keyword = "")
    {
        $search = $keyword;

        $table = $dataTable->createFromType(PartsDataTable::class, ['search' => $search])
            ->handleRequest($request);

        if ($table->isCallback()) {
            return $table->getResponse();
        }

        return $this->render('parts_list.html.twig', ['datatable' => $table]);
    }

    /**
     * @Route("/parts", name="parts_show_all")
     *
     * @param Request          $request
     * @param DataTableFactory $dataTable
     *
     * @return \Symfony\Component\HttpFoundation\JsonResponse|\Symfony\Component\HttpFoundation\Response
     */
    public function showAll(Request $request, DataTableFactory $dataTable)
    {
        $table = $dataTable->createFromType(PartsDataTable::class)
            ->handleRequest($request);

        if ($table->isCallback()) {
            return $table->getResponse();
        }

        return $this->render('parts_list.html.twig', ['datatable' => $table]);
    }
}
