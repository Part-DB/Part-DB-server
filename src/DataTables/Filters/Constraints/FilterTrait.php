<?php

namespace App\DataTables\Filters\Constraints;

use Doctrine\ORM\QueryBuilder;

trait FilterTrait
{

    /**
     * Generates a parameter identifier that can be used for the given property. It gives random results, to be unique, so you have to cache it.
     * @param  string  $property
     * @return string
     */
    protected function generateParameterIdentifier(string $property): string
    {
        return str_replace('.', '_', $property) . '_' . uniqid("", false);
    }

    /**
     * Adds a simple constraint in the form of (property OPERATOR value) (e.g. "part.name = :name") to the given query builder.
     * @param  QueryBuilder  $queryBuilder
     * @param  string  $property
     * @param  string  $comparison_operator
     * @param  mixed $value
     * @return void
     */
    protected function addSimpleAndConstraint(QueryBuilder $queryBuilder, string $property, string $parameterIdentifier, string $comparison_operator, $value): void
    {
        $queryBuilder->andWhere(sprintf("%s %s :%s", $property, $comparison_operator, $parameterIdentifier));
        $queryBuilder->setParameter($parameterIdentifier, $value);
    }
}