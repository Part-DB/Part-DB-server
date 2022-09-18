<?php

namespace App\DataTables\Filters\Constraints;

use Doctrine\DBAL\ParameterType;
use Doctrine\ORM\QueryBuilder;

trait FilterTrait
{

    protected bool $useHaving = false;

    public function useHaving($value = true): self
    {
        $this->useHaving = $value;
        return $this;
    }

    /**
     * Checks if the given input is an aggregateFunction like COUNT(part.partsLot) or so
     * @return bool
     */
    protected function isAggregateFunctionString(string $input): bool
    {
        return preg_match('/^[a-zA-Z]+\(.*\)$/', $input) === 1;
    }

    /**
     * Generates a parameter identifier that can be used for the given property. It gives random results, to be unique, so you have to cache it.
     * @param  string  $property
     * @return string
     */
    protected function generateParameterIdentifier(string $property): string
    {
        //Replace all special characters with underscores
        $property = preg_replace('/[^a-zA-Z0-9_]/', '_', $property);
        //Add a random number to the end of the property name for uniqueness
        return $property . '_' . uniqid("", false);
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
        if ($comparison_operator === 'IN' || $comparison_operator === 'NOT IN') {
            $expression = sprintf("%s %s (:%s)", $property, $comparison_operator, $parameterIdentifier);
        } else {
            $expression = sprintf("%s %s :%s", $property, $comparison_operator, $parameterIdentifier);
        }

        if($this->useHaving || $this->isAggregateFunctionString($property)) { //If the property is an aggregate function, we have to use the "having" instead of the "where"
            $queryBuilder->andHaving($expression);
        } else {
            $queryBuilder->andWhere($expression);
        }

        $queryBuilder->setParameter($parameterIdentifier, $value);
    }
}