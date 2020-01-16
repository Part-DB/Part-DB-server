<?php

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

namespace App\DataTables\Adapter;

use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Omines\DataTablesBundle\Adapter\AbstractAdapter;
use Omines\DataTablesBundle\Adapter\AdapterQuery;
use Omines\DataTablesBundle\Adapter\Doctrine\Event\ORMAdapterQueryEvent;
use Omines\DataTablesBundle\Adapter\Doctrine\ORM\AutomaticQueryBuilder;
use Omines\DataTablesBundle\Adapter\Doctrine\ORM\QueryBuilderProcessorInterface;
use Omines\DataTablesBundle\Adapter\Doctrine\ORM\SearchCriteriaProvider;
use Omines\DataTablesBundle\Adapter\Doctrine\ORMAdapterEvents;
use Omines\DataTablesBundle\Column\AbstractColumn;
use Omines\DataTablesBundle\DataTableState;
use Omines\DataTablesBundle\Exception\InvalidConfigurationException;
use Omines\DataTablesBundle\Exception\MissingDependencyException;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Traversable;

/**
 * Override default ORM Adapter, to allow fetch joins (allow addSelect with ManyToOne Collections).
 * This should improves performance for Part Tables.
 * Based on: https://github.com/omines/datatables-bundle/blob/master/tests/Fixtures/AppBundle/DataTable/Adapter/CustomORMAdapter.php.
 */
class CustomORMAdapter extends AbstractAdapter
{
    /** @var ManagerRegistry */
    private $registry;

    /** @var EntityManager */
    private $manager;

    /** @var \Doctrine\ORM\Mapping\ClassMetadata */
    private $metadata;

    /** @var int */
    private $hydrationMode;

    /** @var QueryBuilderProcessorInterface[] */
    private $queryBuilderProcessors;

    /** @var QueryBuilderProcessorInterface[] */
    protected $criteriaProcessors;

    /** @var bool */
    protected $allow_fetch_join;


    /**
     * DoctrineAdapter constructor.
     */
    public function __construct(ManagerRegistry $registry = null)
    {
        if (null === $registry) {
            throw new MissingDependencyException('Install doctrine/doctrine-bundle to use the ORMAdapter');
        }

        parent::__construct();
        $this->registry = $registry;
    }

    /**
     * {@inheritdoc}
     */
    public function configure(array $options)
    {
        $resolver = new OptionsResolver();
        $this->configureOptions($resolver);
        $options = $resolver->resolve($options);

        // Enable automated mode or just get the general default entity manager
        if (null === ($this->manager = $this->registry->getManagerForClass($options['entity']))) {
            throw new InvalidConfigurationException(sprintf('Doctrine has no manager for entity "%s", is it correctly imported and referenced?', $options['entity']));
        }
        $this->metadata = $this->manager->getClassMetadata($options['entity']);
        if (empty($options['query'])) {
            $options['query'] = [new AutomaticQueryBuilder($this->manager, $this->metadata)];
        }

        // Set options
        $this->hydrationMode = $options['hydrate'];
        $this->queryBuilderProcessors = $options['query'];
        $this->criteriaProcessors = $options['criteria'];
        $this->allow_fetch_join = $options['allow_fetch_join'];
    }

    /**
     * @param mixed $processor
     */
    public function addCriteriaProcessor($processor)
    {
        $this->criteriaProcessors[] = $this->normalizeProcessor($processor);
    }

    protected function prepareQuery(AdapterQuery $query)
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


        $query->setTotalRows($this->getCount($builder, $identifier, true));

        // Get record count after filtering
        $this->buildCriteria($builder, $state);
        $query->setFilteredRows($this->getCount($builder, $identifier, false));

