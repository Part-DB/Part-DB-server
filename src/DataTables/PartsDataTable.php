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

namespace App\DataTables;

use App\DataTables\Adapter\FetchJoinORMAdapter;
use App\DataTables\Column\EntityColumn;
use App\DataTables\Column\IconLinkColumn;
use App\DataTables\Column\LocaleDateTimeColumn;
use App\DataTables\Column\MarkdownColumn;
use App\DataTables\Column\PartAttachmentsColumn;
use App\DataTables\Column\TagsColumn;
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
use App\Services\Trees\NodesListBuilder;
use Doctrine\ORM\QueryBuilder;
use Omines\DataTablesBundle\Adapter\Doctrine\ORM\SearchCriteriaProvider;
use Omines\DataTablesBundle\Column\BoolColumn;
use Omines\DataTablesBundle\Column\MapColumn;
use Omines\DataTablesBundle\Column\TextColumn;
use Omines\DataTablesBundle\DataTable;
use Omines\DataTablesBundle\DataTableTypeInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Security;
use Symfony\Contracts\Translation\TranslatorInterface;

final class PartsDataTable implements DataTableTypeInterface
{
    private $translator;
    private $treeBuilder;
    private $amountFormatter;
    private $previewGenerator;
    private $attachmentURLGenerator;
    private $security;

    /**
     * @var EntityURLGenerator
     */
    private $urlGenerator;

    public function __construct(EntityURLGenerator $urlGenerator, TranslatorInterface $translator,
        NodesListBuilder $treeBuilder, AmountFormatter $amountFormatter,
        PartPreviewGenerator $previewGenerator, AttachmentURLGenerator $attachmentURLGenerator, Security $security)
    {
        $this->urlGenerator = $urlGenerator;
        $this->translator = $translator;
        $this->treeBuilder = $treeBuilder;
        $this->amountFormatter = $amountFormatter;
        $this->previewGenerator = $previewGenerator;
        $this->attachmentURLGenerator = $attachmentURLGenerator;
        $this->security = $security;
    }

    public function configureOptions(OptionsResolver $optionsResolver): void
    {
        $optionsResolver->setDefaults([
            'category' => null,
            'footprint' => null,
            'manufacturer' => null,
            'storelocation' => null,
            'supplier' => null,
            'tag' => null,
            'search' => null,
        ]);

        $optionsResolver->setAllowedTypes('category', ['null', Category::class]);
        $optionsResolver->setAllowedTypes('footprint', ['null', Footprint::class]);
        $optionsResolver->setAllowedTypes('manufacturer', ['null', Manufacturer::class]);
        $optionsResolver->setAllowedTypes('supplier', ['null', Supplier::class]);
        $optionsResolver->setAllowedTypes('tag', ['null', 'string']);
        $optionsResolver->setAllowedTypes('search', ['null', 'string']);

        //Configure search options
        $optionsResolver->setDefault('search_options', static function (OptionsResolver $resolver): void {
            $resolver->setDefaults([
                'name' => true,
                'category' => true,
                'description' => true,
                'store_location' => true,
                'comment' => true,
                'ordernr' => true,
                'supplier' => false,
                'manufacturer' => false,
                'footprint' => false,
                'tags' => false,
                'regex' => false,
            ]);
            $resolver->setAllowedTypes('name', 'bool');
            $resolver->setAllowedTypes('category', 'bool');
            $resolver->setAllowedTypes('description', 'bool');
            $resolver->setAllowedTypes('store_location', 'bool');
            $resolver->setAllowedTypes('comment', 'bool');
            $resolver->setAllowedTypes('supplier', 'bool');
            $resolver->setAllowedTypes('manufacturer', 'bool');
            $resolver->setAllowedTypes('footprint', 'bool');
            $resolver->setAllowedTypes('tags', 'bool');
            $resolver->setAllowedTypes('regex', 'bool');
        });
    }

