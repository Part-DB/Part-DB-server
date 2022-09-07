<?php

namespace App\Form\Filters\Constraints;

class ParameterValueConstraintType extends NumberConstraintType
{
    protected const CHOICES =  [
        '' => '',
        'filter.parameter_value_constraint.operator.=' => '=',
        'filter.parameter_value_constraint.operator.!=' => '!=',
        'filter.parameter_value_constraint.operator.<' => '<',
        'filter.parameter_value_constraint.operator.>' => '>',
        'filter.parameter_value_constraint.operator.<=' => '<=',
        'filter.parameter_value_constraint.operator.>=' => '>=',
        'filter.parameter_value_constraint.operator.BETWEEN' => 'BETWEEN',

        //Extensions by ParameterValueConstraint
        'filter.parameter_value_constraint.operator.IN_RANGE' => 'IN_RANGE',
        'filter.parameter_value_constraint.operator.NOT_IN_RANGE' => 'NOT_IN_RANGE',
        'filter.parameter_value_constraint.operator.GREATER_THAN_RANGE' => 'GREATER_THAN_RANGE',
        'filter.parameter_value_constraint.operator.GREATER_EQUAL_RANGE' => 'GREATER_EQUAL_RANGE',
        'filter.parameter_value_constraint.operator.LESS_THAN_RANGE' => 'LESS_THAN_RANGE',
        'filter.parameter_value_constraint.operator.LESS_EQUAL_RANGE' => 'LESS_EQUAL_RANGE',

        'filter.parameter_value_constraint.operator.RANGE_IN_RANGE' => 'RANGE_IN_RANGE',
        'filter.parameter_value_constraint.operator.RANGE_INTERSECT_RANGE' => 'RANGE_INTERSECT_RANGE'
    ];

    public function getParent(): string
    {
        return NumberConstraintType::class;
    }
}