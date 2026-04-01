<?php
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

declare(strict_types=1);

namespace App\Controller;

use App\DataTables\ErrorDataTable;
use App\DataTables\Filters\PartFilter;
use App\DataTables\Filters\AssemblyFilter;
use App\DataTables\Filters\ProjectFilter;
use App\DataTables\AssemblyDataTable;
use App\DataTables\Filters\AssemblySearchFilter;
use App\DataTables\Filters\PartSearchFilter;
use App\DataTables\Filters\ProjectSearchFilter;
use App\Form\Filters\AssemblyFilterType;
use App\Form\Filters\PartFilterType;
use App\Form\Filters\ProjectFilterType;
use App\DataTables\PartsDataTable;
use App\DataTables\ProjectSearchDataTable;
use App\Entity\Parts\Category;
use App\Entity\Parts\Footprint;
use App\Entity\Parts\Manufacturer;
use App\Entity\Parts\Part;
use App\Entity\Parts\StorageLocation;
use App\Entity\Parts\Supplier;
use App\Exceptions\InvalidRegexException;
use App\Services\Parts\PartsTableActionHandler;
use App\Services\Trees\NodesListBuilder;
use App\Settings\BehaviorSettings\SidebarSettings;
use App\Settings\BehaviorSettings\TableSettings;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\ORM\EntityManagerInterface;
use Omines\DataTablesBundle\DataTableFactory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Contracts\Translation\TranslatorInterface;

use function Symfony\Component\Translation\t;

class PartListsController extends AbstractController
{
    public function __construct(private readonly EntityManagerInterface $entityManager,
        private readonly NodesListBuilder $nodesListBuilder,
        private readonly DataTableFactory $dataTableFactory,
        private readonly TranslatorInterface $translator,
        private readonly TableSettings $tableSettings,
        private readonly SidebarSettings $sidebarSettings,
    )
    {
    }

    /**
     * Gets the filter operator to use by default (INCLUDING_CHILDREN or =)
     * @return string
     */
    private function getFilterOperator(): string
    {
        return $this->sidebarSettings->dataStructureNodesTableIncludeChildren ? 'INCLUDING_CHILDREN' : '=';
    }

    #[Route(path: '/table/action', name: 'table_action', methods: ['POST'])]
    public function tableAction(Request $request, PartsTableActionHandler $actionHandler): Response
    {
        $this->denyAccessUnlessGranted('@parts.edit');

        $redirect = $request->request->get('redirect_back');
        $ids = $request->request->get('ids');
        $action = $request->request->get('action');
        $target = $request->request->get('target');
        $redirectResponse = null;

        if (!$this->isCsrfTokenValid('table_action', $request->request->get('_token'))) {
            $this->addFlash('error', 'csfr_invalid');

            return $this->redirect($redirect);
        }

        if (null === $action || null === $ids) {
            $this->addFlash('error', 'part.table.actions.no_params_given');
        } else {
            $errors = [];

            $parts = $actionHandler->idStringToArray($ids);
            $redirectResponse = $actionHandler->handleAction($action, $parts, $target ? (int) $target : null, $redirect, $errors);

            //Save changes
            $this->entityManager->flush();

            if (count($errors) === 0) {
                $this->addFlash('success', 'part.table.actions.success');
            } else {
                $this->addFlash('error', t('part.table.actions.error', ['%count%' => count($errors)]));
                //Create a flash message for each error
                foreach ($errors as $error) {
                    /** @var Part $part */
                    $part = $error['part'];

                    $this->addFlash('error',
                        t('part.table.actions.error_detail', [
                            '%part_name%' => $part->getName(),
                            '%part_id%' => $part->getID(),
                            '%message%' => $error['message']
                        ])
                    );
                }
            }
        }

        //If the action handler returned a response, we use it, otherwise we redirect back to the previous page.
        if ($redirectResponse !== null) {
            return $redirectResponse;
        }

        return $this->redirect($redirect);
    }

    /**
     * Disable the given form interface after creation of the form by removing and reattaching the form.
     */
    private function disableFormFieldAfterCreation(FormInterface $form, bool $disabled = true): void
    {
        $attrs = $form->getConfig()->getOptions();
        $attrs['disabled'] = $disabled;

        $parent = $form->getParent();
        if (!$parent instanceof FormInterface) {
            throw new \RuntimeException('This function can only be used on form fields that are children of another form!');
        }

        $parent->remove($form->getName());
        $parent->add($form->getName(), $form->getConfig()->getType()->getInnerType()::class, $attrs);
    }

