<?php
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

declare(strict_types=1);


namespace App\DataTables\Adapters;

use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\Query;
use Doctrine\ORM\Query\Expr\Select;
use Doctrine\ORM\Query\ResultSetMapping;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\CountOutputWalker;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;
use Omines\DataTablesBundle\Adapter\AdapterQuery;
use Omines\DataTablesBundle\Adapter\Doctrine\Event\ORMAdapterQueryEvent;
use Omines\DataTablesBundle\Adapter\Doctrine\ORMAdapter;
use Omines\DataTablesBundle\Adapter\Doctrine\ORMAdapterEvents;
use Omines\DataTablesBundle\Column\AbstractColumn;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * This adapter fetches entities from the database in two steps:
 * In the first step, it uses a simple query (filter_query option), which returns only the ids of the main entity,
 * to get the count of results, doing filtering and pagination.
 *
 * In the next step the IDs are passed to the detail_query callback option, which then can do a more complex queries (like fetch join)
 * to get the entities and related stuff in an efficient way.
 * This way we save the overhead of the fetch join query for the count and counting, which can be very slow, cause
 * no indexes can be used.
 */
class TwoStepORMAdapter extends ORMAdapter
{
    private \Closure $detailQueryCallable;

    private bool $use_simple_total = false;

    private \Closure|null $query_modifier;

    public function __construct(ManagerRegistry $registry = null)
    {
        parent::__construct($registry);
        $this->detailQueryCallable = static function (QueryBuilder $qb, array $ids) {
            throw new \RuntimeException('You need to set the detail_query option to use the TwoStepORMAdapter');
        };
    }

    protected function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);

        $resolver->setRequired('filter_query');
        $resolver->setDefault('query', function (Options $options) {
            return $options['filter_query'];
        });

        $resolver->setRequired('detail_query');
        $resolver->setAllowedTypes('detail_query', \Closure::class);

        /*
         * Add the possibility to replace the query for total entity count through a very simple one, to improve performance.
         * You can only use this option, if you did not apply any criteria to your total count.
         */
        $resolver->setDefault('simple_total_query', false);

        //Add the possibility of a closure to modify the query builder before the query is executed
        $resolver->setDefault('query_modifier', null);
        $resolver->setAllowedTypes('query_modifier', ['null', \Closure::class]);

    }

    protected function afterConfiguration(array $options): void
    {
        parent::afterConfiguration($options);
        $this->detailQueryCallable = $options['detail_query'];
        $this->use_simple_total = $options['simple_total_query'];
        $this->query_modifier = $options['query_modifier'];
    }

    protected function prepareQuery(AdapterQuery $query): void
    {
        //Like the parent class, but we add the possibility to use the simple total query

        $state = $query->getState();
        $query->set('qb', $builder = $this->createQueryBuilder($state));
        $query->set('rootAlias', $rootAlias = $builder->getDQLPart('from')[0]->getAlias());

        // Provide default field mappings if needed
        foreach ($state->getDataTable()->getColumns() as $column) {
            if (null === $column->getField() && isset($this->metadata->fieldMappings[$name = $column->getName()])) {
                $column->setOption('field', "{$rootAlias}.{$name}");
            }
        }

        /** @var Query\Expr\From $fromClause */
        $fromClause = $builder->getDQLPart('from')[0];
        $identifier = "{$fromClause->getAlias()}.{$this->metadata->getSingleIdentifierFieldName()}";

        // Use simpler (faster) total count query if the user wanted so...
        if ($this->use_simple_total) {
            $query->setTotalRows($this->getSimpleTotalCount($builder));
        } else {
            $query->setTotalRows($this->getCount($builder, $identifier));
        }

        // Get record count after filtering
        $this->buildCriteria($builder, $state);
        $query->setFilteredRows($this->getCount($builder, $identifier));

        // Perform mapping of all referred fields and implied fields
        $aliases = $this->getAliases($query);
        $query->set('aliases', $aliases);
        $query->setIdentifierPropertyPath($this->mapFieldToPropertyPath($identifier, $aliases));
    }

    protected function getCount(QueryBuilder $queryBuilder, $identifier): int
    {
        if ($this->query_modifier !== null) {
            $queryBuilder = $this->query_modifier->__invoke(clone $queryBuilder);
        }

        //Check if the queryBuilder is having a HAVING clause, which would make the count query invalid
        if (empty($queryBuilder->getDQLPart('having'))) {
            //If not, we can use the simple count query
            return parent::getCount($queryBuilder, $identifier);
        }

        //Otherwise Use the paginator, which uses a subquery to get the right count even with HAVING clauses
        return (new Paginator($queryBuilder, false))->count();
    }

    protected function getResults(AdapterQuery $query): \Traversable
    {
        //Very similar to the parent class
        /** @var QueryBuilder $builder */
        $builder = $query->get('qb');
        $state = $query->getState();

        // Apply definitive view state for current 'page' of the table
        foreach ($state->getOrderBy() as list($column, $direction)) {
            /** @var AbstractColumn $column */
            if ($column->isOrderable()) {
                $builder->addOrderBy($column->getOrderField(), $direction);
            }
        }
        if (null !== $state->getLength()) {
            $builder
                ->setFirstResult($state->getStart())
                ->setMaxResults($state->getLength())
            ;
        }

        //Apply the query modifier, if set
        if ($this->query_modifier !== null) {
            $builder = $this->query_modifier->__invoke($builder);
        }

        $id_query = $builder->getQuery();
        $event = new ORMAdapterQueryEvent($id_query);
        $state->getDataTable()->getEventDispatcher()->dispatch($event, ORMAdapterEvents::PRE_QUERY);

        //In the first step we only get the ids of the main entity...
        $ids = $id_query->getArrayResult();

        //Which is then passed to the detailQuery, which filters for the entities based on the IDs
        $detail_qb = $this->manager->createQueryBuilder();
        $this->detailQueryCallable->__invoke($detail_qb, $ids);

        $detail_query = $detail_qb->getQuery();

        //We pass the results of the detail query to the datatable for view rendering
        foreach ($detail_query->getResult() as $item) {
            yield $item;
        }
    }

    protected function getSimpleTotalCount(QueryBuilder $queryBuilder): int
    {
        /** The paginator count queries can be rather slow, so when query for total count (100ms or longer),
         * just return the entity count.
         */
        /** @var Query\Expr\From $from_expr */
        $from_expr = $queryBuilder->getDQLPart('from')[0];

        return $this->manager->getRepository($from_expr->getFrom())->count([]);
    }
}