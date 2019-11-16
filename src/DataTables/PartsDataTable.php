<?php
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

namespace App\DataTables;

use App\DataTables\Adapter\CustomORMAdapter;
use App\DataTables\Column\EntityColumn;
use App\DataTables\Column\LocaleDateTimeColumn;
use App\DataTables\Column\MarkdownColumn;
use App\DataTables\Column\PartAttachmentsColumn;
use App\DataTables\Column\TagsColumn;
use App\Entity\Attachments\Attachment;
use App\Entity\Parts\Category;
use App\Entity\Parts\Footprint;
use App\Entity\Parts\Manufacturer;
use App\Entity\Parts\Part;
use App\Entity\Parts\Storelocation;
use App\Entity\Parts\Supplier;
use App\Services\AmountFormatter;
use App\Services\Attachments\AttachmentURLGenerator;
use App\Services\Attachments\PartPreviewGenerator;
use App\Services\EntityURLGenerator;
use App\Services\FAIconGenerator;
use App\Services\MarkdownParser;
use App\Services\TreeBuilder;
use Doctrine\ORM\QueryBuilder;
use Omines\DataTablesBundle\Adapter\Doctrine\ORM\SearchCriteriaProvider;
use Omines\DataTablesBundle\Adapter\Doctrine\ORMAdapter;
use Omines\DataTablesBundle\Column\BoolColumn;
use Omines\DataTablesBundle\Column\MapColumn;
use Omines\DataTablesBundle\Column\TextColumn;
use Omines\DataTablesBundle\DataTable;
use Omines\DataTablesBundle\DataTableTypeInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use function foo\func;

class PartsDataTable implements DataTableTypeInterface
{
    /**
     * @var EntityURLGenerator
     */
    protected $urlGenerator;
    protected $translator;
    protected $treeBuilder;
    protected $amountFormatter;
    protected $previewGenerator;
    protected $attachmentURLGenerator;

    public function __construct(EntityURLGenerator $urlGenerator, TranslatorInterface $translator,
                                TreeBuilder $treeBuilder, AmountFormatter $amountFormatter,
                                PartPreviewGenerator $previewGenerator, AttachmentURLGenerator $attachmentURLGenerator)
    {
        $this->urlGenerator = $urlGenerator;
        $this->translator = $translator;
        $this->treeBuilder = $treeBuilder;
        $this->amountFormatter = $amountFormatter;
        $this->previewGenerator = $previewGenerator;
        $this->attachmentURLGenerator = $attachmentURLGenerator;
    }

    protected function getQuery(QueryBuilder $builder)
    {
        $builder->distinct()->select('part')
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
            ->from(Part::class, 'part')
            ->leftJoin('part.category', 'category')
            ->leftJoin('part.master_picture_attachment', 'master_picture_attachment')
            ->leftJoin('part.partLots', 'partLots')
            ->leftJoin('partLots.storage_location', 'storelocations')
            ->leftJoin('part.footprint', 'footprint')
            ->leftJoin('footprint.master_picture_attachment', 'footprint_attachment')
            ->leftJoin('part.manufacturer', 'manufacturer')
            ->leftJoin('part.orderdetails', 'orderdetails')
            ->leftJoin('part.attachments', 'attachments')
            ->leftJoin('part.partUnit', 'partUnit');
    }

    protected function buildCriteria(QueryBuilder $builder, array $options)
    {
        $em = $builder->getEntityManager();

        if (isset($options['category'])) {
            $category = $options['category'];
            $list = $this->treeBuilder->typeToNodesList(Category::class, $category);
            $list[] = $category;

            $builder->andWhere('part.category IN (:cid)')->setParameter('cid', $list);
        }

        if (isset($options['footprint'])) {
            $category = $options['footprint'];
            $list = $this->treeBuilder->typeToNodesList(Footprint::class, $category);
            $list[] = $category;

            $builder->andWhere('part.footprint IN (:cid)')->setParameter('cid', $list);
        }

        if (isset($options['manufacturer'])) {
            $category = $options['manufacturer'];
            $list = $this->treeBuilder->typeToNodesList(Manufacturer::class, $category);
            $list[] = $category;

            $builder->andWhere('part.manufacturer IN (:cid)')->setParameter('cid', $list);
        }

        if (isset($options['storelocation'])) {
            $location = $options['storelocation'];
            $list = $this->treeBuilder->typeToNodesList(Storelocation::class, $location);
            $list[] = $location;

            $builder->andWhere('partLots.storage_location IN (:cid)')->setParameter('cid', $list);
        }

        if (isset($options['supplier'])) {
            $supplier = $options['supplier'];
            $list = $this->treeBuilder->typeToNodesList(Supplier::class, $supplier);
            $list[] = $supplier;

            $builder->andWhere('orderdetails.supplier IN (:cid)')->setParameter('cid', $list);
        }

        if (isset($options['tag'])) {
            $builder->andWhere('part.tags LIKE :tag')->setParameter('tag', '%'.$options['tag'].'%');
        }

        if (isset($options['search'])) {
            $builder->AndWhere('part.name LIKE :search')->orWhere('part.description LIKE :search')->orWhere('part.comment LIKE :search')
                ->setParameter('search', '%'.$options['search'].'%');
        }
    }

