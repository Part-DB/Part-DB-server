<?php
/**
 * part-db version 0.1
 * Copyright (C) 2005 Christoph Lechner
 * http://www.cl-projects.de/.
 *
 * part-db version 0.2+
 * Copyright (C) 2009 K. Jacobs and others (see authors.php)
 * http://code.google.com/p/part-db/
 *
 * Part-DB Version 0.4+
 * Copyright (C) 2016 - 2019 Jan Böhmer
 * https://github.com/jbtronics
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

use App\Entity\Parts\Category;
use App\Entity\Parts\Part;
use App\Services\EntityURLGenerator;
use Doctrine\ORM\QueryBuilder;
use Omines\DataTablesBundle\Adapter\Doctrine\ORM\SearchCriteriaProvider;
use Omines\DataTablesBundle\Adapter\Doctrine\ORMAdapter;
use Omines\DataTablesBundle\Column\BoolColumn;
use Omines\DataTablesBundle\Column\DateTimeColumn;
use Omines\DataTablesBundle\Column\MapColumn;
use Omines\DataTablesBundle\Column\TextColumn;
use Omines\DataTablesBundle\DataTable;
use Omines\DataTablesBundle\DataTableTypeInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class PartsDataTable implements DataTableTypeInterface
{
    /**
     * @var EntityURLGenerator
     */
    protected $urlGenerator;
    protected $translator;

    public function __construct(EntityURLGenerator $urlGenerator, TranslatorInterface $translator)
    {
        $this->urlGenerator = $urlGenerator;
        $this->translator = $translator;
    }

    protected function buildCriteria(QueryBuilder $builder, array $options)
    {
        if (isset($options['category'])) {
            $em = $builder->getEntityManager();
            $category = $options['category'];
            $repo = $em->getRepository(Category::class);
            $list = $repo->toNodesList($category);
            $list[] = $category;

            $builder->andWhere('part.category IN (:cid)')->setParameter('cid', $list);
        }

        if (isset($options['tag'])) {
            $builder->andWhere('part.tags LIKE :tag')->setParameter('tag', '%' . $options['tag'] . '%');
        }

        if (isset($options['search'])) {
            $builder->AndWhere('part.name LIKE :search')->orWhere('part.description LIKE :search')->orWhere('part.comment LIKE :search')
                ->setParameter('search', '%' . $options['search'] . '%');
        }
    }

    /**
     * @param DataTable $dataTable
     * @param array     $options
     */
    public function configure(DataTable $dataTable, array $options)
    {
        $dataTable
            ->add('name', TextColumn::class, [
                'label' => $this->translator->trans('part.table.name'),
                'render' => function ($value, Part $context) {
                    return $this->urlGenerator->infoHTML($context);
                },
            ])
            ->add('id', TextColumn::class, [
                'label' => $this->translator->trans('part.table.id'),
                'visible' => false
            ])
            ->add('description', TextColumn::class, [
                'label' => $this->translator->trans('part.table.description'),
            ])
            ->add('category', TextColumn::class, [
                'field' => 'category.name',
                'label' => $this->translator->trans('part.table.category')
            ])
            //->add('footprint', TextColumn::class, ['field' => 'footprint.name'])
            //->add('manufacturer', TextColumn::class, ['field' => 'manufacturer.name' ])
            //->add('amountSum', TextColumn::class, ['label' => 'instock.label_short'])
            ->add('amount', TextColumn::class, [
                'label' => $this->translator->trans('part.table.amount'),
                'propertyPath' => 'amountSum'
            ])
            ->add('minamount', TextColumn::class, [
                'label' => $this->translator->trans('part.table.minamount')
            ])
            //->add('storelocation', TextColumn::class, ['field' => 'storelocation.name', 'label' => 'storelocation.label'])

            ->add('addedDate', DateTimeColumn::class, [
                'label' => $this->translator->trans('part.table.addedDate'),
                'visible' => false
            ])
            ->add('lastModified', DateTimeColumn::class, [
                'label' => $this->translator->trans('part.table.lastModified'),
                'visible' => false
            ])
            ->add('needs_review', BoolColumn::class, [
                'label' => $this->translator->trans('part.table.needsReview'),
                'trueValue' => $this->translator->trans('true'),
                'falseValue' => $this->translator->trans('false'),
                'nullValue' => '',
                'visible' => false
            ])
            ->add('favorite', BoolColumn::class, [
                'label' => $this->translator->trans('part.table.favorite'),
                'trueValue' => $this->translator->trans('true'),
                'falseValue' => $this->translator->trans('false'),
                'nullValue' => '',
                'visible' => false
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
                    'discontinued' => $this->translator->trans('m_status.discontinued')
                ]
            ])
            ->add('manufacturer_product_number', TextColumn::class, [
                'label' => $this->translator->trans('part.table.mpn'),
                'visible' => false
            ])
            ->add('mass', TextColumn::class, [
                'label' => $this->translator->trans('part.table.mass'),
                'visible' => false
            ])
            ->add('tags', TextColumn::class, [
                'label' => $this->translator->trans('part.table.tags'),
                'visible' => false
            ])

            ->addOrderBy('name')
            ->createAdapter(ORMAdapter::class, [
                'entity' => Part::class,
                'criteria' => [
                    function (QueryBuilder $builder) use ($options) {
                        $this->buildCriteria($builder, $options);
                    },
                    new SearchCriteriaProvider()
                ]
            ]);
    }
}
