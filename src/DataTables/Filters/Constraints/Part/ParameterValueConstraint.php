<?php

namespace App\DataTables\Filters\Constraints\Part;

use App\DataTables\Filters\Constraints\NumberConstraint;
use Doctrine\ORM\QueryBuilder;

class ParameterValueConstraint extends NumberConstraint
{
    protected $alias;

    public const ALLOWED_OPERATOR_VALUES = ['=', '!=', '<', '>', '<=', '>=', 'BETWEEN',
        //Additional operators
        'IN_RANGE', 'NOT_IN_RANGE', 'GREATER_THAN_RANGE', 'GREATER_EQUAL_RANGE', 'LESS_THAN_RANGE', 'LESS_EQUAL_RANGE', 'RANGE_IN_RANGE', 'RANGE_INTERSECT_RANGE'];

    /**
     * @param  string  $alias The alias which is used in the sub query of ParameterConstraint
     */
    public function __construct(string $alias) {
        $this->alias = $alias;

        parent::__construct($alias . '.value_typical');
    }

    public function apply(QueryBuilder $queryBuilder): void
    {
        //Skip if not enabled
        if(!$this->isEnabled()) {
            return;
        }

        $paramName1 = $this->generateParameterIdentifier('value1');
        $paramName2 = $this->generateParameterIdentifier('value2');

        if ($this->operator === 'IN_RANGE') {

            $queryBuilder->andWhere(
                "({$this->alias}.value_min <= :{$paramName1} AND {$this->alias}.value_max >= :{$paramName1}) OR
                ({$this->alias}.value_typical = :{$paramName1})"
            );

            $queryBuilder->setParameter($paramName1, $this->value1);

            return;
        }

        if ($this->operator === 'NOT_IN_RANGE') {

            $queryBuilder->andWhere(
                "({$this->alias}.value_min > :{$paramName1} OR {$this->alias}.value_max < :{$paramName1}) AND
                ({$this->alias}.value_typical IS NULL OR {$this->alias}.value_typical != :{$paramName1})"
            );

            $queryBuilder->setParameter($paramName1, $this->value1);

            return;
        }

        if ($this->operator === 'GREATER_THAN_RANGE') {
            $queryBuilder->andWhere(
                "{$this->alias}.value_max < :{$paramName1} OR {$this->alias}.value_typical < :{$paramName1}"
            );

            $queryBuilder->setParameter($paramName1, $this->value1);

            return;
        }

        if ($this->operator === 'GREATER_EQUAL_RANGE') {
            $queryBuilder->andWhere(
                "{$this->alias}.value_max <= :{$paramName1} OR {$this->alias}.value_typical <= :{$paramName1}"
            );

            $queryBuilder->setParameter($paramName1, $this->value1);

            return;
        }

        if ($this->operator === 'LESS_THAN_RANGE') {
            $queryBuilder->andWhere(
                "{$this->alias}.value_min > :{$paramName1} OR {$this->alias}.value_typical > :{$paramName1}"
            );

            $queryBuilder->setParameter($paramName1, $this->value1);

            return;
        }

        if ($this->operator === 'LESS_EQUAL_RANGE') {
            $queryBuilder->andWhere(
                "{$this->alias}.value_min >= :{$paramName1} OR {$this->alias}.value_typical >= :{$paramName1}"
            );

            $queryBuilder->setParameter($paramName1, $this->value1);

            return;
        }

        // This operator means the constraint range must lie completely within the parameter value range
        if ($this->operator === 'RANGE_IN_RANGE') {
            $queryBuilder->andWhere(
                "({$this->alias}.value_min <= :{$paramName1} AND {$this->alias}.value_max >= :{$paramName2}) OR
                ({$this->alias}.value_typical >= :{$paramName1} AND {$this->alias}.value_typical <= :{$paramName2})"
            );

            $queryBuilder->setParameter($paramName1, $this->value1);
            $queryBuilder->setParameter($paramName2, $this->value2);

            return;
        }

        if ($this->operator === 'RANGE_INTERSECT_RANGE') {
            $queryBuilder->andWhere(
                //The ORs are important here!!
                "({$this->alias}.value_min <= :{$paramName1} OR {$this->alias}.value_min <= :{$paramName2}) OR
                ({$this->alias}.value_max >= :{$paramName1} OR {$this->alias}.value_max >= :{$paramName2}) OR
                ({$this->alias}.value_typical >= :{$paramName1} AND {$this->alias}.value_typical <= :{$paramName2})"
            );

            $queryBuilder->setParameter($paramName1, $this->value1);
            $queryBuilder->setParameter($paramName2, $this->value2);

            return;
        }


        //For all other cases use the default implementation
        parent::apply($queryBuilder);
    }
}