    public function configure(DataTable $dataTable, array $options)
    {
        $dataTable
            ->add('picture', TextColumn::class, [
                'label' => '',
                'render' => function ($value, Part $context) {
                    $preview_attachment = $this->previewGenerator->getTablePreviewAttachment($context);
                    if (null === $preview_attachment) {
                        return '';
                    }

                    return sprintf(
                        '<img alt="%s" src="%s" data-thumbnail="%s" class="%s">',
                        'Part image',
                        $this->attachmentURLGenerator->getThumbnailURL($preview_attachment),
                        $this->attachmentURLGenerator->getThumbnailURL($preview_attachment, 'thumbnail_md'),
                        'img-fluid hoverpic'
                    );
                },
            ])
            ->add('name', TextColumn::class, [
                'label' => $this->translator->trans('part.table.name'),
                'render' => function ($value, Part $context) {
                    return sprintf(
                        '<a href="%s">%s</a>',
                        $this->urlGenerator->infoURL($context),
                        $context->getName()
                    );
                },
            ])
            ->add('id', TextColumn::class, [
                'label' => $this->translator->trans('part.table.id'),
                'visible' => false,
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
                'render' => function ($value, Part $context) {
                    $tmp = [];
                    foreach ($context->getPartLots() as $lot) {
                        //Ignore lots without storelocation
                        if (null === $lot->getStorageLocation()) {
                            continue;
                        }
                        $tmp[] = sprintf(
                            '<a href="%s">%s</a>',
                            $this->urlGenerator->listPartsURL($lot->getStorageLocation()),
                            $lot->getStorageLocation()->getName()
                        );
                    }

                    return implode('<br>', $tmp);
                },
            ])
            ->add('amount', TextColumn::class, [
                'label' => $this->translator->trans('part.table.amount'),
                'render' => function ($value, Part $context) {
                    $amount = $context->getAmountSum();

                    return $this->amountFormatter->format($amount, $context->getPartUnit());
                },
            ])
            ->add('minamount', TextColumn::class, [
                'label' => $this->translator->trans('part.table.minamount'),
                'visible' => false,
                'render' => function ($value, Part $context) {
                    return $this->amountFormatter->format($value, $context->getPartUnit());
                },
            ])
            ->add('partUnit', TextColumn::class, [
                'field' => 'partUnit.name',
                'label' => $this->translator->trans('part.table.partUnit'),
                'visible' => false,
            ])
            ->add('addedDate', LocaleDateTimeColumn::class, [
                'label' => $this->translator->trans('part.table.addedDate'),
                'visible' => false,
            ])
            ->add('lastModified', LocaleDateTimeColumn::class, [
                'label' => $this->translator->trans('part.table.lastModified'),
                'visible' => false,
            ])
            ->add('needs_review', BoolColumn::class, [
                'label' => $this->translator->trans('part.table.needsReview'),
                'trueValue' => $this->translator->trans('true'),
                'falseValue' => $this->translator->trans('false'),
                'nullValue' => '',
                'visible' => false,
            ])
            ->add('favorite', BoolColumn::class, [
                'label' => $this->translator->trans('part.table.favorite'),
                'trueValue' => $this->translator->trans('true'),
                'falseValue' => $this->translator->trans('false'),
                'nullValue' => '',
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
            ->add('mass', TextColumn::class, [
                'label' => $this->translator->trans('part.table.mass'),
                'visible' => false,
            ])
            ->add('tags', TagsColumn::class, [
                'label' => $this->translator->trans('part.table.tags'),
                'visible' => false,
            ])
            ->add('attachments', PartAttachmentsColumn::class, [
                'label' => $this->translator->trans('part.table.attachments'),
                'visible' => false,
            ])

            ->addOrderBy('name')
            ->createAdapter(CustomORMAdapter::class, [
                'query' => function (QueryBuilder $builder) {
                    $this->getQuery($builder);
                },
                'entity' => Part::class,
                'criteria' => [
                    function (QueryBuilder $builder) use ($options) {
                        $this->buildCriteria($builder, $options);
                    },
                    new SearchCriteriaProvider(),
                ],
            ]);
    }
}
