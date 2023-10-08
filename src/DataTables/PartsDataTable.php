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
use App\DataTables\Adapters\TwoStepORMAdapater;
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
use App\Entity\Parts\Storelocation;
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
                'visible' => false,
            ])
            ->add('ipn', TextColumn::class, [
                'label' => $this->translator->trans('part.table.ipn'),
                'visible' => false,
            ])
            ->add('description', MarkdownColumn::class, [
                'label' => $this->translator->trans('part.table.description'),
            ]);

        if ($this->security->isGranted('@categories.read')) {
            $this->csh->add('category', EntityColumn::class, [
                'label' => $this->translator->trans('part.table.category'),
                'property' => 'category',
            ]);
        }

        if ($this->security->isGranted('@footprints.read')) {
            $this->csh->add('footprint', EntityColumn::class, [
                'property' => 'footprint',
                'label' => $this->translator->trans('part.table.footprint'),
            ]);
        }
        if ($this->security->isGranted('@manufacturers.read')) {
            $this->csh->add('manufacturer', EntityColumn::class, [
                'property' => 'manufacturer',
                'label' => $this->translator->trans('part.table.manufacturer'),
            ]);
        }
        if ($this->security->isGranted('@storelocations.read')) {
            $this->csh->add('storelocation', TextColumn::class, [
                'label' => $this->translator->trans('part.table.storeLocations'),
                'orderField' => 'storelocations.name',
                'render' => function ($value, Part $context): string {
                    $tmp = [];
                    foreach ($context->getPartLots() as $lot) {
                        //Ignore lots without storelocation
                        if (!$lot->getStorageLocation() instanceof Storelocation) {
                            continue;
                        }
                        $tmp[] = sprintf(
                            '<a href="%s" title="%s">%s</a>',
                            $this->urlGenerator->listPartsURL($lot->getStorageLocation()),
                            htmlspecialchars($lot->getStorageLocation()->getFullPath()),
                            htmlspecialchars($lot->getStorageLocation()->getName())
                        );
                    }

                    return implode('<br>', $tmp);
                },
            ], alias: 'storage_location');
        }

        $this->csh->add('amount', TextColumn::class, [
            'label' => $this->translator->trans('part.table.amount'),
            'render' => function ($value, Part $context) {
                $amount = $context->getAmountSum();
                $expiredAmount = $context->getExpiredAmountSum();

                $ret = '';

                if ($context->isAmountUnknown()) {
                    //When all amounts are unknown, we show a question mark
                    if ($amount === 0.0) {
                        $ret .= sprintf('<b class="text-primary" title="%s">?</b>',
                            $this->translator->trans('part_lots.instock_unknown'));
                    } else { //Otherwise mark it with greater equal and the (known) amount
                        $ret .= sprintf('<b class="text-primary" title="%s">≥</b>',
                            $this->translator->trans('part_lots.instock_unknown')
                        );
                        $ret .= htmlspecialchars($this->amountFormatter->format($amount, $context->getPartUnit()));
                    }
                } else {
                    $ret .= htmlspecialchars($this->amountFormatter->format($amount, $context->getPartUnit()));
                }

                //If we have expired lots, we show them in parentheses behind
                if ($expiredAmount > 0) {
                    $ret .= sprintf(' <span title="%s" class="text-muted">(+%s)</span>',
                        $this->translator->trans('part_lots.is_expired'),
                        htmlspecialchars($this->amountFormatter->format($expiredAmount, $context->getPartUnit())));
                }

                //When the amount is below the minimum amount, we highlight the number red
                if ($context->isNotEnoughInstock()) {
                    $ret = sprintf('<b class="text-danger" title="%s">%s</b>',
                        $this->translator->trans('part.info.amount.less_than_desired'),
                        $ret);
                }

                return $ret;
            },
            'orderField' => 'amountSum'
        ])
            ->add('minamount', TextColumn::class, [
                'label' => $this->translator->trans('part.table.minamount'),
                'visible' => false,
                'render' => fn($value, Part $context): string => htmlspecialchars($this->amountFormatter->format($value,
                    $context->getPartUnit())),
            ]);

        if ($this->security->isGranted('@footprints.read')) {
            $this->csh->add('partUnit', TextColumn::class, [
                'field' => 'partUnit.name',
                'label' => $this->translator->trans('part.table.partUnit'),
                'visible' => false,
            ]);
        }

        $this->csh->add('addedDate', LocaleDateTimeColumn::class, [
            'label' => $this->translator->trans('part.table.addedDate'),
            'visible' => false,
        ])
            ->add('lastModified', LocaleDateTimeColumn::class, [
                'label' => $this->translator->trans('part.table.lastModified'),
                'visible' => false,
            ])
            ->add('needs_review', PrettyBoolColumn::class, [
                'label' => $this->translator->trans('part.table.needsReview'),
                'visible' => false,
            ])
            ->add('favorite', PrettyBoolColumn::class, [
                'label' => $this->translator->trans('part.table.favorite'),
                'visible' => false,
            ])
            ->add('manufacturing_status', EnumColumn::class, [
                'label' => $this->translator->trans('part.table.manufacturingStatus'),
                'visible' => false,
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
                'visible' => false,
            ])
            ->add('mass', SIUnitNumberColumn::class, [
                'label' => $this->translator->trans('part.table.mass'),
                'visible' => false,
                'unit' => 'g'
            ])
            ->add('tags', TagsColumn::class, [
                'label' => $this->translator->trans('part.table.tags'),
                'visible' => false,
            ])
            ->add('attachments', PartAttachmentsColumn::class, [
                'label' => $this->translator->trans('part.table.attachments'),
                'visible' => false,
            ])
            ->add('edit', IconLinkColumn::class, [
                'label' => $this->translator->trans('part.table.edit'),
                'visible' => false,
                'href' => fn($value, Part $context) => $this->urlGenerator->editURL($context),
                'disabled' => fn($value, Part $context) => !$this->security->isGranted('edit', $context),
                'title' => $this->translator->trans('part.table.edit.title'),
            ]);

        //Apply the user configured order and visibility and add the columns to the table
        $this->csh->applyVisibilityAndConfigureColumns($dataTable, $this->visible_columns, "TABLE_PARTS_DEFAULT_COLUMNS");

        $dataTable->addOrderBy('name')
            ->createAdapter(TwoStepORMAdapater::class, [
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
