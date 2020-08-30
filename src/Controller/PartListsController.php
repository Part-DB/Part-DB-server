<?php
/**
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 * Copyright (C) 2019 - 2020 Jan Böhmer (https://github.com/jbtronics)
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

declare(strict_types=1);

/**
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 * Copyright (C) 2019 Jan Böhmer (https://github.com/jbtronics)
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
use App\Entity\Parts\Footprint;
use App\Entity\Parts\Manufacturer;
use App\Entity\Parts\Storelocation;
use App\Entity\Parts\Supplier;
use App\Services\Parts\PartsTableActionHandler;
use Doctrine\ORM\EntityManagerInterface;
use Omines\DataTablesBundle\DataTableFactory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class PartListsController extends AbstractController
{
    private $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    /**
     * @Route("/table/action", name="table_action", methods={"POST"})
     */
    public function tableAction(Request $request, PartsTableActionHandler $actionHandler): Response
    {
        $redirect = $request->request->get('redirect_back');
        $ids = $request->request->get('ids');
        $action = $request->request->get('action');
        $target = $request->request->get('target');

        if (!$this->isCsrfTokenValid('table_action', $request->request->get('_token'))) {
            $this->addFlash('error', 'csfr_invalid');

            return $this->redirect($redirect);
        }

        if (null === $action || null === $ids) {
            $this->addFlash('error', 'part.table.actions.no_params_given');
        } else {
            $parts = $actionHandler->idStringToArray($ids);
            $actionHandler->handleAction($action, $parts, $target ? (int) $target : null);

            //Save changes
            $this->entityManager->flush();

            $this->addFlash('success', 'part.table.actions.success');
        }

        return $this->redirect($redirect);
    }

    /**
     * @Route("/category/{id}/parts", name="part_list_category")
     *
     * @return JsonResponse|Response
     */
    public function showCategory(Category $category, Request $request, DataTableFactory $dataTable)
    {
        $table = $dataTable->createFromType(PartsDataTable::class, ['category' => $category])
            ->handleRequest($request);

        if ($table->isCallback()) {
            return $table->getResponse();
        }

        return $this->render('Parts/lists/category_list.html.twig', [
            'datatable' => $table,
            'entity' => $category,
            'repo' => $this->entityManager->getRepository(Category::class),
        ]);
    }

    /**
     * @Route("/footprint/{id}/parts", name="part_list_footprint")
     *
     * @return JsonResponse|Response
     */
    public function showFootprint(Footprint $footprint, Request $request, DataTableFactory $dataTable)
    {
        $table = $dataTable->createFromType(PartsDataTable::class, ['footprint' => $footprint])
            ->handleRequest($request);

        if ($table->isCallback()) {
            return $table->getResponse();
        }

        return $this->render('Parts/lists/footprint_list.html.twig', [
            'datatable' => $table,
            'entity' => $footprint,
            'repo' => $this->entityManager->getRepository(Footprint::class),
        ]);
    }

    /**
     * @Route("/manufacturer/{id}/parts", name="part_list_manufacturer")
     *
     * @return JsonResponse|Response
     */
    public function showManufacturer(Manufacturer $manufacturer, Request $request, DataTableFactory $dataTable)
    {
        $table = $dataTable->createFromType(PartsDataTable::class, ['manufacturer' => $manufacturer])
            ->handleRequest($request);

        if ($table->isCallback()) {
            return $table->getResponse();
        }

        return $this->render('Parts/lists/manufacturer_list.html.twig', [
            'datatable' => $table,
            'entity' => $manufacturer,
            'repo' => $this->entityManager->getRepository(Manufacturer::class),
        ]);
    }

    /**
     * @Route("/store_location/{id}/parts", name="part_list_store_location")
     *
     * @return JsonResponse|Response
     */
    public function showStorelocation(Storelocation $storelocation, Request $request, DataTableFactory $dataTable)
    {
        $table = $dataTable->createFromType(PartsDataTable::class, ['storelocation' => $storelocation])
            ->handleRequest($request);

        if ($table->isCallback()) {
            return $table->getResponse();
        }

        return $this->render('Parts/lists/store_location_list.html.twig', [
            'datatable' => $table,
            'entity' => $storelocation,
            'repo' => $this->entityManager->getRepository(Storelocation::class),
        ]);
    }

    /**
     * @Route("/supplier/{id}/parts", name="part_list_supplier")
     *
     * @return JsonResponse|Response
     */
    public function showSupplier(Supplier $supplier, Request $request, DataTableFactory $dataTable)
    {
        $table = $dataTable->createFromType(PartsDataTable::class, ['supplier' => $supplier])
            ->handleRequest($request);

        if ($table->isCallback()) {
            return $table->getResponse();
        }

        return $this->render('Parts/lists/supplier_list.html.twig', [
            'datatable' => $table,
            'entity' => $supplier,
            'repo' => $this->entityManager->getRepository(Supplier::class),
        ]);
    }

    /**
     * @Route("/parts/by_tag/{tag}", name="part_list_tags")
     *
     * @return JsonResponse|Response
     */
    public function showTag(string $tag, Request $request, DataTableFactory $dataTable)
    {
        $table = $dataTable->createFromType(PartsDataTable::class, ['tag' => $tag])
            ->handleRequest($request);

        if ($table->isCallback()) {
            return $table->getResponse();
        }

        return $this->render('Parts/lists/tags_list.html.twig', [
            'tag' => $tag,
            'datatable' => $table,
        ]);
    }

    /**
     * @Route("/parts/search", name="parts_search")
     *
     * @return JsonResponse|Response
     */
    public function showSearch(Request $request, DataTableFactory $dataTable)
    {
        $search = $request->query->get('keyword', '');
        $search_options = [
            'name' => $request->query->getBoolean('name'),
            'description' => $request->query->getBoolean('description'),
            'comment' => $request->query->getBoolean('comment'),
            'category' => $request->query->getBoolean('category'),
            'store_location' => $request->query->getBoolean('storelocation'),
            'supplier' => $request->query->getBoolean('supplier'),
            'ordernr' => $request->query->getBoolean('ordernr'),
            'manufacturer' => $request->query->getBoolean('manufacturer'),
            'footprint' => $request->query->getBoolean('footprint'),
            'tags' => $request->query->getBoolean('tags'),
            'regex' => $request->query->getBoolean('regex'),
        ];

        $table = $dataTable->createFromType(PartsDataTable::class, [
            'search' => $search,
            'search_options' => $search_options,
        ])
            ->handleRequest($request);

        if ($table->isCallback()) {
            return $table->getResponse();
        }

        return $this->render('Parts/lists/search_list.html.twig', [
            'datatable' => $table,
            'keyword' => $search,
        ]);
    }

    /**
     * @Route("/parts", name="parts_show_all")
     *
     * @return JsonResponse|Response
     */
    public function showAll(Request $request, DataTableFactory $dataTable)
    {
        $table = $dataTable->createFromType(PartsDataTable::class)
            ->handleRequest($request);

        if ($table->isCallback()) {
            return $table->getResponse();
        }

        return $this->render('Parts/lists/all_list.html.twig', ['datatable' => $table]);
    }

    /**
     * @Route("/lowstock", name="parts_show_low_stock")
     *
     * @return JsonResponse|Response
     */
    public function showLowStock(Request $request, DataTableFactory $dataTable)
    {
        $table = $dataTable->createFromType(PartsDataTable::class, ['lowstock'=>true])
            ->handleRequest($request);

        if ($table->isCallback()) {
            return $table->getResponse();
        }

        return $this->render('Parts/lists/lowstock_list.html.twig', ['datatable' => $table]);
    }
}
