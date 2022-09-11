<?php

namespace App\DataTables\Filters\Constraints;

use Doctrine\ORM\QueryBuilder;

class ChoiceConstraint extends AbstractConstraint
{
    public const ALLOWED_OPERATOR_VALUES = ['ANY', 'NONE'];

    /**
     * @var string[]|int[] The values to compare to
     */
    protected $value;

    /**
     * @var string The operator to use
     */
    protected $operator;

    /**
     * @return string[]|int[]
     */
    public function getValue(): array
    {
        return $this->value;
    }

    /**
     * @param  string[]|int[]  $value
     * @return ChoiceConstraint
     */
    public function setValue(array $value): ChoiceConstraint
    {
        $this->value = $value;
        return $this;
    }

    /**
     * @return string
     */
    public function getOperator(): string
    {
        return $this->operator;
    }

    /**
     * @param  string  $operator
     * @return ChoiceConstraint
     */
    public function setOperator(string $operator): ChoiceConstraint
    {
        $this->operator = $operator;
        return $this;
    }



    public function isEnabled(): bool
    {
        return !empty($this->operator);
    }

    public function apply(QueryBuilder $queryBuilder): void
    {
        //If no value is provided then we do not apply a filter
        if (!$this->isEnabled()) {
            return;
        }

        //Ensure we have an valid operator
        if(!in_array($this->operator, self::ALLOWED_OPERATOR_VALUES, true)) {
            throw new \RuntimeException('Invalid operator '. $this->operator . ' provided. Valid operators are '. implode(', ', self::ALLOWED_OPERATOR_VALUES));
        }

        if ($this->operator === 'ANY') {
            $this->addSimpleAndConstraint($queryBuilder, $this->property, $this->identifier, 'IN', $this->value);
        } elseif ($this->operator === 'NONE') {
            $this->addSimpleAndConstraint($queryBuilder, $this->property, $this->identifier, 'NOT IN', $this->value);
        } else {
            throw new \RuntimeException('Unknown operator '. $this->operator . ' provided. Valid operators are '. implode(', ', self::ALLOWED_OPERATOR_VALUES));
        }
    }
}