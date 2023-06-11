<?php
/*
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

namespace App\DataTables;

use App\DataTables\Column\EntityColumn;
use App\DataTables\Column\LocaleDateTimeColumn;
use App\DataTables\Column\MarkdownColumn;
use App\DataTables\Column\SelectColumn;
use App\DataTables\Helpers\PartDataTableHelper;
use App\Entity\Attachments\Attachment;
use App\Entity\Parts\Part;
use App\Entity\ProjectSystem\ProjectBOMEntry;
use App\Services\EntityURLGenerator;
use App\Services\Formatters\AmountFormatter;
use Doctrine\ORM\QueryBuilder;
use Omines\DataTablesBundle\Adapter\Doctrine\ORM\SearchCriteriaProvider;
use Omines\DataTablesBundle\Adapter\Doctrine\ORMAdapter;
use Omines\DataTablesBundle\Column\TextColumn;
use Omines\DataTablesBundle\DataTable;
use Omines\DataTablesBundle\DataTableTypeInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class ProjectBomEntriesDataTable implements DataTableTypeInterface
{
    public function __construct(protected TranslatorInterface $translator, protected PartDataTableHelper $partDataTableHelper, protected EntityURLGenerator $entityURLGenerator, protected AmountFormatter $amountFormatter)
    {
    }


    public function configure(DataTable $dataTable, array $options)
    {
        $dataTable
            //->add('select', SelectColumn::class)
            ->add('picture', TextColumn::class, [
                'label' => '',
                'className' => 'no-colvis',
                'render' => function ($value, ProjectBOMEntry $context) {
                    if(!$context->getPart() instanceof \App\Entity\Parts\Part) {
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
                'render' => function ($value, ProjectBOMEntry $context): float|string {
                    //If we have a non-part entry, only show the rounded quantity
                    if (!$context->getPart() instanceof \App\Entity\Parts\Part) {
                        return round($context->getQuantity());
                    }
                    //Otherwise use the unit of the part to format the quantity
                    return htmlspecialchars($this->amountFormatter->format($context->getQuantity(), $context->getPart()->getPartUnit()));
                },
            ])

            ->add('name', TextColumn::class, [
                'label' => $this->translator->trans('part.table.name'),
                'orderField' => 'part.name',
                'render' => function ($value, ProjectBOMEntry $context) {
                    if(!$context->getPart() instanceof \App\Entity\Parts\Part) {
                        return htmlspecialchars($context->getName());
                    }
                    if($context->getPart() instanceof \App\Entity\Parts\Part) {
                        $tmp = $this->partDataTableHelper->renderName($context->getPart());
                        if(!empty($context->getName())) {
                            $tmp .= '<br><b>'.htmlspecialchars($context->getName()).'</b>';
                        }
                        return $tmp;
                    }
                    throw new \Exception('This should never happen!');
                },
            ])

            ->add('description', MarkdownColumn::class, [
                'label' => $this->translator->trans('part.table.description'),
                'data' => function (ProjectBOMEntry $context) {
                    if($context->getPart() instanceof \App\Entity\Parts\Part) {
                        return $context->getPart()->getDescription();
                    }
                    //For non-part BOM entries show the comment field
                    return $context->getComment();
                },
            ])


            ->add('category', EntityColumn::class, [
                'label' => $this->translator->trans('part.table.category'),
                'property' => 'part.category',
                'orderField' => 'category.name',
            ])
            ->add('footprint', EntityColumn::class, [
                'property' => 'part.footprint',
                'label' => $this->translator->trans('part.table.footprint'),
                'orderField' => 'footprint.name',
            ])

            ->add('manufacturer', EntityColumn::class, [
                'property' => 'part.manufacturer',
                'label' => $this->translator->trans('part.table.manufacturer'),
                'orderField' => 'manufacturer.name',
            ])

            ->add('mountnames', TextColumn::class, [
                'label' => 'project.bom.mountnames',
                'render' => function ($value, ProjectBOMEntry $context) {
                    $html = '';

                    foreach (explode(',', $context->getMountnames()) as $mountname) {
                        $html .= sprintf('<span class="badge badge-secondary bg-secondary">%s</span> ', htmlspecialchars($mountname));
                    }
                    return $html;
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

        $dataTable->createAdapter(ORMAdapter::class, [
            'entity' => Attachment::class,
            'query' => function (QueryBuilder $builder) use ($options): void {
                $this->getQuery($builder, $options);
            },
            'criteria' => [
                function (QueryBuilder $builder) use ($options): void {
                    $this->buildCriteria($builder, $options);
                },
                new SearchCriteriaProvider(),
            ],
        ]);
    }

    private function getQuery(QueryBuilder $builder, array $options): void
    {
        $builder->select('bom_entry')
            ->addSelect('part')
            ->from(ProjectBOMEntry::class, 'bom_entry')
            ->leftJoin('bom_entry.part', 'part')
            ->leftJoin('part.category', 'category')
            ->leftJoin('part.footprint', 'footprint')
            ->leftJoin('part.manufacturer', 'manufacturer')
            ->where('bom_entry.project = :project')
            ->setParameter('project', $options['project'])
        ;
    }

    private function buildCriteria(QueryBuilder $builder, array $options): void
    {

    }
}