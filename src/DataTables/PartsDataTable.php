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

use App\DataTables\Adapters\FetchResultsAtOnceORMAdapter;
use App\DataTables\Adapters\TwoStepORMAdapter;
use App\DataTables\Column\EnumColumn;
use App\DataTables\Helpers\ColumnSortHelper;
use App\Doctrine\Helpers\FieldHelper;
use App\Entity\Parts\ManufacturingStatus;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\ClassMetadataInfo;
use Doctrine\ORM\Query;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Omines\DataTablesBundle\Adapter\Doctrine\Event\ORMAdapterQueryEvent;
use Omines\DataTablesBundle\Adapter\Doctrine\ORMAdapterEvents;
use Symfony\Bundle\SecurityBundle\Security;
use App\Entity\Parts\StorageLocation;
use App\DataTables\Column\EntityColumn;
use App\DataTables\Column\IconLinkColumn;
use App\DataTables\Column\LocaleDateTimeColumn;
use App\DataTables\Column\MarkdownColumn;
use App\DataTables\Column\PartAttachmentsColumn;
use App\DataTables\Column\PrettyBoolColumn;
use App\DataTables\Column\RowClassColumn;
use App\DataTables\Column\SelectColumn;
use App\DataTables\Column\SIUnitNumberColumn;
use App\DataTables\Column\TagsColumn;
use App\DataTables\Filters\PartFilter;
use App\DataTables\Filters\PartSearchFilter;
use App\DataTables\Helpers\PartDataTableHelper;
use App\Entity\Parts\Part;
use App\Entity\Parts\PartLot;
use App\Services\Formatters\AmountFormatter;
use App\Services\EntityURLGenerator;
use Doctrine\ORM\QueryBuilder;
use Omines\DataTablesBundle\Adapter\Doctrine\ORM\SearchCriteriaProvider;
use Omines\DataTablesBundle\Column\TextColumn;
use Omines\DataTablesBundle\DataTable;
use Omines\DataTablesBundle\DataTableTypeInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

final class PartsDataTable implements DataTableTypeInterface
{
    public function __construct(
        private readonly EntityURLGenerator $urlGenerator,
        private readonly TranslatorInterface $translator,
        private readonly AmountFormatter $amountFormatter,
        private readonly PartDataTableHelper $partDataTableHelper,
        private readonly Security $security,
        private readonly string $visible_columns,
        private readonly ColumnSortHelper $csh,
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
                'render' => function ($value, Part $context): string {
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
                'render' => fn($value, Part $context) => $this->partDataTableHelper->renderPicture($context),
            ], visibility_configurable: false)
            ->add('name', TextColumn::class, [
                'label' => $this->translator->trans('part.table.name'),
                'render' => fn($value, Part $context) => $this->partDataTableHelper->renderName($context),
            ])
            ->add('id', TextColumn::class, [
                'label' => $this->translator->trans('part.table.id'),
            ])
            ->add('ipn', TextColumn::class, [
                'label' => $this->translator->trans('part.table.ipn'),
            ])
            ->add('description', MarkdownColumn::class, [
                'label' => $this->translator->trans('part.table.description'),
            ])
            ->add('category', EntityColumn::class, [
                'label' => $this->translator->trans('part.table.category'),
                'property' => 'category',
            ])
            ->add('footprint', EntityColumn::class, [
                'property' => 'footprint',
                'label' => $this->translator->trans('part.table.footprint'),
            ])
            ->add('manufacturer', EntityColumn::class, [
                'property' => 'manufacturer',
                'label' => $this->translator->trans('part.table.manufacturer'),
            ])
            ->add('storelocation', TextColumn::class, [
                'label' => $this->translator->trans('part.table.storeLocations'),
                'orderField' => 'storelocations.name',
                'render' => fn ($value, Part $context) => $this->partDataTableHelper->renderStorageLocations($context),
            ], alias: 'storage_location')
            ->add('amount', TextColumn::class, [
                'label' => $this->translator->trans('part.table.amount'),
                'render' => fn ($value, Part $context) => $this->partDataTableHelper->renderAmount($context),
                'orderField' => 'amountSum'
            ])
            ->add('minamount', TextColumn::class, [
                'label' => $this->translator->trans('part.table.minamount'),
                'render' => fn($value, Part $context): string => htmlspecialchars($this->amountFormatter->format($value,
                    $context->getPartUnit())),
            ])
            ->add('partUnit', TextColumn::class, [
                'field' => 'partUnit.name',
                'label' => $this->translator->trans('part.table.partUnit'),
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
                'render' => function (?ManufacturingStatus $status, Part $context): string {
                    if (!$status) {
                        return '';
                    }

                    return $this->translator->trans($status->toTranslationKey());
                },
            ])
            ->add('manufacturer_product_number', TextColumn::class, [
                'label' => $this->translator->trans('part.table.mpn'),
            ])
            ->add('mass', SIUnitNumberColumn::class, [
                'label' => $this->translator->trans('part.table.mass'),
                'unit' => 'g'
            ])
            ->add('tags', TagsColumn::class, [
                'label' => $this->translator->trans('part.table.tags'),
            ])
            ->add('attachments', PartAttachmentsColumn::class, [
                'label' => $this->translator->trans('part.table.attachments'),
            ])
            ->add('edit', IconLinkColumn::class, [
                'label' => $this->translator->trans('part.table.edit'),
                'href' => fn($value, Part $context) => $this->urlGenerator->editURL($context),
                'disabled' => fn($value, Part $context) => !$this->security->isGranted('edit', $context),
                'title' => $this->translator->trans('part.table.edit.title'),
            ]);

