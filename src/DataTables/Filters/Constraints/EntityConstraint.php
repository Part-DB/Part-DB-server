<?php

namespace App\DataTables\Filters\Constraints;

use App\Entity\Base\AbstractDBElement;
use App\Entity\Base\AbstractStructuralDBElement;
use App\Services\Trees\NodesListBuilder;
use Doctrine\ORM\QueryBuilder;

/**
 * @template T
 */
class EntityConstraint extends AbstractConstraint
{
    private const ALLOWED_OPERATOR_VALUES_BASE = ['=', '!='];
    private const ALLOWED_OPERATOR_VALUES_STRUCTURAL = ['INCLUDING_CHILDREN', 'EXCLUDING_CHILDREN'];

    /**
     * @var
     */
    protected $nodesListBuilder;

    /**
     * @var class-string<T> The class to use for the comparison
     */
    protected $class;

    /**
     * @var string|null The operator to use
     */
    protected $operator;

    /**
     * @var T The value to compare to
     */
    protected $value;

    /**
     * @param  NodesListBuilder  $nodesListBuilder
     * @param  class-string<T>  $class
     * @param  string  $property
     * @param  string|null  $identifier
     * @param $value
     * @param  string $operator
     */
    public function __construct(NodesListBuilder $nodesListBuilder, string $class, string $property, string $identifier = null, $value = null, string $operator = '')
    {
        $this->nodesListBuilder = $nodesListBuilder;
        $this->class = $class;

        parent::__construct($property, $identifier);
        $this->value = $value;
        $this->operator = $operator;
    }

    public function getClass(): string
    {
        return $this->class;
    }

    /**
     * @return string|null
     */
    public function getOperator(): ?string
    {
        return $this->operator;
    }

    /**
     * @param  string|null  $operator
     */
    public function setOperator(?string $operator): self
    {
        $this->operator = $operator;
        return $this;
    }

    /**
     * @return mixed|null
     */
    public function getValue(): ?AbstractDBElement
    {
        return $this->value;
    }

    /**
     * @param  T|null $value
     */
    public function setValue(?AbstractDBElement $value): void
    {
        if (!$value instanceof $this->class) {
            throw new \InvalidArgumentException('The value must be an instance of ' . $this->class);
        }

        $this->value = $value;
    }

    /**
     * Checks whether the constraints apply to a structural type or not
     * @return bool
     */
    public function isStructural(): bool
    {
        return is_subclass_of($this->class, AbstractStructuralDBElement::class);
    }

    /**
     * Returns a list of operators which are allowed with the given class.
     * @return string[]
     */
    public function getAllowedOperatorValues(): array
    {
        //Base operators are allowed for everything
        $tmp = self::ALLOWED_OPERATOR_VALUES_BASE;

        if ($this->isStructural()) {
            $tmp = array_merge($tmp, self::ALLOWED_OPERATOR_VALUES_STRUCTURAL);
        }

        return $tmp;
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
        if(!in_array($this->operator, $this->getAllowedOperatorValues(), true)) {
            throw new \RuntimeException('Invalid operator '. $this->operator . ' provided. Valid operators are '. implode(', ', $this->getAllowedOperatorValues()));
        }

        //We need to handle null values differently, as they can not be compared with == or !=
        if ($this->value === null) {
            if($this->operator === '=' || $this->operator === 'INCLUDING_CHILDREN') {
                $queryBuilder->andWhere(sprintf("%s IS NULL", $this->property));
                return;
            }

            if ($this->operator === '!=' || $this->operator === 'EXCLUDING_CHILDREN') {
                $queryBuilder->andWhere(sprintf("%s IS NOT NULL", $this->property));
                return;
            }

            throw new \RuntimeException('Unknown operator '. $this->operator . ' provided. Valid operators are '. implode(', ', $this->getAllowedOperatorValues()));
        }

        if($this->operator === '=' || $this->operator === '!=') {
           $this->addSimpleAndConstraint($queryBuilder, $this->property, $this->identifier, $this->operator, $this->value);
           return;
        }

        //Otherwise retrieve the children list and apply the operator to it
        if($this->isStructural()) {
            $list = $this->nodesListBuilder->getChildrenFlatList($this->value);
            //Add the element itself to the list
            $list[] = $this->value;

            if ($this->operator === 'INCLUDING_CHILDREN') {
                $this->addSimpleAndConstraint($queryBuilder, $this->property, $this->identifier, 'IN', $list);
                return;
            }

            if ($this->operator === 'EXCLUDING_CHILDREN') {
                $this->addSimpleAndConstraint($queryBuilder, $this->property, $this->identifier, 'NOT IN', $list);
                return;
            }
        } else {
            throw new \RuntimeException('Cannot apply operator '. $this->operator . ' to non-structural type');
        }

    }
}