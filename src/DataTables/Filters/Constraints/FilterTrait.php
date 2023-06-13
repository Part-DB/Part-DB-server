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
namespace App\DataTables\Filters\Constraints;

use Doctrine\ORM\QueryBuilder;

trait FilterTrait
{

    protected bool $useHaving = false;

    public function useHaving($value = true): static
    {
        $this->useHaving = $value;
        return $this;
    }

    /**
     * Checks if the given input is an aggregateFunction like COUNT(part.partsLot) or so
     */
    protected function isAggregateFunctionString(string $input): bool
    {
        return preg_match('/^[a-zA-Z]+\(.*\)$/', $input) === 1;
    }

    /**
     * Generates a parameter identifier that can be used for the given property. It gives random results, to be unique, so you have to cache it.
     */
    protected function generateParameterIdentifier(string $property): string
    {
        //Replace all special characters with underscores
        $property = preg_replace('/\W/', '_', $property);
        //Add a random number to the end of the property name for uniqueness
        return $property . '_' . uniqid("", false);
    }

    /**
     * Adds a simple constraint in the form of (property OPERATOR value) (e.g. "part.name = :name") to the given query builder.
     */
    protected function addSimpleAndConstraint(QueryBuilder $queryBuilder, string $property, string $parameterIdentifier, string $comparison_operator, mixed $value): void
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