    /**
     * Common implementation for the part list pages.
     * @param  Request  $request The request to parse
     * @param  string  $template  The template that should be rendered
     * @param  callable|null  $filter_changer  A function that is called with the filter object as parameter. This function can be used to customize the filter
     * @param  callable|null  $form_changer  A function that is called with the form object as parameter. This function can be used to customize the form
     * @param  array  $additonal_template_vars  Any additional template variables that should be passed to the template
     * @param  array  $additional_table_vars Any additional variables that should be passed to the table creation
     */
    protected function showListWithFilter(Request $request, string $template, ?callable $filter_changer = null, ?callable $form_changer = null, array $additonal_template_vars = [], array $additional_table_vars = []): Response
    {
        $this->denyAccessUnlessGranted('@parts.read');

        $formRequest = clone $request;
        $formRequest->setMethod('GET');

        $filterType = $additional_table_vars['filterType'] ?? PartFilter::class;
        unset($additional_table_vars['filterType']);

        if ($filterType === PartFilter::class) {
            $filter = new PartFilter($this->nodesListBuilder);
        } elseif ($filterType === AssemblyFilter::class) {
            $filter = new AssemblyFilter($this->nodesListBuilder);
        } elseif ($filterType === ProjectFilter::class) {
            $filter = new ProjectFilter($this->nodesListBuilder);
        } else {
            $filter = null;
        }

        if ($filter !== null && $filter_changer !== null) {
            $filter_changer($filter);
        }

        //If we are in a post request for the tables, we only have to apply the filter form if the submit query param was set
        //This saves us some time from creating this complicated term on simple list pages, where no special filter is applied
        $filterForm = null;
        if ($request->getMethod() !== 'POST' || $request->query->has('part_filter') || $request->query->has('assembly_filter') || $request->query->has('project_filter') || $request->query->has('submit')) {
            $formType = match ($filterType) {
                PartFilter::class => PartFilterType::class,
                AssemblyFilter::class => AssemblyFilterType::class,
                ProjectFilter::class => ProjectFilterType::class,
                default => null,
            };

            if ($formType !== null) {
                $filterForm = $this->createForm($formType, $filter, ['method' => 'GET']);
                if ($form_changer !== null) {
                    $form_changer($filterForm);
                }

                $filterForm->handleRequest($formRequest);
            }
        }

        $dataTableType = $additional_table_vars['dataTableType'] ?? PartsDataTable::class;
        unset($additional_table_vars['dataTableType']);

        $tableOptions = array_merge(
            ['filter' => $filter], $additional_table_vars);

        $table = $this->dataTableFactory->createFromType($dataTableType, $tableOptions,
            ['pageLength' => $this->tableSettings->fullDefaultPageSize, 'lengthMenu' => PartsDataTable::LENGTH_MENU])
            ->handleRequest($request);

        if ($table->isCallback()) {
            try {
                try {
                    return $table->getResponse();
                } catch (DriverException $driverException) {
                    if ($driverException->getCode() === 1139) {
                        //Convert the driver exception to InvalidRegexException so it has the same hanlder as for SQLite
                        throw InvalidRegexException::fromDriverException($driverException);
                    } else {
                        throw $driverException;
                    }
                }
            } catch (InvalidRegexException $exception) {
                $errors = $this->translator->trans('part.table.invalid_regex').': '.$exception->getReason();
                return ErrorDataTable::errorTable($this->dataTableFactory, $request, $errors);
            }
        }

        return $this->render($template, array_merge([
            'datatable' => $table,
            'filterForm' => $filterForm?->createView(),
        ], $additonal_template_vars));
    }

    #[Route(path: '/category/{id}/parts', name: 'part_list_category')]
    public function showCategory(Category $category, Request $request): Response
    {
        $this->denyAccessUnlessGranted('@categories.read');

        return $this->showListWithFilter($request,
            'parts/lists/category_list.html.twig',
            function (PartFilter $filter) use ($category) {
                $filter->category->setOperator($this->getFilterOperator())->setValue($category);
            }, function (FormInterface $filterForm) {
                $this->disableFormFieldAfterCreation($filterForm->get('category')->get('value'));
            }, [
                'entity' => $category,
                'repo' => $this->entityManager->getRepository(Category::class),
            ]
        );
    }

    #[Route(path: '/footprint/{id}/parts', name: 'part_list_footprint')]
    public function showFootprint(Footprint $footprint, Request $request): Response
    {
        $this->denyAccessUnlessGranted('@footprints.read');

        return $this->showListWithFilter($request,
            'parts/lists/footprint_list.html.twig',
            function (PartFilter $filter) use ($footprint) {
                $filter->footprint->setOperator($this->getFilterOperator())->setValue($footprint);
            }, function (FormInterface $filterForm) {
                $this->disableFormFieldAfterCreation($filterForm->get('footprint')->get('value'));
            }, [
                'entity' => $footprint,
                'repo' => $this->entityManager->getRepository(Footprint::class),
            ]
        );
    }

