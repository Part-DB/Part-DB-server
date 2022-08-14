<?php

namespace App\DataTables\Filters;

use Doctrine\ORM\QueryBuilder;

interface FilterInterface
{

    /**
     * Apply the given filter to the given query builder on the given property
     * @param  QueryBuilder  $queryBuilder
     * @return void
     */
    public function apply(QueryBuilder $queryBuilder): void;
}