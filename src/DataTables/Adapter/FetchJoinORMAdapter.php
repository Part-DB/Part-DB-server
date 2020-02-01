<?php

declare(strict_types=1);

/**
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 * Copyright (C) 2019 - 2020 Jan Böhmer (https://github.com/jbtronics)
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

namespace App\DataTables\Adapter;

use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Omines\DataTablesBundle\Adapter\AdapterQuery;
use Omines\DataTablesBundle\Adapter\Doctrine\Event\ORMAdapterQueryEvent;
use Omines\DataTablesBundle\Adapter\Doctrine\ORMAdapterEvents;
use Omines\DataTablesBundle\Column\AbstractColumn;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Similar to ORMAdapter this class allows to access objects from the doctrine ORM.
 * Unlike the default ORMAdapter supports Fetch Joins (additional entites are fetched from DB via joins) using
 * the Doctrine Paginator.
 *
 * @author Jan Böhmer
 */
class FetchJoinORMAdapter extends ORMAdapter
{
    protected $use_simple_total;

    public function configure(array $options): void
    {
        parent::configure($options);
        $this->use_simple_total = $options['simple_total_query'];
    }

    public function getResults(AdapterQuery $query): \Traversable
    {
        $builder = $query->get('qb');
        $state = $query->getState();

        // Apply definitive view state for current 'page' of the table
        foreach ($state->getOrderBy() as [$column, $direction]) {
            /** @var AbstractColumn $column */
            if ($column->isOrderable()) {
                $builder->addOrderBy($column->getOrderField(), $direction);
            }
        }
        if ($state->getLength() > 0) {
            $builder
                ->setFirstResult($state->getStart())
                ->setMaxResults($state->getLength());
        }

        $query = $builder->getQuery();
        $event = new ORMAdapterQueryEvent($query);
        $state->getDataTable()->getEventDispatcher()->dispatch($event, ORMAdapterEvents::PRE_QUERY);

        //Use Doctrine paginator for result iteration
        $paginator = new Paginator($query);

        foreach ($paginator->getIterator() as $result) {
            yield $result;
            $this->manager->detach($result);
        }
    }

    public function getCount(QueryBuilder $queryBuilder, $identifier)
    {
        $paginator = new Paginator($queryBuilder);

        return $paginator->count();
    }

    protected function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);

        //Enforce object hydration mode (fetch join only works for objects)
        $resolver->addAllowedValues('hydrate', Query::HYDRATE_OBJECT);

        /*
         * Add the possibility to replace the query for total entity count through a very simple one, to improve performance.
         * You can only use this option, if you did not apply any criteria to your total count.
         */
        $resolver->setDefault('simple_total_query', false);
    }

    protected function prepareQuery(AdapterQuery $query): void
    {
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

        //Use simpler (faster) total count query if the user wanted so...
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

    protected function getSimpleTotalCount(QueryBuilder $queryBuilder)
    {
        /** The paginator count queries can be rather slow, so when query for total count (100ms or longer),
         * just return the entity count.
         */
        /** @var Query\Expr\From $from_expr */
        $from_expr = $queryBuilder->getDQLPart('from')[0];

        return $this->manager->getRepository($from_expr->getFrom())->count([]);
    }
}