    #[Route(path: '/manufacturer/{id}/parts', name: 'part_list_manufacturer')]
    public function showManufacturer(Manufacturer $manufacturer, Request $request): Response
    {
        $this->denyAccessUnlessGranted('@manufacturers.read');

        return $this->showListWithFilter($request,
            'parts/lists/manufacturer_list.html.twig',
            function (PartFilter $filter) use ($manufacturer) {
                $filter->manufacturer->setOperator($this->getFilterOperator())->setValue($manufacturer);
            }, function (FormInterface $filterForm) {
                $this->disableFormFieldAfterCreation($filterForm->get('manufacturer')->get('value'));
            }, [
                'entity' => $manufacturer,
                'repo' => $this->entityManager->getRepository(Manufacturer::class),
            ]
        );
    }

    #[Route(path: '/store_location/{id}/parts', name: 'part_list_store_location')]
    public function showStorelocation(StorageLocation $storelocation, Request $request): Response
    {
        $this->denyAccessUnlessGranted('@storelocations.read');

        return $this->showListWithFilter($request,
            'parts/lists/store_location_list.html.twig',
            function (PartFilter $filter) use ($storelocation) {
                $filter->storelocation->setOperator($this->getFilterOperator())->setValue($storelocation);
            }, function (FormInterface $filterForm) {
                $this->disableFormFieldAfterCreation($filterForm->get('storelocation')->get('value'));
            }, [
                'entity' => $storelocation,
                'repo' => $this->entityManager->getRepository(StorageLocation::class),
            ]
        );
    }

    #[Route(path: '/supplier/{id}/parts', name: 'part_list_supplier')]
    public function showSupplier(Supplier $supplier, Request $request): Response
    {
        $this->denyAccessUnlessGranted('@suppliers.read');

        return $this->showListWithFilter($request,
            'parts/lists/supplier_list.html.twig',
            function (PartFilter $filter) use ($supplier) {
                $filter->supplier->setOperator($this->getFilterOperator())->setValue($supplier);
            }, function (FormInterface $filterForm) {
                $this->disableFormFieldAfterCreation($filterForm->get('supplier')->get('value'));
            }, [
                'entity' => $supplier,
                'repo' => $this->entityManager->getRepository(Supplier::class),
            ]
        );
    }

    #[Route(path: '/parts/by_tag/{tag}', name: 'part_list_tags', requirements: ['tag' => '.*'])]
    public function showTag(string $tag, Request $request): Response
    {
        $tag = trim($tag);

        return $this->showListWithFilter($request,
            'parts/lists/tags_list.html.twig',
            function (PartFilter $filter) use ($tag) {
                $filter->tags->setOperator('ANY')->setValue($tag);
            }, function (FormInterface $filterForm) {
                $this->disableFormFieldAfterCreation($filterForm->get('tags')->get('value'));
            }, [
                'tag' => $tag,
            ]
        );
    }

