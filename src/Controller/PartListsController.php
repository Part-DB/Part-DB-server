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

use App\DataTables\Filters\PartFilter;
use App\DataTables\Filters\PartSearchFilter;
use App\DataTables\PartsDataTable;
use App\Entity\Parts\Category;
use App\Entity\Parts\Footprint;
use App\Entity\Parts\Manufacturer;
use App\Entity\Parts\Storelocation;
use App\Entity\Parts\Supplier;
use App\Form\Filters\PartFilterType;
use App\Services\Parts\PartsTableActionHandler;
use App\Services\Trees\NodesListBuilder;
use Doctrine\ORM\EntityManagerInterface;
use Omines\DataTablesBundle\DataTableFactory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class PartListsController extends AbstractController
{
    private $entityManager;
    private $nodesListBuilder;
    private $dataTableFactory;

    public function __construct(EntityManagerInterface $entityManager, NodesListBuilder $nodesListBuilder, DataTableFactory $dataTableFactory)
    {
        $this->entityManager = $entityManager;
        $this->nodesListBuilder = $nodesListBuilder;
        $this->dataTableFactory = $dataTableFactory;
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
     * Disable the given form interface after creation of the form by removing and reattaching the form.
     * @param  FormInterface  $form
     * @return void
     */
    private function disableFormFieldAfterCreation(FormInterface $form, bool $disabled = true): void
    {
        $attrs = $form->getConfig()->getOptions();
        $attrs['disabled'] = $disabled;

        $parent = $form->getParent();
        if ($parent === null) {
            throw new \RuntimeException('This function can only be used on form fields that are children of another form!');
        }

        $parent->remove($form->getName());
        $parent->add($form->getName(), get_class($form->getConfig()->getType()->getInnerType()), $attrs);
    }

    /**
     * Common implementation for the part list pages.
     * @param  Request  $request The request to parse
     * @param  string  $template  The template that should be rendered
     * @param  callable|null  $filter_changer  A function that is called with the filter object as parameter. This function can be used to customize the filter
     * @param  callable|null  $form_changer  A function that is called with the form object as parameter. This function can be used to customize the form
     * @param  array  $additonal_template_vars  Any additional template variables that should be passed to the template
     * @param  array  $additional_table_vars Any additional variables that should be passed to the table creation
     * @return Response
     */
    protected function showListWithFilter(Request $request, string $template, ?callable $filter_changer = null, ?callable $form_changer = null, array $additonal_template_vars = [], array $additional_table_vars = []): Response
    {
        $formRequest = clone $request;
        $formRequest->setMethod('GET');
        $filter = new PartFilter($this->nodesListBuilder);
        if($filter_changer !== null){
            $filter_changer($filter);
        }

        $filterForm = $this->createForm(PartFilterType::class, $filter, ['method' => 'GET']);
        if($form_changer !== null) {
            $form_changer($filterForm);
        }

        $filterForm->handleRequest($formRequest);

        $table = $this->dataTableFactory->createFromType(PartsDataTable::class, array_merge(['filter' => $filter], $additional_table_vars))
            ->handleRequest($request);

        if ($table->isCallback()) {
            return $table->getResponse();
        }

        return $this->render($template, array_merge([
            'datatable' => $table,
            'filterForm' => $filterForm->createView(),
        ], $additonal_template_vars));
    }

    /**
     * @Route("/category/{id}/parts", name="part_list_category")
     *
     * @return JsonResponse|Response
     */
    public function showCategory(Category $category, Request $request)
    {
        return $this->showListWithFilter($request,
            'Parts/lists/category_list.html.twig',
            function (PartFilter $filter) use ($category) {
                $filter->getCategory()->setOperator('INCLUDING_CHILDREN')->setValue($category);
            }, function (FormInterface $filterForm) {
                $this->disableFormFieldAfterCreation($filterForm->get('category')->get('value'));
            }, [
                'entity' => $category,
                'repo' => $this->entityManager->getRepository(Category::class),
            ]
        );
    }

    /**
     * @Route("/footprint/{id}/parts", name="part_list_footprint")
     *
     * @return JsonResponse|Response
     */
    public function showFootprint(Footprint $footprint, Request $request)
    {
        return $this->showListWithFilter($request,
            'Parts/lists/footprint_list.html.twig',
            function (PartFilter $filter) use ($footprint) {
                $filter->getFootprint()->setOperator('INCLUDING_CHILDREN')->setValue($footprint);
            }, function (FormInterface $filterForm) {
                $this->disableFormFieldAfterCreation($filterForm->get('footprint')->get('value'));
            }, [
                'entity' => $footprint,
                'repo' => $this->entityManager->getRepository(Footprint::class),
            ]
        );
    }

    /**
     * @Route("/manufacturer/{id}/parts", name="part_list_manufacturer")
     *
     * @return JsonResponse|Response
     */
    public function showManufacturer(Manufacturer $manufacturer, Request $request)
    {
        return $this->showListWithFilter($request,
            'Parts/lists/manufacturer_list.html.twig',
            function (PartFilter $filter) use ($manufacturer) {
                $filter->getManufacturer()->setOperator('INCLUDING_CHILDREN')->setValue($manufacturer);
            }, function (FormInterface $filterForm) {
                $this->disableFormFieldAfterCreation($filterForm->get('manufacturer')->get('value'));
            }, [
                'entity' => $manufacturer,
                'repo' => $this->entityManager->getRepository(Manufacturer::class),
            ]
        );
    }

    /**
     * @Route("/store_location/{id}/parts", name="part_list_store_location")
     *
     * @return JsonResponse|Response
     */
    public function showStorelocation(Storelocation $storelocation, Request $request)
    {
        return $this->showListWithFilter($request,
            'Parts/lists/store_location_list.html.twig',
            function (PartFilter $filter) use ($storelocation) {
                $filter->getStorelocation()->setOperator('INCLUDING_CHILDREN')->setValue($storelocation);
            }, function (FormInterface $filterForm) {
                $this->disableFormFieldAfterCreation($filterForm->get('storelocation')->get('value'));
            }, [
                'entity' => $storelocation,
                'repo' => $this->entityManager->getRepository(Storelocation::class),
            ]
        );
    }

    /**
     * @Route("/supplier/{id}/parts", name="part_list_supplier")
     *
     * @return JsonResponse|Response
     */
    public function showSupplier(Supplier $supplier, Request $request)
    {
        return $this->showListWithFilter($request,
            'Parts/lists/supplier_list.html.twig',
            function (PartFilter $filter) use ($supplier) {
                $filter->getSupplier()->setOperator('INCLUDING_CHILDREN')->setValue($supplier);
            }, function (FormInterface $filterForm) {
                $this->disableFormFieldAfterCreation($filterForm->get('supplier')->get('value'));
            }, [
                'entity' => $supplier,
                'repo' => $this->entityManager->getRepository(Supplier::class),
            ]
        );
    }

    /**
     * @Route("/parts/by_tag/{tag}", name="part_list_tags", requirements={"tag": ".*"})
     *
     * @return JsonResponse|Response
     */
    public function showTag(string $tag, Request $request, DataTableFactory $dataTable)
    {
        $tag = trim($tag);

        return $this->showListWithFilter($request,
            'Parts/lists/tags_list.html.twig',
            function (PartFilter $filter) use ($tag) {
                $filter->getTags()->setOperator('ANY')->setValue($tag);
            }, function (FormInterface $filterForm) {
                $this->disableFormFieldAfterCreation($filterForm->get('tags')->get('value'));
            }, [
                'tag' => $tag,
            ]
        );
    }

    private function searchRequestToFilter(Request $request): PartSearchFilter
    {
        $filter = new PartSearchFilter($request->query->get('keyword', ''));

        $filter->setName($request->query->getBoolean('name', true));
        $filter->setCategory($request->query->getBoolean('category', true));
        $filter->setDescription($request->query->getBoolean('description', true));
        $filter->setTags($request->query->getBoolean('tags', true));
        $filter->setStorelocation($request->query->getBoolean('storelocation', true));
        $filter->setComment($request->query->getBoolean('comment', true));
        $filter->setOrdernr($request->query->getBoolean('ordernr', true));
        $filter->setSupplier($request->query->getBoolean('supplier', false));
        $filter->setManufacturer($request->query->getBoolean('manufacturer', false));
        $filter->setFootprint($request->query->getBoolean('footprint', false));

        $filter->setRegex($request->query->getBoolean('regex', false));

        return $filter;
    }

    /**
     * @Route("/parts/search", name="parts_search")
     *
     * @return JsonResponse|Response
     */
    public function showSearch(Request $request, DataTableFactory $dataTable)
    {
        $searchFilter = $this->searchRequestToFilter($request);

        return $this->showListWithFilter($request,
            'Parts/lists/search_list.html.twig',
            null,
            null,
            [
                'keyword' => $searchFilter->getKeyword(),
                'searchFilter' => $searchFilter,
            ],
            [
                'search' => $searchFilter,
            ]
        );
    }

    /**
     * @Route("/parts", name="parts_show_all")
     *
     * @return JsonResponse|Response
     */
    public function showAll(Request $request, DataTableFactory $dataTable)
    {
        return $this->showListWithFilter($request,'Parts/lists/all_list.html.twig');
    }
}
