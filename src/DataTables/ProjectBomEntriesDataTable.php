<?php
/**
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

namespace App\DataTables;

use App\DataTables\Adapters\TwoStepORMAdapter;
use App\DataTables\Column\EntityColumn;
use App\DataTables\Column\EnumColumn;
use App\DataTables\Column\HTMLColumn;
use App\DataTables\Column\LocaleDateTimeColumn;
use App\DataTables\Column\MarkdownColumn;
use App\DataTables\Helpers\PartDataTableHelper;
use App\Doctrine\Helpers\FieldHelper;
use App\Entity\Parts\ManufacturingStatus;
use App\Entity\Parts\Part;
use App\Entity\ProjectSystem\ProjectBOMEntry;
use App\Services\ElementTypeNameGenerator;
use App\Services\EntityURLGenerator;
use App\Services\Formatters\AmountFormatter;
use App\Services\Formatters\MoneyFormatter;
use App\Services\ProjectSystem\ProjectBuildHelper;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Omines\DataTablesBundle\Adapter\Doctrine\ORM\SearchCriteriaProvider;
use Omines\DataTablesBundle\Column\TextColumn;
use Omines\DataTablesBundle\DataTable;
use Omines\DataTablesBundle\DataTableTypeInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class ProjectBomEntriesDataTable implements DataTableTypeInterface
{
    public function __construct(
        protected EntityURLGenerator $entityURLGenerator,
        protected TranslatorInterface $translator,
        protected AmountFormatter $amountFormatter,
        protected PartDataTableHelper $partDataTableHelper,
        protected ProjectBuildHelper $projectBuildHelper,
        protected MoneyFormatter $moneyFormatter,
    ) {
    }


    public function configure(DataTable $dataTable, array $options): void
    {
        /*************************************************************************************************************
         * Avoid using render, as it has no escaping, and is a potential security risk. Use data on TextColumn or the
         * HTMLColumn, if necessary
         ************************************************************************************************************/

        $dataTable
            //->add('select', SelectColumn::class)
            ->add('picture', HTMLColumn::class, [
                'label' => '',
                'className' => 'no-colvis',
                'data' => function (ProjectBOMEntry $context) {
                    if(!$context->getPart() instanceof Part) {
                        return '';
                    }
                    return $this->partDataTableHelper->renderPicture($context->getPart());
                },
            ])

            ->add('id', TextColumn::class, [
                'label' => $this->translator->trans('part.table.id'),
                'visible' => false,
            ])

            ->add('quantity', TextColumn::class, [
                'label' => $this->translator->trans('project.bom.quantity'),
                'className' => 'text-center',
                'orderField' => 'bom_entry.quantity',
                'data' => function (ProjectBOMEntry $context): float|string {
                    //If we have a non-part entry, only show the rounded quantity
                    if (!$context->getPart() instanceof Part) {
                        return round($context->getQuantity());
                    }
                    //Otherwise use the unit of the part to format the quantity
                    return $this->amountFormatter->format($context->getQuantity(), $context->getPart()->getPartUnit());
                },
            ])
            ->add('partId', TextColumn::class, [
                'label' => $this->translator->trans('project.bom.part_id'),
                'visible' => false,
                'orderField' => 'part.id',
                'data' => function (ProjectBOMEntry $context) {
                    return $context->getPart() instanceof Part ? (string) $context->getPart()->getId() : '';
                },
            ])
            ->add('name', HTMLColumn::class, [
                'label' => $this->translator->trans('part.table.name'),
                'orderField' => 'NATSORT(part.name)',
                'data' => function (ProjectBOMEntry $context) {
                    if(!$context->getPart() instanceof Part) {
                        return htmlspecialchars((string) $context->getName());
                    }

                    //Part exists if we reach this point

                    $tmp = $this->partDataTableHelper->renderName($context->getPart());
                    if($context->getName() !== null && $context->getName() !== '') {
                        $tmp .= '<br><b>'.htmlspecialchars($context->getName()).'</b>';
                    }
                    return $tmp;
                },
            ])
            ->add('ipn', TextColumn::class, [
                'label' => $this->translator->trans('part.table.ipn'),
                'orderField' => 'NATSORT(part.ipn)',
                'visible' => false,
                'data' => fn (ProjectBOMEntry $context) => $context->getPart()?->getIpn()
            ])
            ->add('description', MarkdownColumn::class, [
                'label' => $this->translator->trans('part.table.description'),
                'data' => function (ProjectBOMEntry $context) {
                    if($context->getPart() instanceof Part) {
                        return $context->getPart()->getDescription();
                    }
                    //For non-part BOM entries show the comment field
                    return $context->getComment();
                },
            ])


            ->add('category', EntityColumn::class, [
                'label' => $this->translator->trans('part.table.category'),
                'property' => 'part.category',
                'orderField' => 'NATSORT(category.name)'
            ])
            ->add('footprint', EntityColumn::class, [
                'property' => 'part.footprint',
                'visible' => false,
                'label' => $this->translator->trans('part.table.footprint'),
                'orderField' => 'NATSORT(footprint.name)'
            ])

            ->add('manufacturer', EntityColumn::class, [
                'property' => 'part.manufacturer',
                'label' => $this->translator->trans('part.table.manufacturer'),
                'orderField' => 'NATSORT(manufacturer.name)'
            ])
            ->add('supplier', HTMLColumn::class, [
                'label' => $this->translator->trans('supplier.label'),
                'visible' => true,
                // Use an aggregate because a part can have multiple supplier orderdetails.
                'orderField' => 'NATSORT(MIN(_suppliers.name))',
                'data' => function (ProjectBOMEntry $context): string {
                    if (!$context->getPart() instanceof Part) {
                        return '';
                    }

                    $supplierLinks = [];
                    foreach ($context->getPart()->getOrderdetails(true) as $orderdetail) {
                        $supplier = $orderdetail->getSupplier();
                        $supplierName = trim((string) $supplier->getName());
                        if ($supplierName === '') {
                            continue;
                        }

                        $supplierId = $supplier->getId();
                        if (isset($supplierLinks[$supplierId])) {
                            continue;
                        }

                        $supplierLinks[$supplierId] = sprintf(
                            '<a href="%s">%s</a>',
                            htmlspecialchars($this->entityURLGenerator->listPartsURL($supplier)),
                            htmlspecialchars($supplierName)
                        );
                    }

                    return implode(', ', $supplierLinks);
                },
            ])

            ->add('manufacturing_status', EnumColumn::class, [
                'label' => $this->translator->trans('part.table.manufacturingStatus'),
                'data' => static fn(ProjectBOMEntry $context): ?ManufacturingStatus => $context->getPart()?->getManufacturingStatus(),
                'orderField' => 'part.manufacturing_status',
                'class' => ManufacturingStatus::class,
                'render' => function (?ManufacturingStatus $status, ProjectBOMEntry $context): string {
                    if ($status === null) {
                        return '';
                    }

                    return $this->translator->trans($status->toTranslationKey());
                },
            ])

            ->add('mountnames', HTMLColumn::class, [
                'label' => 'project.bom.mountnames',
                'visible' => false,
                'data' => function (ProjectBOMEntry $context) {
                    $html = '';

                    foreach (explode(',', $context->getMountnames()) as $mountname) {
                        $html .= sprintf('<span class="badge badge-secondary bg-secondary">%s</span> ', htmlspecialchars($mountname));
                    }
                    return $html;
                },
            ])

            ->add('instockAmount', HTMLColumn::class, [
                'label' => 'project.bom.instockAmount',
                'visible' => true,
                'data' => function (ProjectBOMEntry $context) {
                    if ($context->getPart() !== null) {
                        return $this->partDataTableHelper->renderAmount($context->getPart());
                    }

                    return '';
                },
            ])
            ->add('minAmount', HTMLColumn::class, [
                'label' => $this->translator->trans('part.table.minamount'),
                'visible' => true,
                'orderField' => 'part.minamount',
                'data' => function (ProjectBOMEntry $context): string {
                    if (!$context->getPart() instanceof Part) {
                        return '';
                    }

                    return $this->amountFormatter->format($context->getPart()->getMinAmount(), $context->getPart()->getPartUnit());
                },
            ])
            ->add('orderAmount', HTMLColumn::class, [
                'label' => $this->translator->trans('part.table.orderamount'),
                'visible' => true,
                'orderField' => 'part.orderamount',
                'data' => function (ProjectBOMEntry $context): string {
                    if (!$context->getPart() instanceof Part) {
                        return '';
                    }

                    return $this->amountFormatter->format($context->getPart()->getOrderAmount(), $context->getPart()->getPartUnit());
                },
            ])
            ->add('storelocation', HTMLColumn::class, [
                'label' => $this->translator->trans('part.table.storeLocations'),
                //We need to use a aggregate function to get the first store location, as we have a one-to-many relation
                'orderField' => 'NATSORT(MIN(_storelocations.name))',
                'visible' => false,
                'data' => function (ProjectBOMEntry $context) {
                    if ($context->getPart() !== null) {
                        return $this->partDataTableHelper->renderStorageLocations($context->getPart());
                    }

                    return '';
                },
            ])
            ->add('price', TextColumn::class, [
                'label' => 'project.bom.price',
                'visible' => false,
                'data' => function (ProjectBOMEntry $context) {
                    $price = $this->projectBuildHelper->getEntryUnitPrice($context);
                    return $this->moneyFormatter->format($price->toScale(2, RoundingMode::Up)->toFloat(), null, 2, true);
                },
            ])
            ->add('ext_price', TextColumn::class, [
                'label' => 'project.bom.ext_price',
                'visible' => false,
                'data' => function (ProjectBOMEntry $context) {
                    $price = $this->projectBuildHelper->getEntryUnitPrice($context);
                    return $this->moneyFormatter->format(
                        $price->multipliedBy(BigDecimal::fromFloatShortest($context->getQuantity()))
                            ->toScale(2, RoundingMode::Up)->toFloat(),
                        null,
                        2,
                        true
                    );
                },
            ])

            ->add('addedDate', LocaleDateTimeColumn::class, [
                'label' => $this->translator->trans('part.table.addedDate'),
                'visible' => false,
            ])
            ->add('lastModified', LocaleDateTimeColumn::class, [
                'label' => $this->translator->trans('part.table.lastModified'),
                'visible' => false,
            ])
        ;

        $dataTable->addOrderBy('name', DataTable::SORT_ASCENDING);

        $dataTable->createAdapter(TwoStepORMAdapter::class, [
            'entity' => ProjectBOMEntry::class,
            'hydrate' => AbstractQuery::HYDRATE_OBJECT,
            'filter_query' => function (QueryBuilder $builder) use ($options): void {
                $this->getFilterQuery($builder, $options);
            },
            'detail_query' => $this->getDetailQuery(...),
            'criteria' => [
                function (QueryBuilder $builder) use ($options): void {
                    $this->buildCriteria($builder, $options);
                },
                new SearchCriteriaProvider(),
            ],
        ]);
    }

    private function getFilterQuery(QueryBuilder $builder, array $options): void
    {
        $builder
            ->select('bom_entry.id')
            ->from(ProjectBOMEntry::class, 'bom_entry')
            ->leftJoin('bom_entry.part', 'part')
            ->leftJoin('part.category', 'category')
            ->leftJoin('part.partLots', '_partLots')
            ->leftJoin('_partLots.storage_location', '_storelocations')
            ->leftJoin('part.footprint', 'footprint')
            ->leftJoin('part.manufacturer', 'manufacturer')
            ->leftJoin('part.orderdetails', '_orderdetails')
            ->leftJoin('_orderdetails.supplier', '_suppliers')
            ->leftJoin('part.partCustomState', 'partCustomState')
            ->where('bom_entry.project = :project')
            ->setParameter('project', $options['project'])
            ->addGroupBy('bom_entry')
            ->addGroupBy('part')
            ->addGroupBy('category')
            ->addGroupBy('footprint')
            ->addGroupBy('manufacturer')
            ->addGroupBy('partCustomState')
        ;
    }

    private function getDetailQuery(QueryBuilder $builder, array $filter_results): void
    {
        $ids = array_map(static fn (array $row) => $row['id'], $filter_results);
        if ($ids === []) {
            $ids = [-1];
        }

        $builder
            ->select('bom_entry')
            ->addSelect('part')
            ->addSelect('category')
            ->addSelect('partLots')
            ->addSelect('storelocations')
            ->addSelect('footprint')
            ->addSelect('manufacturer')
            ->addSelect('orderdetails')
            ->addSelect('suppliers')
            ->addSelect('partCustomState')
            ->from(ProjectBOMEntry::class, 'bom_entry')
            ->leftJoin('bom_entry.part', 'part')
            ->leftJoin('part.category', 'category')
            ->leftJoin('part.partLots', 'partLots')
            ->leftJoin('partLots.storage_location', 'storelocations')
            ->leftJoin('part.footprint', 'footprint')
            ->leftJoin('part.manufacturer', 'manufacturer')
            ->leftJoin('part.orderdetails', 'orderdetails')
            ->leftJoin('orderdetails.supplier', 'suppliers')
            ->leftJoin('part.partCustomState', 'partCustomState')
            ->where('bom_entry.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->addGroupBy('bom_entry')
            ->addGroupBy('part')
            ->addGroupBy('partLots')
            ->addGroupBy('category')
            ->addGroupBy('storelocations')
            ->addGroupBy('footprint')
            ->addGroupBy('manufacturer')
            ->addGroupBy('orderdetails')
            ->addGroupBy('suppliers')
            ->addGroupBy('partCustomState')

            ->setHint(Query::HINT_READ_ONLY, true)
            ->setHint(Query::HINT_FORCE_PARTIAL_LOAD, false)
        ;

        FieldHelper::addOrderByFieldParam($builder, 'bom_entry.id', 'ids');
    }

    private function buildCriteria(QueryBuilder $builder, array $options): void
    {

    }
}
