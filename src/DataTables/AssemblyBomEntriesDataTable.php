<?php

declare(strict_types=1);

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
use App\DataTables\Helpers\AssemblyDataTableHelper;
use App\DataTables\Helpers\ProjectDataTableHelper;
use App\DataTables\Helpers\ColumnSortHelper;
use App\DataTables\Helpers\PartDataTableHelper;
use App\Entity\AssemblySystem\Assembly;
use App\Entity\Attachments\Attachment;
use App\Entity\Parts\Part;
use App\Entity\AssemblySystem\AssemblyBOMEntry;
use App\Entity\ProjectSystem\Project;
use App\Services\EntityURLGenerator;
use App\Services\Formatters\AmountFormatter;
use Doctrine\ORM\QueryBuilder;
use Omines\DataTablesBundle\Adapter\Doctrine\ORM\SearchCriteriaProvider;
use Omines\DataTablesBundle\Adapter\Doctrine\ORMAdapter;
use Omines\DataTablesBundle\Column\TextColumn;
use Omines\DataTablesBundle\DataTable;
use Omines\DataTablesBundle\DataTableTypeInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class AssemblyBomEntriesDataTable implements DataTableTypeInterface
{
    public function __construct(
        protected TranslatorInterface     $translator,
        protected PartDataTableHelper     $partDataTableHelper,
        protected ProjectDataTableHelper  $projectDataTableHelper,
        protected AssemblyDataTableHelper $assemblyDataTableHelper,
        protected EntityURLGenerator      $entityURLGenerator,
        protected AmountFormatter         $amountFormatter,
        private string                    $visible_columns,
        private ColumnSortHelper          $csh
    ){
    }

    public function configure(DataTable $dataTable, array $options): void
    {
        $this->csh
            ->add('picture', TextColumn::class, [
                'label' => '',
                'className' => 'no-colvis',
                'render' => function ($value, AssemblyBOMEntry $context) {
                    if(!$context->getPart() instanceof Part) {
                        return '';
                    }
                    return $this->partDataTableHelper->renderPicture($context->getPart());
                },
            ])
            ->add('id', TextColumn::class, [
                'label' => $this->translator->trans('part.table.id'),
            ])
            ->add('quantity', TextColumn::class, [
                'label' => $this->translator->trans('assembly.bom.quantity'),
                'className' => 'text-center',
                'orderField' => 'bom_entry.quantity',
                'render' => function ($value, AssemblyBOMEntry $context): float|string {
                    //If we have a non-part entry, only show the rounded quantity
                    if (!$context->getPart() instanceof Part) {
                        return round($context->getQuantity());
                    }
                    //Otherwise use the unit of the part to format the quantity
                    return htmlspecialchars($this->amountFormatter->format($context->getQuantity(), $context->getPart()->getPartUnit()));
                },
            ])
            ->add('name', TextColumn::class, [
                'label' => $this->translator->trans('part.table.name'),
                'orderField' => 'NATSORT(part.name)',
                'render' => function ($value, AssemblyBOMEntry $context) {
                    if(!$context->getPart() instanceof Part && !$context->getReferencedAssembly() instanceof Assembly && !$context->getProject() instanceof Project) {
                        return htmlspecialchars((string) $context->getName());
                    }

                    if ($context->getPart() !== null) {
                        $tmp = $this->partDataTableHelper->renderName($context->getPart());
                        $tmp = $this->translator->trans('part.table.name.value.for_part', ['%value%' => $tmp]);

                        if($context->getName() !== null && $context->getName() !== '') {
                            $tmp .= '<br><b>'.htmlspecialchars($context->getName()).'</b>';
                        }
                    } elseif ($context->getReferencedAssembly() !== null) {
                        $tmp = $this->assemblyDataTableHelper->renderName($context->getReferencedAssembly());
                        $tmp = $this->translator->trans('part.table.name.value.for_assembly', ['%value%' => $tmp]);

                        if($context->getName() !== null && $context->getName() !== '') {
                            $tmp .= '<br><b>'.htmlspecialchars($context->getName()).'</b>';
                        }
                    } elseif ($context->getProject() !== null) {
                        $tmp = $this->projectDataTableHelper->renderName($context->getProject());
                        $tmp = $this->translator->trans('part.table.name.value.for_project', ['%value%' => $tmp]);

                        if($context->getName() !== null && $context->getName() !== '') {
                            $tmp .= '<br><b>'.htmlspecialchars($context->getName()).'</b>';
                        }
                    }

                    return $tmp;
                },

            ])
            ->add('ipn', TextColumn::class, [
                'label' => $this->translator->trans('part.table.ipn'),
                'orderField' => 'NATSORT(part.ipn)',
                'render' => function ($value, AssemblyBOMEntry $context) {
                    if($context->getPart() instanceof Part) {
                        return $context->getPart()->getIpn();
                    } elseif($context->getReferencedAssembly() instanceof Assembly) {
                        return $context->getReferencedAssembly()->getIpn();
                    }

                    return '';
                }
            ])
            ->add('description', MarkdownColumn::class, [
                'label' => $this->translator->trans('part.table.description'),
                'data' => function (AssemblyBOMEntry $context) {
                    if ($context->getPart() instanceof Part) {
                        return $context->getPart()->getDescription();
                    } elseif ($context->getReferencedAssembly() instanceof Assembly) {
                        return $context->getReferencedAssembly()->getDescription();
                    }
                    //For non-part BOM entries show the comment field
                    return $context->getComment();
                },
            ])
            ->add('category', EntityColumn::class, [
                'label' => $this->translator->trans('part.table.category'),
                'property' => 'part.category',
                'orderField' => 'NATSORT(category.name)',
            ])
            ->add('footprint', EntityColumn::class, [
                'property' => 'part.footprint',
                'label' => $this->translator->trans('part.table.footprint'),
                'orderField' => 'NATSORT(footprint.name)',
            ])
            ->add('manufacturer', EntityColumn::class, [
                'property' => 'part.manufacturer',
                'label' => $this->translator->trans('part.table.manufacturer'),
                'orderField' => 'NATSORT(manufacturer.name)',
            ])
            ->add('mountnames', TextColumn::class, [
                'label' => 'assembly.bom.mountnames',
                'render' => function ($value, AssemblyBOMEntry $context) {
                    $html = '';

                    foreach (explode(',', $context->getMountnames()) as $mountname) {
                        $html .= sprintf('<span class="badge badge-secondary bg-secondary">%s</span> ', htmlspecialchars($mountname));
                    }
                    return $html;
                },
            ])
            ->add('instockAmount', TextColumn::class, [
                'label' => 'assembly.bom.instockAmount',
                'visible' => false,
                'render' => function ($value, AssemblyBOMEntry $context) {
                    if ($context->getPart() !== null) {
                        return $this->partDataTableHelper->renderAmount($context->getPart());
                    }

                    return '';
                }
            ])
            ->add('storageLocations', TextColumn::class, [
                'label' => 'part.table.storeLocations',
                'visible' => false,
                'render' => function ($value, AssemblyBOMEntry $context) {
                    if ($context->getPart() !== null) {
                        return $this->partDataTableHelper->renderStorageLocations($context->getPart());
                    }

                    return '';
                }
            ])
            ->add('addedDate', LocaleDateTimeColumn::class, [
                'label' => $this->translator->trans('part.table.addedDate'),
            ])
            ->add('lastModified', LocaleDateTimeColumn::class, [
                'label' => $this->translator->trans('part.table.lastModified'),
            ])
        ;

        //Apply the user configured order and visibility and add the columns to the table
        $this->csh->applyVisibilityAndConfigureColumns($dataTable, $this->visible_columns,"TABLE_ASSEMBLIES_BOM_DEFAULT_COLUMNS");

        $dataTable->addOrderBy('name');

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
            ->from(AssemblyBOMEntry::class, 'bom_entry')
            ->leftJoin('bom_entry.part', 'part')
            ->leftJoin('bom_entry.referencedAssembly', 'referencedAssembly')
            ->leftJoin('part.category', 'category')
            ->leftJoin('part.footprint', 'footprint')
            ->leftJoin('part.manufacturer', 'manufacturer')
            ->where('bom_entry.assembly = :assembly')
            ->setParameter('assembly', $options['assembly'])
        ;
    }

    private function buildCriteria(QueryBuilder $builder, array $options): void
    {

    }
}