    public function configure(DataTable $dataTable, array $options): void
    {
        $resolver = new OptionsResolver();
        $this->configureOptions($resolver);
        $options = $resolver->resolve($options);

        $dataTable
            ->add('picture', TextColumn::class, [
                'label' => '',
                'render' => function ($value, Part $context) {
                    $preview_attachment = $this->previewGenerator->getTablePreviewAttachment($context);
                    if (null === $preview_attachment) {
                        return '';
                    }

                    $title = htmlspecialchars($preview_attachment->getName());
                    if ($preview_attachment->getFilename()) {
                        $title .= ' ('.htmlspecialchars($preview_attachment->getFilename()).')';
                    }

                    return sprintf(
                        '<img alt="%s" src="%s" data-thumbnail="%s" class="%s" data-title="%s">',
                        'Part image',
                        $this->attachmentURLGenerator->getThumbnailURL($preview_attachment),
                        $this->attachmentURLGenerator->getThumbnailURL($preview_attachment, 'thumbnail_md'),
                        'img-fluid hoverpic',
                        $title
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
            ->add('edit', IconLinkColumn::class, [
                'label' => $this->translator->trans('part.table.edit'),
                'visible' => false,
                'href' => function ($value, Part $context) {
                    return $this->urlGenerator->editURL($context);
                },
                'disabled' => function ($value, Part $context) {
                    return !$this->security->isGranted('edit', $context);
                },
                'title' => $this->translator->trans('part.table.edit.title'),
            ])

            ->addOrderBy('name')
            ->createAdapter(FetchJoinORMAdapter::class, [
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
            ->leftJoin('orderdetails.supplier', 'suppliers')
            ->leftJoin('part.attachments', 'attachments')
            ->leftJoin('part.partUnit', 'partUnit');
    }

    private function buildCriteria(QueryBuilder $builder, array $options): void
    {
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

        if (!empty($options['search'])) {
            if (!$options['search_options']['regex']) {
                //Dont show results, if no things are selected
                $builder->andWhere('0=1');
                $defined = false;
                if ($options['search_options']['name']) {
                    $builder->orWhere('part.name LIKE :search');
                    $defined = true;
                }
                if ($options['search_options']['description']) {
                    $builder->orWhere('part.description LIKE :search');
                    $defined = true;
                }
                if ($options['search_options']['comment']) {
                    $builder->orWhere('part.comment LIKE :search');
                    $defined = true;
                }
                if ($options['search_options']['category']) {
                    $builder->orWhere('category.name LIKE :search');
                    $defined = true;
                }
                if ($options['search_options']['manufacturer']) {
                    $builder->orWhere('manufacturer.name LIKE :search');
                    $defined = true;
                }
                if ($options['search_options']['footprint']) {
                    $builder->orWhere('footprint.name LIKE :search');
                    $defined = true;
                }
                if ($options['search_options']['tags']) {
                    $builder->orWhere('part.tags LIKE :search');
                    $defined = true;
                }
                if ($options['search_options']['store_location']) {
                    $builder->orWhere('storelocations.name LIKE :search');
                    $defined = true;
                }
                if ($options['search_options']['supplier']) {
                    $builder->orWhere('suppliers.name LIKE :search');
                    $defined = true;
                }

                if ($defined) {
                    $builder->setParameter('search', '%'.$options['search'].'%');
                }
            } else { //Use REGEX
                $builder->andWhere('0=1');
                $defined = false;
                if ($options['search_options']['name']) {
                    $builder->orWhere('REGEXP(part.name, :search) = 1');
                    $defined = true;
                }
                if ($options['search_options']['description']) {
                    $builder->orWhere('REGEXP(part.description, :search) = 1');
                    $defined = true;
                }
                if ($options['search_options']['comment']) {
                    $builder->orWhere('REGEXP(part.comment, :search) = 1');
                    $defined = true;
                }
                if ($options['search_options']['category']) {
                    $builder->orWhere('REGEXP(category.name, :search) = 1');
                    $defined = true;
                }
                if ($options['search_options']['manufacturer']) {
                    $builder->orWhere('REGEXP(manufacturer.name, :search) = 1');
                    $defined = true;
                }
                if ($options['search_options']['footprint']) {
                    $builder->orWhere('REGEXP(footprint.name, :search) = 1');
                    $defined = true;
                }
                if ($options['search_options']['tags']) {
                    $builder->orWhere('REGEXP(part.tags, :search) = 1');
                    $defined = true;
                }
                if ($options['search_options']['store_location']) {
                    $builder->orWhere('REGEXP(storelocations.name, :search) = 1');
                    $defined = true;
                }
                if ($options['search_options']['supplier']) {
                    $builder->orWhere('REGEXP(suppliers.name, :search) = 1');
                    $defined = true;
                }

                if ($defined) {
                    $builder->setParameter('search', $options['search']);
                }
            }
        }
    }
}