        //Apply the user configured order and visibility and add the columns to the table
        $this->csh->applyVisibilityAndConfigureColumns($dataTable, $this->visible_columns,
            "TABLE_PARTS_DEFAULT_COLUMNS");

        $dataTable->addOrderBy('name')
            ->createAdapter(TwoStepORMAdapter::class, [
                'filter_query' => $this->getFilterQuery(...),
                'detail_query' => $this->getDetailQuery(...),
                'entity' => Part::class,
                'hydrate' => Query::HYDRATE_OBJECT,
                'criteria' => [
                    function (QueryBuilder $builder) use ($options): void {
                        $this->buildCriteria($builder, $options);
                    },
                    new SearchCriteriaProvider(),
                ],
            ]);
    }


    private function getFilterQuery(QueryBuilder $builder): void
    {
        /* In the filter query we only select the IDs. The fetching of the full entities is done in the detail query.
         * We only need to join the entities here, so we can filter by them.
         * The filter conditions are added to this QB in the buildCriteria method.
         */
        $builder
            ->select('part.id')
            ->addSelect('part.minamount AS HIDDEN minamount')
            //Calculate amount sum using a subquery, so we can filter and sort by it
            ->addSelect(
                '(
                    SELECT IFNULL(SUM(partLot.amount), 0.0)
                    FROM '.PartLot::class.' partLot
                    WHERE partLot.part = part.id
                    AND partLot.instock_unknown = false
                    AND (partLot.expiration_date IS NULL OR partLot.expiration_date > CURRENT_DATE())
                ) AS HIDDEN amountSum'
            )
            ->from(Part::class, 'part')
            ->leftJoin('part.category', 'category')
            ->leftJoin('part.master_picture_attachment', 'master_picture_attachment')
            ->leftJoin('part.partLots', 'partLots')
            ->leftJoin('partLots.storage_location', 'storelocations')
            ->leftJoin('part.footprint', 'footprint')
            ->leftJoin('footprint.master_picture_attachment', 'footprint_attachment')
            ->leftJoin('part.manufacturer', 'manufacturer')
            ->leftJoin('part.orderdetails', 'orderdetails')
            ->leftJoin('orderdetails.supplier', 'suppliers')
            ->leftJoin('part.attachments', 'attachments')
            ->leftJoin('part.partUnit', 'partUnit')
            ->leftJoin('part.parameters', 'parameters')

            //This must be the only group by, or the paginator will not work correctly
            ->addGroupBy('part.id');
    }

    private function getDetailQuery(QueryBuilder $builder, array $filter_results): void
    {
        $ids = array_map(fn($row) => $row['id'], $filter_results);

        /*
         * In this query we take the IDs which were filtered, paginated and sorted in the filter query, and fetch the
         * full entities.
         * We can do complex fetch joins, as we do not need to filter or sort here (which would kill the performance).
         * The only condition should be for the IDs.
         * It is important that elements are ordered the same way, as the IDs are passed, or ordering will be wrong.
         */
        $builder
            ->select('part')
            ->addSelect('category')
            ->addSelect('footprint')
            ->addSelect('manufacturer')
            ->addSelect('partUnit')
            ->addSelect('master_picture_attachment')
            ->addSelect('footprint_attachment')
            ->addSelect('partLots')
            ->addSelect('orderdetails')
            ->addSelect('attachments')
            ->addSelect('storelocations')
            //Calculate amount sum using a subquery, so we can filter and sort by it
            ->addSelect(
                '(
                    SELECT IFNULL(SUM(partLot.amount), 0.0)
                    FROM '.PartLot::class.' partLot
                    WHERE partLot.part = part.id
                    AND partLot.instock_unknown = false
                    AND (partLot.expiration_date IS NULL OR partLot.expiration_date > CURRENT_DATE())
                ) AS HIDDEN amountSum'
            )
            ->from(Part::class, 'part')
            ->leftJoin('part.category', 'category')
            ->leftJoin('part.master_picture_attachment', 'master_picture_attachment')
            ->leftJoin('part.partLots', 'partLots')
            ->leftJoin('partLots.storage_location', 'storelocations')
            ->leftJoin('part.footprint', 'footprint')
            ->leftJoin('footprint.master_picture_attachment', 'footprint_attachment')
            ->leftJoin('part.manufacturer', 'manufacturer')
            ->leftJoin('part.orderdetails', 'orderdetails')
            ->leftJoin('orderdetails.supplier', 'suppliers')
            ->leftJoin('part.attachments', 'attachments')
            ->leftJoin('part.partUnit', 'partUnit')
            ->leftJoin('part.parameters', 'parameters')
            ->where('part.id IN (:ids)')
            ->setParameter('ids', $ids)

            //We have to group by all elements, or only the first sub elements of an association is fetched! (caused issue #190)
            ->addGroupBy('part')
            ->addGroupBy('partLots')
            ->addGroupBy('category')
            ->addGroupBy('master_picture_attachment')
            ->addGroupBy('storelocations')
            ->addGroupBy('footprint')
            ->addGroupBy('footprint_attachment')
            ->addGroupBy('manufacturer')
            ->addGroupBy('orderdetails')
            ->addGroupBy('suppliers')
            ->addGroupBy('attachments')
            ->addGroupBy('partUnit')
            ->addGroupBy('parameters');

        //Get the results in the same order as the IDs were passed
        FieldHelper::addOrderByFieldParam($builder, 'part.id', 'ids');
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
