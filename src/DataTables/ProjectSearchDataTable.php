<?php

declare(strict_types=1);

/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2026 Jan Böhmer (https://github.com/jbtronics)
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

use App\DataTables\Adapters\TwoStepORMAdapter;
use App\DataTables\Column\IconLinkColumn;
use App\DataTables\Column\LocaleDateTimeColumn;
use App\DataTables\Helpers\ColumnSortHelper;
use App\DataTables\Helpers\ProjectDataTableHelper;
use App\DataTables\Filters\ProjectFilter;
use App\DataTables\Filters\ProjectSearchFilter;
use App\DataTables\Column\MarkdownColumn;
use App\Entity\ProjectSystem\Project;
use App\Doctrine\Helpers\FieldHelper;
use App\Services\EntityURLGenerator;
use App\Settings\BehaviorSettings\TableSettings;
use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\QueryBuilder;
use Omines\DataTablesBundle\Adapter\Doctrine\ORM\SearchCriteriaProvider;
use Omines\DataTablesBundle\Column\TextColumn;
use Omines\DataTablesBundle\DataTable;
use Omines\DataTablesBundle\DataTableTypeInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

class ProjectSearchDataTable implements DataTableTypeInterface
{
    public function __construct(
        private readonly ProjectDataTableHelper $projectDataTableHelper,
        private readonly TranslatorInterface $translator,
        private readonly EntityURLGenerator $urlGenerator,
        private readonly Security $security,
        private readonly ColumnSortHelper $csh,
        private readonly TableSettings $tableSettings
    ) {
    }

    public function configureOptions(OptionsResolver $optionsResolver): void
    {
        $optionsResolver->setDefaults([
            'filter' => null,
            'search' => null,
        ]);

        $optionsResolver->setAllowedTypes('filter', [ProjectFilter::class, 'null']);
        $optionsResolver->setAllowedTypes('search', [ProjectSearchFilter::class, 'null']);
    }

    public function configure(DataTable $dataTable, array $options): void
    {
        $resolver = new OptionsResolver();
        $this->configureOptions($resolver);
        $options = $resolver->resolve($options);

        $this->csh
            ->add('picture', TextColumn::class, [
                'label' => '',
                'className' => 'no-colvis',
                'render' => function ($value, Project $context): string {
                    return $this->projectDataTableHelper->renderPicture($context);
                },
                'orderable' => false,
                'searchable' => false,
            ], visibility_configurable: false)
            ->add('name', TextColumn::class, [
                'label' => $this->translator->trans('project.table.name'),
                'render' => function ($value, Project $context): string {
                    return $this->projectDataTableHelper->renderName($context);
                },
                'orderField' => 'NATSORT(project.name)'
            ])
            ->add('id', TextColumn::class, [
                'label' => $this->translator->trans('project.table.id'),
            ])
            ->add('description', MarkdownColumn::class, [
                'label' => $this->translator->trans('project.table.description'),
            ])
            ->add('comment', MarkdownColumn::class, [
                'label' => $this->translator->trans('project.table.comment'),
            ])
            ->add('addedDate', LocaleDateTimeColumn::class, [
                'label' => $this->translator->trans('project.table.addedDate'),
            ])
            ->add('lastModified', LocaleDateTimeColumn::class, [
                'label' => $this->translator->trans('project.table.lastModified'),
            ])
            ->add('edit', IconLinkColumn::class, [
                'label' => $this->translator->trans('project.table.edit'),
                'href' => fn($value, Project $context) => $this->urlGenerator->editURL($context),
                'disabled' => fn($value, Project $context) => !$this->security->isGranted('edit', $context),
                'title' => $this->translator->trans('project.table.edit.title'),
            ]);

        //Apply the user configured order and visibility and add the columns to the table
        $this->csh->applyVisibilityAndConfigureColumns($dataTable, $this->tableSettings->projectsDefaultColumns,
            "TABLE_PROJECTS_DEFAULT_COLUMNS");

        $dataTable->addOrderBy('name')
            ->createAdapter(TwoStepORMAdapter::class, [
                'filter_query' => $this->getFilterQuery(...),
                'detail_query' => $this->getDetailQuery(...),
                'entity' => Project::class,
                'hydrate' => AbstractQuery::HYDRATE_OBJECT,
                'simple_total_query' => true,
                'criteria' => [
                    function (QueryBuilder $builder) use ($options): void {
                        if ($options['search'] instanceof ProjectSearchFilter) {
                            $options['search']->apply($builder);
                        }

                        if ($options['filter'] instanceof ProjectFilter) {
                            $options['filter']->apply($builder);
                        }
                    },
                    new SearchCriteriaProvider(),
                ],
                'query_modifier' => $this->addJoins(...),
            ]);
    }

    public function getFilterQuery(QueryBuilder $builder): void
    {
        $builder
            ->select('project.id')
            ->from(Project::class, 'project');

        $this->addJoins($builder);
    }

    private function addJoins(QueryBuilder $builder): QueryBuilder
    {
        $dql = $builder->getDQL();

        //Helper function to check if a join alias is already present in the QueryBuilder
        $hasJoin = static function (QueryBuilder $qb, string $alias): bool {
            foreach ($qb->getDQLPart('join') as $joins) {
                foreach ($joins as $join) {
                    if ($join->getAlias() === $alias) {
                        return true;
                    }
                }
            }
            return false;
        };

        if (str_contains($dql, '_master_picture_attachment') && !$hasJoin($builder, '_master_picture_attachment')) {
            $builder->leftJoin('project.master_picture_attachment', '_master_picture_attachment');
        }

        return $builder;
    }

    public function getDetailQuery(QueryBuilder $builder, array $filter_results): void
    {
        $ids = array_map(static fn($row) => $row['id'], $filter_results);

        $builder
            ->select('project')
            ->from(Project::class, 'project')
            ->where('project.id IN (:ids)')
            ->setParameter('ids', $ids);

        //Get the results in the same order as the IDs were passed
        FieldHelper::addOrderByFieldParam($builder, 'project.id', 'ids');
    }
}
