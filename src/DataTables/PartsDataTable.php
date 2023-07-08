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

use Symfony\Bundle\SecurityBundle\Security;
use App\Entity\Parts\Storelocation;
use App\DataTables\Adapters\CustomFetchJoinORMAdapter;
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
use App\Services\Attachments\AttachmentURLGenerator;
use App\Services\EntityURLGenerator;
use App\Services\Trees\NodesListBuilder;
use Doctrine\ORM\QueryBuilder;
use Omines\DataTablesBundle\Adapter\Doctrine\ORM\SearchCriteriaProvider;
use Omines\DataTablesBundle\Column\MapColumn;
use Omines\DataTablesBundle\Column\TextColumn;
use Omines\DataTablesBundle\DataTable;
use Omines\DataTablesBundle\DataTableTypeInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

final class PartsDataTable implements DataTableTypeInterface
{
    public function __construct(private readonly EntityURLGenerator $urlGenerator, private readonly TranslatorInterface $translator, private readonly AmountFormatter $amountFormatter, private readonly PartDataTableHelper $partDataTableHelper, private readonly Security $security)
    {
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

        $dataTable
            //Color the table rows depending on the review and favorite status
            ->add('dont_matter', RowClassColumn::class, [
                'render' => function ($value, Part $context): string {
                    if ($context->isNeedsReview()) {
                        return 'table-secondary';
                    }
                    if ($context->isFavorite()) {
                        return 'table-info';
                    }

                    return ''; //Default coloring otherwise
                },
            ])

            ->add('select', SelectColumn::class)
            ->add('picture', TextColumn::class, [
                'label' => '',
                'className' => 'no-colvis',
                'render' => fn($value, Part $context) => $this->partDataTableHelper->renderPicture($context),
            ])
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
            $dataTable->add('category', EntityColumn::class, [
                'label' => $this->translator->trans('part.table.category'),
                'property' => 'category',
            ]);
        }

        if ($this->security->isGranted('@footprints.read')) {
            $dataTable->add('footprint', EntityColumn::class, [
                'property' => 'footprint',
                'label' => $this->translator->trans('part.table.footprint'),
            ]);
        }
        if ($this->security->isGranted('@manufacturers.read')) {
            $dataTable->add('manufacturer', EntityColumn::class, [
                'property' => 'manufacturer',
                'label' => $this->translator->trans('part.table.manufacturer'),
            ]);
        }
        if ($this->security->isGranted('@storelocations.read')) {
            $dataTable->add('storelocation', TextColumn::class, [
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
            ]);
        }

        $dataTable->add('amount', TextColumn::class, [
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
                'render' => fn($value, Part $context): string => htmlspecialchars($this->amountFormatter->format($value, $context->getPartUnit())),
            ]);

        if ($this->security->isGranted('@footprints.read')) {
            $dataTable->add('partUnit', TextColumn::class, [
                'field' => 'partUnit.name',
                'label' => $this->translator->trans('part.table.partUnit'),
                'visible' => false,
            ]);
        }

        $dataTable->add('addedDate', LocaleDateTimeColumn::class, [
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
            ->add('manufacturing_status', MapColumn::class, [
                'label' => $this->translator->trans('part.table.manufacturingStatus'),
                'visible' => false,
                'default' => $this->translator->trans('m_status.unknown'),
                'map' => [
                    '' => $this->translator->trans('m_status.unknown'),
                    'announced' => $this->translator->trans('m_status.announced'),
                    'active' => $this->translator->trans('m_status.active'),
                    'nrfnd' => $this->translator->trans('m_status.nrfnd'),
                    'eol' => $this->translator->trans('m_status.eol'),
                    'discontinued' => $this->translator->trans('m_status.discontinued'),
                ],
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
            ])

            ->addOrderBy('name')
            ->createAdapter(CustomFetchJoinORMAdapter::class, [
                'simple_total_query' => true,
                'query' => function (QueryBuilder $builder): void {
                    $this->getQuery($builder);
                },
                'entity' => Part::class,
                'criteria' => [
                    function (QueryBuilder $builder) use ($options): void {
                        $this->buildCriteria($builder, $options);
                    },
                    new SearchCriteriaProvider(),
                ],
            ]);
    }

    private function getQuery(QueryBuilder $builder): void
    {
        //Distinct is very slow here, do not add this here (also I think this is not needed here, as the id column is always distinct)
        $builder->select('part')
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
                    FROM '. PartLot::class. ' partLot
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
            ->addGroupBy('parameters')
        ;
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
