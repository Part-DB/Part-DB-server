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

namespace App\DataTables;

use App\DataTables\Adapters\TwoStepORMAdapter;
use App\DataTables\Column\EntityColumn;
use App\DataTables\Column\EnumColumn;
use App\DataTables\Column\IconLinkColumn;
use App\DataTables\Column\LocaleDateTimeColumn;
use App\DataTables\Column\MarkdownColumn;
use App\DataTables\Column\PartAttachmentsColumn;
use App\DataTables\Column\PrettyBoolColumn;
use App\DataTables\Column\RowClassColumn;
use App\DataTables\Column\SelectColumn;
use App\DataTables\Column\SIUnitNumberColumn;
use App\DataTables\Column\TagsColumn;
use App\DataTables\DTO\PartDTO;
use App\DataTables\DTO\PartDTOHydrator;
use App\DataTables\Filters\PartFilter;
use App\DataTables\Filters\PartSearchFilter;
use App\DataTables\Helpers\ColumnSortHelper;
use App\DataTables\Helpers\PartDataTableHelper;
use App\Doctrine\Helpers\FieldHelper;
use App\Entity\Parts\ManufacturingStatus;
use App\Entity\Parts\Part;
use App\Entity\Parts\PartLot;
use App\Entity\ProjectSystem\Project;
use App\Services\EntityURLGenerator;
use App\Services\Formatters\AmountFormatter;
use App\Settings\BehaviorSettings\TableSettings;
use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Omines\DataTablesBundle\Adapter\Doctrine\ORM\SearchCriteriaProvider;
use Omines\DataTablesBundle\Column\TextColumn;
use Omines\DataTablesBundle\DataTable;
use Omines\DataTablesBundle\DataTableTypeInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

