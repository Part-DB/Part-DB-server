<?php

declare(strict_types=1);

/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2023 Jan Böhmer (https://github.com/jbtronics)
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
use App\DataTables\Column\EntityColumn;
use App\DataTables\Column\EnumColumn;
use App\DataTables\Column\IconLinkColumn;
use App\DataTables\Column\LocaleDateTimeColumn;
use App\Entity\ProjectSystem\PlannedProject;
use App\Entity\ProjectSystem\PlannedProjectStatus;
use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Omines\DataTablesBundle\Adapter\Doctrine\ORM\SearchCriteriaProvider;
use Omines\DataTablesBundle\Column\TextColumn;
use Omines\DataTablesBundle\DataTable;
use Omines\DataTablesBundle\DataTableTypeInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class PlannedProjectsDataTable implements DataTableTypeInterface
{
    public function __construct(
        private TranslatorInterface $translator,
        private Security $security,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function configure(DataTable $dataTable, array $options): void
    {
        $dataTable
            ->add('name', TextColumn::class, [
                'label' => $this->translator->trans('planned_build.table.name'),
                'orderField' => 'NATSORT(planned_project.name)',
            ])
            ->add('project', EntityColumn::class, [
                'label' => $this->translator->trans('planned_build.table.project'),
                'property' => 'project',
                'orderField' => 'NATSORT(project.name)',
            ])
            ->add('numberOfBuilds', TextColumn::class, [
                'label' => $this->translator->trans('planned_build.table.number_of_builds'),
                'className' => 'text-center',
            ])
            ->add('status', EnumColumn::class, [
                'label' => $this->translator->trans('planned_build.table.status'),
                'data' => static fn (PlannedProject $context): PlannedProjectStatus => $context->getStatus(),
                'class' => PlannedProjectStatus::class,
                'render' => function (PlannedProjectStatus $status): string {
                    $badge_class = match ($status) {
                        PlannedProjectStatus::PLANNED => 'text-bg-primary',
                        PlannedProjectStatus::BUILT => 'text-bg-success',
                        PlannedProjectStatus::CANCELLED => 'text-bg-secondary',
                    };

                    return sprintf(
                        '<span class="badge rounded-pill %s">%s</span>',
                        $badge_class,
                        htmlspecialchars($this->translator->trans($status->toTranslationKey()))
                    );
                },
            ])
            ->add('addedDate', LocaleDateTimeColumn::class, [
                'label' => $this->translator->trans('planned_build.table.added_date'),
            ])
            ->add('info', IconLinkColumn::class, [
                'label' => $this->translator->trans('planned_build.table.info'),
                'className' => 'no-colvis no-export',
                'href' => fn (mixed $value, PlannedProject $context): string => $this->urlGenerator->generate(
                    'planned_project_info',
                    ['id' => $context->getId()]
                ),
                'disabled' => fn (mixed $value, PlannedProject $context): bool => !$this->security->isGranted('read', $context),
                'title' => $this->translator->trans('planned_build.table.info.title'),
            ])
        ;

        $dataTable->addOrderBy('addedDate', DataTable::SORT_DESCENDING);

        $dataTable->createAdapter(TwoStepORMAdapter::class, [
            'entity' => PlannedProject::class,
            'hydrate' => AbstractQuery::HYDRATE_OBJECT,
            'filter_query' => function (QueryBuilder $builder) use ($options): void {
                $this->getFilterQuery($builder, $options);
            },
            'detail_query' => $this->getDetailQuery(...),
            'criteria' => [
                new SearchCriteriaProvider(),
            ],
        ]);
    }

    private function getFilterQuery(QueryBuilder $builder, array $options): void
    {
        $builder
            ->select('planned_project.id')
            ->from(PlannedProject::class, 'planned_project')
            ->leftJoin('planned_project.project', 'project')
        ;
    }

    private function getDetailQuery(QueryBuilder $builder, array $filter_results): void
    {
        $ids = array_map(static fn (array $row) => $row['id'], $filter_results);
        if ($ids === []) {
            $ids = [-1];
        }

        $builder
            ->select('planned_project')
            ->addSelect('project')
            ->from(PlannedProject::class, 'planned_project')
            ->leftJoin('planned_project.project', 'project')
            ->where('planned_project.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->setHint(Query::HINT_READ_ONLY, true)
            ->setHint(Query::HINT_FORCE_PARTIAL_LOAD, false)
        ;
    }
}
