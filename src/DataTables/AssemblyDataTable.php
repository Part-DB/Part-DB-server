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

use App\DataTables\Adapters\TwoStepORMAdapter;
use App\DataTables\Column\IconLinkColumn;
use App\DataTables\Column\LocaleDateTimeColumn;
use App\DataTables\Column\MarkdownColumn;
use App\DataTables\Column\SelectColumn;
use App\DataTables\Filters\AssemblyFilter;
use App\DataTables\Filters\AssemblySearchFilter;
use App\DataTables\Helpers\AssemblyDataTableHelper;
use App\DataTables\Helpers\ColumnSortHelper;
use App\Doctrine\Helpers\FieldHelper;
use App\Entity\AssemblySystem\Assembly;
use App\Services\EntityURLGenerator;
use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\QueryBuilder;
use Omines\DataTablesBundle\Adapter\Doctrine\ORM\SearchCriteriaProvider;
use Omines\DataTablesBundle\Column\TextColumn;
use Omines\DataTablesBundle\DataTable;
use Omines\DataTablesBundle\DataTableTypeInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

final class AssemblyDataTable implements DataTableTypeInterface
{
    const LENGTH_MENU = [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]];

    public function __construct(
        private readonly EntityURLGenerator $urlGenerator,
        private readonly TranslatorInterface $translator,
        private readonly AssemblyDataTableHelper $assemblyDataTableHelper,
        private readonly Security $security,
        private readonly string $visible_columns,
        private readonly ColumnSortHelper $csh,
    ) {
    }

    public function configureOptions(OptionsResolver $optionsResolver): void
    {
        $optionsResolver->setDefaults([
            'filter' => null,
            'search' => null
        ]);

        $optionsResolver->setAllowedTypes('filter', [AssemblyFilter::class, 'null']);
        $optionsResolver->setAllowedTypes('search', [AssemblySearchFilter::class, 'null']);
    }

    public function configure(DataTable $dataTable, array $options): void
    {
        $resolver = new OptionsResolver();
        $this->configureOptions($resolver);
        $options = $resolver->resolve($options);

        $this->csh
            ->add('select', SelectColumn::class, visibility_configurable: false)
            ->add('picture', TextColumn::class, [
                'label' => '',
                'className' => 'no-colvis',
                'render' => fn($value, Assembly $context) => $this->assemblyDataTableHelper->renderPicture($context),
            ], visibility_configurable: false)
            ->add('name', TextColumn::class, [
                'label' => $this->translator->trans('assembly.table.name'),
                'render' => fn($value, Assembly $context) => $this->assemblyDataTableHelper->renderName($context),
                'orderField' => 'NATSORT(assembly.name)'
            ])
            ->add('id', TextColumn::class, [
                'label' => $this->translator->trans('assembly.table.id'),
            ])
            ->add('ipn', TextColumn::class, [
                'label' => $this->translator->trans('assembly.table.ipn'),
                'orderField' => 'NATSORT(assembly.ipn)'
            ])
            ->add('description', MarkdownColumn::class, [
                'label' => $this->translator->trans('assembly.table.description'),
            ])
            ->add('addedDate', LocaleDateTimeColumn::class, [
                'label' => $this->translator->trans('assembly.table.addedDate'),
            ])
            ->add('lastModified', LocaleDateTimeColumn::class, [
                'label' => $this->translator->trans('assembly.table.lastModified'),
            ]);

        //Add a assembly column to list where the assembly is used as referenced assembly as bom-entry, when the user has the permission to see the assemblies
        if ($this->security->isGranted('read', Assembly::class)) {
            $this->csh->add('referencedAssemblies', TextColumn::class, [
                'label' => $this->translator->trans('assembly.referencedAssembly.labelp'),
                'render' => function ($value, Assembly $context): string {
                    $assemblies = $context->getReferencedAssemblies();

                    $max = 5;
                    $tmp = "";

                    for ($i = 0; $i < min($max, count($assemblies)); $i++) {
                        $url = $this->urlGenerator->infoURL($assemblies[$i]);
                        $tmp .= sprintf('<a href="%s">%s</a>', $url, htmlspecialchars($assemblies[$i]->getName()));
                        if ($i < count($assemblies) - 1) {
                            $tmp .= ", ";
                        }
                    }

                    if (count($assemblies) > $max) {
                        $tmp .= ", + ".(count($assemblies) - $max);
                    }

                    return $tmp;
                }
            ]);
        }

        $this->csh
            ->add('edit', IconLinkColumn::class, [
                'label' => $this->translator->trans('assembly.table.edit'),
                'href' => fn($value, Assembly $context) => $this->urlGenerator->editURL($context),
                'disabled' => fn($value, Assembly $context) => !$this->security->isGranted('edit', $context),
                'title' => $this->translator->trans('assembly.table.edit.title'),
            ]);

        //Apply the user configured order and visibility and add the columns to the table
        $this->csh->applyVisibilityAndConfigureColumns($dataTable, $this->visible_columns, "TABLE_ASSEMBLIES_DEFAULT_COLUMNS");

        $dataTable->addOrderBy('name')
            ->createAdapter(TwoStepORMAdapter::class, [
                'filter_query' => $this->getFilterQuery(...),
                'detail_query' => $this->getDetailQuery(...),
                'entity' => Assembly::class,
                'hydrate' => AbstractQuery::HYDRATE_OBJECT,
                //Use the simple total query, as we just want to get the total number of assemblies without any conditions
                //For this the normal query would be pretty slow
                'simple_total_query' => true,
                'criteria' => [
                    function (QueryBuilder $builder) use ($options): void {
                        $this->buildCriteria($builder, $options);
                    },
                    new SearchCriteriaProvider(),
                ],
                'query_modifier' => $this->addJoins(...),
            ]);
    }


    private function getFilterQuery(QueryBuilder $builder): void
    {
        /* In the filter query we only select the IDs. The fetching of the full entities is done in the detail query.
         * We only need to join the entities here, so we can filter by them.
         * The filter conditions are added to this QB in the buildCriteria method.
         *
         * The amountSum field and the joins are dynamically added by the addJoins method, if the fields are used in the query.
         * This improves the performance, as we do not need to join all tables, if we do not need them.
         */
        $builder
            ->select('assembly.id')
            ->from(Assembly::class, 'assembly')

            //The other group by fields, are dynamically added by the addJoins method
            ->addGroupBy('assembly');
    }

    private function getDetailQuery(QueryBuilder $builder, array $filter_results): void
    {
        $ids = array_map(static fn($row) => $row['id'], $filter_results);

        /*
         * In this query we take the IDs which were filtered, paginated and sorted in the filter query, and fetch the
         * full entities.
         * We can do complex fetch joins, as we do not need to filter or sort here (which would kill the performance).
         * The only condition should be for the IDs.
         * It is important that elements are ordered the same way, as the IDs are passed, or ordering will be wrong.
         *
         * We do not require the subqueries like amountSum here, as it is not used to render the table (and only for sorting)
         */
        $builder
            ->select('assembly')
            ->addSelect('master_picture_attachment')
            ->addSelect('attachments')
            ->from(Assembly::class, 'assembly')
            ->leftJoin('assembly.master_picture_attachment', 'master_picture_attachment')
            ->leftJoin('assembly.attachments', 'attachments')
            ->where('assembly.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->addGroupBy('assembly')
            ->addGroupBy('master_picture_attachment')
            ->addGroupBy('attachments');

        //Get the results in the same order as the IDs were passed
        FieldHelper::addOrderByFieldParam($builder, 'assembly.id', 'ids');
    }

    /**
     * This function is called right before the filter query is executed.
     * We use it to dynamically add joins to the query, if the fields are used in the query.
     * @param  QueryBuilder  $builder
     * @return QueryBuilder
     */
    private function addJoins(QueryBuilder $builder): QueryBuilder
    {
        //Check if the query contains certain conditions, for which we need to add additional joins
        //The join fields get prefixed with an underscore, so we can check if they are used in the query easy without confusing them for a assembly subfield
        $dql = $builder->getDQL();

        if (str_contains($dql, '_master_picture_attachment')) {
            $builder->leftJoin('assembly.master_picture_attachment', '_master_picture_attachment');
            $builder->addGroupBy('_master_picture_attachment');
        }
        if (str_contains($dql, '_attachments')) {
            $builder->leftJoin('assembly.attachments', '_attachments');
        }

        return $builder;
    }

    private function buildCriteria(QueryBuilder $builder, array $options): void
    {
        //Apply the search criterias first
        if ($options['search'] instanceof AssemblySearchFilter) {
            $search = $options['search'];
            $search->apply($builder);
        }

        //We do the most stuff here in the filter class
        if ($options['filter'] instanceof AssemblyFilter) {
            $filter = $options['filter'];
            $filter->apply($builder);
        }
    }
}