final class PartsDataTable implements DataTableTypeInterface
{
    const LENGTH_MENU = [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]];

    public function __construct(
        private readonly EntityURLGenerator $urlGenerator,
        private readonly TranslatorInterface $translator,
        private readonly AmountFormatter $amountFormatter,
        private readonly PartDataTableHelper $partDataTableHelper,
        private readonly Security $security,
        private readonly ColumnSortHelper $csh,
        private readonly TableSettings $tableSettings,
        private readonly PartDTOHydrator $partDTOHydrator,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function configureOptions(OptionsResolver $optionsResolver): void
    {
        $optionsResolver->setDefaults([
            'filter' => null,
            'search' => null
        ]);

        $optionsResolver->setAllowedTypes('filter', [PartFilter::class, 'null']);
        $optionsResolver->setAllowedTypes('search', [PartSearchFilter::class, 'null']);
    }

    public function configure(DataTable $dataTable, array $options): void
    {
        $resolver = new OptionsResolver();
        $this->configureOptions($resolver);
        $options = $resolver->resolve($options);

        $this->csh
            //Color the table rows depending on the review and favorite status
            ->add('row_color', RowClassColumn::class, [
                'render' => function ($value, $context): string {
                    if ($context->isNeedsReview()) {
                        return 'table-secondary';
                    }
                    if ($context->isFavorite()) {
                        return 'table-info';
                    }

                    return ''; //Default coloring otherwise
                },
            ], visibility_configurable: false)
            ->add('select', SelectColumn::class, visibility_configurable: false)
            ->add('picture', TextColumn::class, [
                'label' => '',
                'className' => 'no-colvis',
                'render' => fn($value, $context) => $this->partDataTableHelper->renderPicture($context),
            ], visibility_configurable: false)
            ->add('name', TextColumn::class, [
                'label' => $this->translator->trans('part.table.name'),
                'render' => fn($value, $context) => $this->partDataTableHelper->renderName($context),
                'orderField' => 'NATSORT(part.name)'
            ])
            ->add('id', TextColumn::class, [
                'label' => $this->translator->trans('part.table.id'),
            ])
            ->add('ipn', TextColumn::class, [
                'label' => $this->translator->trans('part.table.ipn'),
                'orderField' => 'NATSORT(part.ipn)'
            ])
            ->add('description', MarkdownColumn::class, [
                'label' => $this->translator->trans('part.table.description'),
            ])
            ->add('category', EntityColumn::class, [
                'label' => $this->translator->trans('part.table.category'),
                'property' => 'category',
                'orderField' => 'NATSORT(_category.name)'
            ])
            ->add('footprint', EntityColumn::class, [
                'property' => 'footprint',
                'label' => $this->translator->trans('part.table.footprint'),
                'orderField' => 'NATSORT(_footprint.name)'
            ])
            ->add('manufacturer', EntityColumn::class, [
                'property' => 'manufacturer',
                'label' => $this->translator->trans('part.table.manufacturer'),
                'orderField' => 'NATSORT(_manufacturer.name)'
            ])
            ->add('storelocation', TextColumn::class, [
                'label' => $this->translator->trans('part.table.storeLocations'),
                //We need to use a aggregate function to get the first store location, as we have a one-to-many relation
                'orderField' => 'NATSORT(MIN(_storelocations.name))',
                'render' => fn($value, $context) => $this->partDataTableHelper->renderStorageLocations($context),
            ], alias: 'storage_location')

            ->add('amount', TextColumn::class, [
                'label' => $this->translator->trans('part.table.amount'),
                'render' => fn($value, $context) => $this->partDataTableHelper->renderAmount($context),
                'orderField' => 'amountSum'
            ])
            ->add('minamount', TextColumn::class, [
                'label' => $this->translator->trans('part.table.minamount'),
                'render' => fn($value, $context): string => htmlspecialchars($this->amountFormatter->format(
                    $value,
                    $context->getPartUnit()
                )),
            ])
            ->add('partUnit', TextColumn::class, [
                'label' => $this->translator->trans('part.table.partUnit'),
                'orderField' => 'NATSORT(_partUnit.name)',
                'render' => function ($value, $context): string {
                    $partUnit = $context->getPartUnit();
                    if ($partUnit === null) {
                        return '';
                    }

                    $tmp = htmlspecialchars($partUnit->getName());

                    if ($partUnit->getUnit()) {
                        $tmp .= ' (' . htmlspecialchars($partUnit->getUnit()) . ')';
                    }
                    return $tmp;
                }
            ])
            ->add('partCustomState', TextColumn::class, [
                'label' => $this->translator->trans('part.table.partCustomState'),
                'orderField' => 'NATSORT(_partCustomState.name)',
                'render' => function($value, $context): string {
                    $partCustomState = $context->getPartCustomState();

                    if ($partCustomState === null) {
                        return '';
                    }

                    return htmlspecialchars($partCustomState->getName());
                }
            ])
            ->add('addedDate', LocaleDateTimeColumn::class, [
                'label' => $this->translator->trans('part.table.addedDate'),
            ])
            ->add('lastModified', LocaleDateTimeColumn::class, [
                'label' => $this->translator->trans('part.table.lastModified'),
            ])
            ->add('needs_review', PrettyBoolColumn::class, [
                'label' => $this->translator->trans('part.table.needsReview'),
            ])
            ->add('favorite', PrettyBoolColumn::class, [
                'label' => $this->translator->trans('part.table.favorite'),
            ])
            ->add('manufacturing_status', EnumColumn::class, [
                'label' => $this->translator->trans('part.table.manufacturingStatus'),
                'class' => ManufacturingStatus::class,
                'render' => function (?ManufacturingStatus $status, $context): string {
                    if ($status === null) {
                        return '';
                    }

                    return $this->translator->trans($status->toTranslationKey());
                },
            ])
            ->add('manufacturer_product_number', TextColumn::class, [
                'label' => $this->translator->trans('part.table.mpn'),
                'orderField' => 'NATSORT(part.manufacturer_product_number)'
            ])
            ->add('mass', SIUnitNumberColumn::class, [
                'label' => $this->translator->trans('part.table.mass'),
                'unit' => 'g'
            ])
            ->add('gtin', TextColumn::class, [
                'label' => $this->translator->trans('part.table.gtin'),
                'orderField' => 'NATSORT(part.gtin)'
            ])
            ->add('tags', TagsColumn::class, [
                'label' => $this->translator->trans('part.table.tags'),
            ])
            ->add('attachments', PartAttachmentsColumn::class, [
                'label' => $this->translator->trans('part.table.attachments'),
            ]);

        //Add a column to list the projects where the part is used, when the user has the permission to see the projects
        if ($this->security->isGranted('read', Project::class)) {
            $this->csh->add('projects', TextColumn::class, [
                'label' => $this->translator->trans('project.labelp'),
                'render' => function ($value, $context): string {
                    //Only show the first 5 projects names
                    $projects = $context->getProjects();
                    $tmp = "";

                    $max = 5;

                    for ($i = 0; $i < min($max, count($projects)); $i++) {
                        $project = $projects[$i];
                        
                        // For DTO, projects are arrays with id and name
                        if (is_array($project)) {
                            $projectProxy = $this->entityManager->getReference(Project::class, $project['id']);
                            $url = $this->urlGenerator->infoURL($projectProxy);
                            $tmp .= sprintf('<a href="%s">%s</a>', $url, htmlspecialchars($project['name']));
                        } else {
                            // For Part entity, projects are Project objects
                            $url = $this->urlGenerator->infoURL($project);
                            $tmp .= sprintf('<a href="%s">%s</a>', $url, htmlspecialchars($project->getName()));
                        }
                        
                        if ($i < count($projects) - 1) {
                            $tmp .= ", ";
                        }
                    }

                    if (count($projects) > $max) {
                        $tmp .= ", + " . (count($projects) - $max);
                    }

                    return $tmp;
                }
            ]);
        }

        $this->csh
            ->add('edit', IconLinkColumn::class, [
                'label' => $this->translator->trans('part.table.edit'),
                'href' => function ($value, $context) {
                    // For DTO, get a Part reference for URL generation
                    if ($context instanceof PartDTO) {
                        $partProxy = $this->entityManager->getReference(Part::class, $context->getId());
                        return $this->urlGenerator->editURL($partProxy);
                    }
                    return $this->urlGenerator->editURL($context);
                },
                'disabled' => function ($value, $context) {
                    // For DTO, get a Part reference for permission check
                    if ($context instanceof PartDTO) {
                        $partProxy = $this->entityManager->getReference(Part::class, $context->getId());
                        return !$this->security->isGranted('edit', $partProxy);
                    }
                    return !$this->security->isGranted('edit', $context);
                },
                'title' => $this->translator->trans('part.table.edit.title'),
            ]);

        //Apply the user configured order and visibility and add the columns to the table
        $this->csh->applyVisibilityAndConfigureColumns($dataTable, $this->tableSettings->partsDefaultColumns,
            "TABLE_PARTS_DEFAULT_COLUMNS");

        $dataTable->addOrderBy('name')
            ->createAdapter(TwoStepORMAdapter::class, [
                'filter_query' => $this->getFilterQuery(...),
                'detail_query' => $this->getDetailQuery(...),
                'entity' => Part::class,
                'hydrate' => AbstractQuery::HYDRATE_OBJECT,
                //Use the simple total query, as we just want to get the total number of parts without any conditions
                //For this the normal query would be pretty slow
                'simple_total_query' => true,
                'criteria' => [
                    function (QueryBuilder $builder) use ($options): void {
                        $this->buildCriteria($builder, $options);
                    },
                    new SearchCriteriaProvider(),
                ],
                'query_modifier' => $this->addJoins(...),
                // Use DTO hydration instead of full entity loading
                'dto_hydrator' => fn(array $results) => $this->partDTOHydrator->hydrateFromQueryResults($results),
            ]);
    }


    private function getFilterQuery(QueryBuilder $builder): void
    {
        /* In the filter query we only select the IDs. The fetching of the full entities is done in the detail query.
         * We only need to join the entities here, so we can filter by them.
         * The filter conditions are added to this QB in the buildCriteria method.
         *
         * The amountSum field and the joins are dynmically added by the addJoins method, if the fields are used in the query.
         * This improves the performance, as we do not need to join all tables, if we do not need them.
         */
        $builder
            ->select('part.id')
            ->addSelect('part.minamount AS HIDDEN minamount')
            ->from(Part::class, 'part')

            //The other group by fields, are dynamically added by the addJoins method
            ->addGroupBy('part');
    }

    private function getDetailQuery(QueryBuilder $builder, array $filter_results): void
    {
        $ids = array_map(static fn($row) => $row['id'], $filter_results);

        /*
         * Optimized query that selects only specific fields needed for table rendering.
         * Instead of loading full Part entities, we select scalar values and build lightweight DTOs.
         * This significantly reduces memory usage and improves performance.
         *
         * We compute aggregated amounts (amountSum, expiredAmountSum, hasUnknownAmount) using subqueries
         * to avoid complex PHP iteration.
         */
        $builder
            // Core Part fields
            ->select('part.id AS id')
            ->addSelect('part.name AS name')
            ->addSelect('part.ipn AS ipn')
            ->addSelect('part.description AS description')
            ->addSelect('part.minamount AS minamount')
            ->addSelect('part.manufacturer_product_number AS manufacturer_product_number')
            ->addSelect('part.mass AS mass')
            ->addSelect('part.gtin AS gtin')
            ->addSelect('part.tags AS tags')
            ->addSelect('part.favorite AS favorite')
            ->addSelect('part.needs_review AS needs_review')
            ->addSelect('part.addedDate AS addedDate')
            ->addSelect('part.lastModified AS lastModified')
            ->addSelect('part.manufacturing_status AS manufacturing_status')

            // Related entity IDs and names
            ->addSelect('category.id AS category_id')
            ->addSelect('category.name AS category_name')
            ->addSelect('footprint.id AS footprint_id')
            ->addSelect('footprint.name AS footprint_name')
            ->addSelect('manufacturer.id AS manufacturer_id')
            ->addSelect('manufacturer.name AS manufacturer_name')
            ->addSelect('partUnit.id AS partUnit_id')
            ->addSelect('partUnit.name AS partUnit_name')
            ->addSelect('partUnit.unit AS partUnit_unit')
            ->addSelect('partCustomState.id AS partCustomState_id')
            ->addSelect('partCustomState.name AS partCustomState_name')
            ->addSelect('master_picture_attachment.id AS master_picture_attachment_id')
            ->addSelect('master_picture_attachment.filename AS master_picture_attachment_filename')
            ->addSelect('master_picture_attachment.name AS master_picture_attachment_name')
            ->addSelect('footprint_attachment.id AS footprint_attachment_id')
            ->addSelect('builtProject.id AS builtProject_id')
            ->addSelect('builtProject.name AS builtProject_name')

            // Part lots for storage locations
            ->addSelect('partLots.id AS partLot_id')
            ->addSelect('storelocations.id AS storageLocation_id')
            ->addSelect('storelocations.name AS storageLocation_name')
            ->addSelect('storelocations.full_path AS storageLocation_fullPath')

            // Attachments
            ->addSelect('attachments.id AS attachment_id')

            // Projects
            ->addSelect('projects.id AS project_id')
            ->addSelect('projects.name AS project_name')

            // Computed/aggregated amounts using subqueries
            ->addSelect('(
                SELECT COALESCE(SUM(pl_sum.amount), 0.0)
                FROM ' . PartLot::class . ' pl_sum
                WHERE pl_sum.part = part.id
                AND pl_sum.instock_unknown = false
                AND (pl_sum.expiration_date IS NULL OR pl_sum.expiration_date > CURRENT_DATE())
            ) AS amountSum')
            ->addSelect('(
                SELECT COALESCE(SUM(pl_exp.amount), 0.0)
                FROM ' . PartLot::class . ' pl_exp
                WHERE pl_exp.part = part.id
                AND pl_exp.instock_unknown = false
                AND pl_exp.expiration_date IS NOT NULL
                AND pl_exp.expiration_date <= CURRENT_DATE()
            ) AS expiredAmountSum')
            ->addSelect('(
                SELECT CASE WHEN COUNT(pl_unk.id) > 0 THEN true ELSE false END
                FROM ' . PartLot::class . ' pl_unk
                WHERE pl_unk.part = part.id
                AND pl_unk.instock_unknown = true
            ) AS hasUnknownAmount')

            ->from(Part::class, 'part')
            ->leftJoin('part.category', 'category')
            ->leftJoin('part.footprint', 'footprint')
            ->leftJoin('footprint.master_picture_attachment', 'footprint_attachment')
            ->leftJoin('part.manufacturer', 'manufacturer')
            ->leftJoin('part.partUnit', 'partUnit')
            ->leftJoin('part.partCustomState', 'partCustomState')
            ->leftJoin('part.master_picture_attachment', 'master_picture_attachment')
            ->leftJoin('part.builtProject', 'builtProject')
            ->leftJoin('part.partLots', 'partLots')
            ->leftJoin('partLots.storage_location', 'storelocations')
            ->leftJoin('part.attachments', 'attachments')
            ->leftJoin('part.projects', 'projects')
            ->where('part.id IN (:ids)')
            ->setParameter('ids', $ids);

        //Get the results in the same order as the IDs were passed
        FieldHelper::addOrderByFieldParam($builder, 'part.id', 'ids');
    }

    /**
     * This function is called right before the filter query is executed.
     * We use it to dynamically add joins to the query, if the fields are used in the query.
     * @param  QueryBuilder  $builder
     * @return QueryBuilder
     */
    private function addJoins(QueryBuilder $builder): QueryBuilder
    {
        //Check if the query contains certain conditions, for which we need to add additional joins
        //The join fields get prefixed with an underscore, so we can check if they are used in the query easy without confusing them for a part subfield
        $dql = $builder->getDQL();

        //Add the amountSum field, if it is used in the query
        if (str_contains($dql, 'amountSum')) {
            //Calculate amount sum using a subquery, so we can filter and sort by it
            $builder->addSelect(
                '(
                    SELECT COALESCE(SUM(partLot.amount), 0.0)
                    FROM ' . PartLot::class . ' partLot
                    WHERE partLot.part = part.id
                    AND partLot.instock_unknown = false
                    AND (partLot.expiration_date IS NULL OR partLot.expiration_date > CURRENT_DATE())
                ) AS HIDDEN amountSum'
            );
        }

        if (str_contains($dql, '_category')) {
            $builder->leftJoin('part.category', '_category');
            $builder->addGroupBy('_category');
        }
        if (str_contains($dql, '_master_picture_attachment')) {
            $builder->leftJoin('part.master_picture_attachment', '_master_picture_attachment');
            $builder->addGroupBy('_master_picture_attachment');
        }
        if (str_contains($dql, '_partLots') || str_contains($dql, '_storelocations')) {
            $builder->leftJoin('part.partLots', '_partLots');
            $builder->leftJoin('_partLots.storage_location', '_storelocations');
            //Do not group by many-to-* relations, as it would restrict the COUNT having clauses to be maximum 1
            //$builder->addGroupBy('_partLots');
            //$builder->addGroupBy('_storelocations');
        }
        if (str_contains($dql, '_footprint')) {
            $builder->leftJoin('part.footprint', '_footprint');
            $builder->addGroupBy('_footprint');
        }
        if (str_contains($dql, '_manufacturer')) {
            $builder->leftJoin('part.manufacturer', '_manufacturer');
            $builder->addGroupBy('_manufacturer');
        }
        if (str_contains($dql, '_orderdetails') || str_contains($dql, '_suppliers')) {
            $builder->leftJoin('part.orderdetails', '_orderdetails');
            $builder->leftJoin('_orderdetails.supplier', '_suppliers');
            //Do not group by many-to-* relations, as it would restrict the COUNT having clauses to be maximum 1
            //$builder->addGroupBy('_orderdetails');
            //$builder->addGroupBy('_suppliers');
        }
        if (str_contains($dql, '_attachments')) {
            $builder->leftJoin('part.attachments', '_attachments');
            //Do not group by many-to-* relations, as it would restrict the COUNT having clauses to be maximum 1
            //$builder->addGroupBy('_attachments');
        }
        if (str_contains($dql, '_partUnit')) {
            $builder->leftJoin('part.partUnit', '_partUnit');
            $builder->addGroupBy('_partUnit');
        }
        if (str_contains($dql, '_partCustomState')) {
            $builder->leftJoin('part.partCustomState', '_partCustomState');
            $builder->addGroupBy('_partCustomState');
        }
        if (str_contains($dql, '_parameters')) {
            $builder->leftJoin('part.parameters', '_parameters');
            //Do not group by many-to-* relations, as it would restrict the COUNT having clauses to be maximum 1
            //$builder->addGroupBy('_parameters');
        }
        if (str_contains($dql, '_projectBomEntries')) {
            $builder->leftJoin('part.project_bom_entries', '_projectBomEntries');
            //Do not group by many-to-* relations, as it would restrict the COUNT having clauses to be maximum 1
            //$builder->addGroupBy('_projectBomEntries');
        }
        if (str_contains($dql, '_jobPart')) {
            $builder->leftJoin('part.bulkImportJobParts', '_jobPart');
            $builder->leftJoin('_jobPart.job', '_bulkImportJob');
            //Do not group by many-to-* relations, as it would restrict the COUNT having clauses to be maximum 1
            //$builder->addGroupBy('_jobPart');
            //$builder->addGroupBy('_bulkImportJob');
        }

        return $builder;
    }

    private function buildCriteria(QueryBuilder $builder, array $options): void
    {
        //Apply the search criterias first
        if ($options['search'] instanceof PartSearchFilter) {
            $search = $options['search'];
            $search->apply($builder);
        }

        //We do the most stuff here in the filter class
        if ($options['filter'] instanceof PartFilter) {
            $filter = $options['filter'];
            $filter->apply($builder);
        }
    }
}