        // Perform mapping of all referred fields and implied fields
        $aliases = $this->getAliases($query);
        $query->set('aliases', $aliases);
        $query->setIdentifierPropertyPath($this->mapFieldToPropertyPath($identifier, $aliases));
    }

    /**
     * @return array
     */
    protected function getAliases(AdapterQuery $query)
    {
        /** @var QueryBuilder $builder */
        $builder = $query->get('qb');
        $aliases = [];

        /** @var Query\Expr\From $from */
        foreach ($builder->getDQLPart('from') as $from) {
            $aliases[$from->getAlias()] = [null, $this->manager->getMetadataFactory()->getMetadataFor($from->getFrom())];
        }

        // Alias all joins
        foreach ($builder->getDQLPart('join') as $joins) {
            /** @var Query\Expr\Join $join */
            foreach ($joins as $join) {
                if (false === mb_strstr($join->getJoin(), '.')) {
                    continue;
                }

                list($origin, $target) = explode('.', $join->getJoin());

                $mapping = $aliases[$origin][1]->getAssociationMapping($target);
                $aliases[$join->getAlias()] = [$join->getJoin(), $this->manager->getMetadataFactory()->getMetadataFor($mapping['targetEntity'])];
            }
        }

        return $aliases;
    }

    /**
     * {@inheritdoc}
     */
    protected function mapPropertyPath(AdapterQuery $query, AbstractColumn $column)
    {
        return $this->mapFieldToPropertyPath($column->getField(), $query->get('aliases'));
    }

    protected function getResults(AdapterQuery $query): \Traversable
    {
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
        if ($state->getLength() > 0) {
            $builder
                ->setFirstResult($state->getStart())
                ->setMaxResults($state->getLength());
        }

        $query = $builder->getQuery();
        $event = new ORMAdapterQueryEvent($query);
        $state->getDataTable()->getEventDispatcher()->dispatch($event, ORMAdapterEvents::PRE_QUERY);

        if ($this->allow_fetch_join && $this->hydrationMode === Query::HYDRATE_OBJECT) {
            $paginator = new Paginator($query);
            $iterator = $paginator->getIterator();
        } else {
            $iterator = $query->iterate([], $this->hydrationMode);
        }

        foreach ($iterator as $result) {
            if (Query::HYDRATE_OBJECT === $this->hydrationMode) {
                yield $entity = $result;
                $this->manager->detach($entity);
            } else {
                yield $entity = array_values($result)[0];
            }
        }
    }

    protected function buildCriteria(QueryBuilder $queryBuilder, DataTableState $state)
    {
        foreach ($this->criteriaProcessors as $provider) {
            $provider->process($queryBuilder, $state);
        }
    }

    protected function createQueryBuilder(DataTableState $state): QueryBuilder
    {
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = $this->manager->createQueryBuilder();

        // Run all query builder processors in order
        foreach ($this->queryBuilderProcessors as $processor) {
            $processor->process($queryBuilder, $state);
        }

        return $queryBuilder;
    }

    /**
     * @param $identifier
     * @return int
     */
    protected function getCount(QueryBuilder $queryBuilder, $identifier, $total_count = false)
    {
        if ($this->allow_fetch_join) {
            /** The paginator count queries can be rather slow, so when query for total count (100ms or longer),
             * just return the entity count.
             */
            if ($total_count) {
                /** @var Query\Expr\From $from_expr */
                $from_expr = $queryBuilder->getDQLPart('from')[0];
                return $this->manager->getRepository($from_expr->getFrom())->count([]);
            }

            $paginator = new Paginator($queryBuilder);
            return $paginator->count();
        }

        $qb = clone $queryBuilder;

        $qb->resetDQLPart('orderBy');
        $gb = $qb->getDQLPart('groupBy');
        if (empty($gb) || !$this->hasGroupByPart($identifier, $gb)) {
            $qb->select($qb->expr()->count($identifier));

            return (int) $qb->getQuery()->getSingleScalarResult();
        } else {
            $qb->resetDQLPart('groupBy');
            $qb->select($qb->expr()->countDistinct($identifier));

            return (int) $qb->getQuery()->getSingleScalarResult();
        }
    }

    /**
     * @param $identifier
     * @param Query\Expr\GroupBy[] $gbList
     * @return bool
     */
    protected function hasGroupByPart($identifier, array $gbList)
    {
        foreach ($gbList as $gb) {
            if (in_array($identifier, $gb->getParts(), true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param string $field
     * @return string
     */
    private function mapFieldToPropertyPath($field, array $aliases = [])
    {
        $parts = explode('.', $field);
        if (count($parts) < 2) {
            throw new InvalidConfigurationException(sprintf("Field name '%s' must consist at least of an alias and a field separated with a period", $field));
        }
        list($origin, $target) = $parts;

        $path = [$target];
        $current = $aliases[$origin][0];

        while (null !== $current) {
            list($origin, $target) = explode('.', $current);
            $path[] = $target;
            $current = $aliases[$origin][0];
        }

        if (Query::HYDRATE_ARRAY === $this->hydrationMode) {
            return '[' . implode('][', array_reverse($path)) . ']';
        } else {
            return implode('.', array_reverse($path));
        }
    }

    protected function configureOptions(OptionsResolver $resolver)
    {
        $providerNormalizer = function (Options $options, $value) {
            return array_map([$this, 'normalizeProcessor'], (array) $value);
        };

        $resolver
            ->setDefaults([
                              'hydrate' => Query::HYDRATE_OBJECT,
                              'allow_fetch_join' => false,
                              'query' => [],
                              'criteria' => function (Options $options) {
                                  return [new SearchCriteriaProvider()];
                              },
                          ])
            ->setRequired('entity')
            ->setAllowedTypes('entity', ['string'])
            ->setAllowedTypes('hydrate', 'int')
            ->setAllowedTypes('query', [QueryBuilderProcessorInterface::class, 'array', 'callable'])
            ->setAllowedTypes('criteria', [QueryBuilderProcessorInterface::class, 'array', 'callable', 'null'])
            ->setNormalizer('query', $providerNormalizer)
            ->setNormalizer('criteria', $providerNormalizer)
        ;
    }

    /**
     * @param callable|QueryBuilderProcessorInterface $provider
     * @return QueryBuilderProcessorInterface
     */
    private function normalizeProcessor($provider)
    {
        if ($provider instanceof QueryBuilderProcessorInterface) {
            return $provider;
        } elseif (is_callable($provider)) {
            return new class($provider) implements QueryBuilderProcessorInterface {
                private $callable;

                public function __construct(callable $value)
                {
                    $this->callable = $value;
                }

                public function process(QueryBuilder $queryBuilder, DataTableState $state)
                {
                    return call_user_func($this->callable, $queryBuilder, $state);
                }
            };
        }

        throw new InvalidConfigurationException('Provider must be a callable or implement QueryBuilderProcessorInterface');
    }
}