    /**
     * @return PartSearchFilter|AssemblySearchFilter|ProjectSearchFilter
     */
    private function searchRequestToFilter(Request $request): object
    {
        $datasource = $request->query->get('datasource', 'parts');
        $keyword = $request->query->get('keyword', '');

        if ($datasource === 'assemblies') {
            $filter = new AssemblySearchFilter($keyword);
            $filter->setDatasource($datasource);
            $filter->setRegex($request->query->getBoolean('regex'));

            //As an unchecked checkbox is not set in the query, the default value for all bools have to be false (which is the default argument value)!
            //But if we are coming from a simple search (without search options set), we want to search in all fields by default
            if ($request->query->has('name') || $request->query->has('description') || $request->query->has('comment') || $request->query->has('ipn') || $request->query->has('category') || $request->query->has('status') || $request->query->has('dbid')) {
                $filter->setName($request->query->getBoolean('name'));
                $filter->setDescription($request->query->getBoolean('description'));
                $filter->setComment($request->query->getBoolean('comment') || $request->query->getBoolean('notes'));
                $filter->setIPN($request->query->getBoolean('ipn'));
                $filter->setCategory($request->query->getBoolean('category'));
                $filter->setStatus($request->query->getBoolean('status'));
                $filter->setDbId($request->query->getBoolean('dbid'));
            } else {
                //Simple search: search in all fields
                $filter->setName(true);
                $filter->setDescription(true);
                $filter->setComment(true);
                $filter->setIPN(true);
                $filter->setStatus(true);
            }

            return $filter;
        }

        if ($datasource === 'projects') {
            $filter = new ProjectSearchFilter($keyword);
            $filter->setDatasource($datasource);
            $filter->setRegex($request->query->getBoolean('regex'));

            //As an unchecked checkbox is not set in the query, the default value for all bools have to be false (which is the default argument value)!
            if ($request->query->has('name') || $request->query->has('description') || $request->query->has('comment') || $request->query->has('notes') || $request->query->has('category') || $request->query->has('status') || $request->query->has('dbid')) {
                $filter->setName($request->query->getBoolean('name'));
                $filter->setDescription($request->query->getBoolean('description'));
                $filter->setComment($request->query->getBoolean('comment') || $request->query->getBoolean('notes'));
                $filter->setCategory($request->query->getBoolean('category'));
                $filter->setStatus($request->query->getBoolean('status'));
                $filter->setDbId($request->query->getBoolean('dbid'));
            } else {
                //Simple search: search in all fields
                $filter->setName(true);
                $filter->setDescription(true);
                $filter->setComment(true);
                $filter->setStatus(true);
            }

            return $filter;
        }

        $filter = new PartSearchFilter($keyword);
        $filter->setDatasource($datasource);

        //As an unchecked checkbox is not set in the query, the default value for all bools have to be false (which is the default argument value)!
        if ($request->query->has('name') || $request->query->has('description') || $request->query->has('comment') || $request->query->has('ipn') || $request->query->has('category') || $request->query->has('dbid') || $request->query->has('mpn') || $request->query->has('tags') || $request->query->has('storelocation') || $request->query->has('ordernr') || $request->query->has('supplier') || $request->query->has('manufacturer') || $request->query->has('footprint') || $request->query->has('assembly') || $request->query->has('manufacturing_status')) {
            $filter->setName($request->query->getBoolean('name'));
            $filter->setDbId($request->query->getBoolean('dbid'));
            $filter->setCategory($request->query->getBoolean('category'));
            $filter->setDescription($request->query->getBoolean('description'));
            $filter->setMpn($request->query->getBoolean('mpn'));
            $filter->setTags($request->query->getBoolean('tags'));
            $filter->setStorelocation($request->query->getBoolean('storelocation'));
            $filter->setComment($request->query->getBoolean('comment'));
            $filter->setManufacturingStatus($request->query->getBoolean('manufacturing_status'));
            $filter->setIPN($request->query->getBoolean('ipn'));
            $filter->setOrdernr($request->query->getBoolean('ordernr'));
            $filter->setSupplier($request->query->getBoolean('supplier'));
            $filter->setManufacturer($request->query->getBoolean('manufacturer'));
            $filter->setFootprint($request->query->getBoolean('footprint'));
            $filter->setAssembly($request->query->getBoolean('assembly'));
        } else {
            //Simple search: search in all fields
            $filter->setName(true);
            $filter->setCategory(true);
            $filter->setDescription(true);
            $filter->setComment(true);
            $filter->setManufacturingStatus(true);
            $filter->setTags(true);
            $filter->setStorelocation(true);
            $filter->setOrdernr(true);
            $filter->setMpn(true);
            $filter->setIPN(true);
            $filter->setAssembly(true);
        }

        $filter->setRegex($request->query->getBoolean('regex'));

        return $filter;
    }

    #[Route(path: '/parts/search', name: 'parts_search')]
    public function showSearch(Request $request, DataTableFactory $dataTable): Response
    {
        $searchFilter = $this->searchRequestToFilter($request);
        $datasource = $request->query->get('datasource', 'parts');

        $dataTableType = PartsDataTable::class;
        $template = 'parts/lists/search_list.html.twig';
        $filterType = PartFilter::class;

        if ($searchFilter instanceof AssemblySearchFilter) {
            $dataTableType = AssemblyDataTable::class;
            $filterType = AssemblyFilter::class;
        } elseif ($searchFilter instanceof ProjectSearchFilter) {
            $dataTableType = ProjectSearchDataTable::class;
            $filterType = ProjectFilter::class;
        }

        return $this->showListWithFilter($request,
            $template,
            null,
            null,
            [
                'keyword' => $searchFilter->getKeyword(),
                'searchFilter' => $searchFilter,
                'dataTableType' => $dataTableType,
                'datasource' => $datasource,
            ],
            [
                'search' => $searchFilter,
                'dataTableType' => $dataTableType,
                'filterType' => $filterType,
            ]
        );
    }

    #[Route(path: '/parts', name: 'parts_show_all')]
    public function showAll(Request $request): Response
    {
        return $this->showListWithFilter($request,'parts/lists/all_list.html.twig');
    }
}
