<?php
/**
 *
 * part-db version 0.1
 * Copyright (C) 2005 Christoph Lechner
 * http://www.cl-projects.de/
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
 *
 */

namespace App\DataTables;


use App\Entity\Part;
use App\Services\EntityURLGenerator;
use Doctrine\ORM\QueryBuilder;
use Omines\DataTablesBundle\Adapter\Doctrine\ORM\SearchCriteriaProvider;
use Omines\DataTablesBundle\Adapter\Doctrine\ORMAdapter;
use Omines\DataTablesBundle\Column\TextColumn;
use Omines\DataTablesBundle\DataTable;
use Omines\DataTablesBundle\DataTableTypeInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class PartsDataTable implements DataTableTypeInterface
{

    /**
     * @var EntityURLGenerator
     */
    protected $urlGenerator;

    public function __construct(EntityURLGenerator $urlGenerator)
    {
        $this->urlGenerator = $urlGenerator;
    }

    /**
     * @param DataTable $dataTable
     * @param array $options
     */
    public function configure(DataTable $dataTable, array $options)
    {
        $dataTable//->add("id", TextColumn::class)
            ->add("name", TextColumn::class, ['label' => 'name.label',
            'render' => function($value, Part $context) {
                return $this->urlGenerator->infoHTML($context);
            }])
            ->add("description", TextColumn::class, ['label' => 'description.label'])
            ->add("category", TextColumn::class, ['field' => 'category.name', 'label' => 'category.label'])
            ->add("instock", TextColumn::class, ['label' => 'instock.label_short'])
            ->add("mininstock", TextColumn::class, ['label' => 'mininstock.label_short'])
            ->add("storelocation", TextColumn::class, ['field' => 'storelocation.name', 'label' => 'storelocation.label'])
            ->addOrderBy('name')
            ->createAdapter(ORMAdapter::class, [
                'entity' => Part::class,
                'criteria' => [
                function (QueryBuilder $builder) use($options) {
                    if(isset($options['cid'])) {
                        $builder->andWhere('part.category = :cid')
                            ->setParameter('cid', $options['cid']);
                    }
                },
                    new SearchCriteriaProvider()
                ]
            ]);
    }
}