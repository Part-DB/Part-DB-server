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

use App\Entity\Base\AbstractDBElement;
use App\Entity\Base\AbstractStructuralDBElement;
use App\Services\Trees\NodesListBuilder;
use Doctrine\ORM\QueryBuilder;

/**
 * @template T of AbstractDBElement
 */
class EntityConstraint extends AbstractConstraint
{
    private const ALLOWED_OPERATOR_VALUES_BASE = ['=', '!='];
    private const ALLOWED_OPERATOR_VALUES_STRUCTURAL = ['INCLUDING_CHILDREN', 'EXCLUDING_CHILDREN'];

    /**
     * @param  NodesListBuilder|null  $nodesListBuilder
     * @param  class-string<T>  $class
     * @param  string  $property
     * @param  string|null  $identifier
     * @param  null|T  $value
     * @param  string|null  $operator
     */
    public function __construct(protected ?NodesListBuilder $nodesListBuilder,
        protected string $class,
        string $property,
        string $identifier = null,
        protected $value = null,
        protected ?string $operator = null)
    {
        if (!$nodesListBuilder instanceof NodesListBuilder && $this->isStructural()) {
            throw new \InvalidArgumentException('NodesListBuilder must be provided for structural entities');
        }

        parent::__construct($property, $identifier);
    }

    public function getClass(): string
    {
        return $this->class;
    }

    public function getOperator(): ?string
    {
        return $this->operator;
    }

    public function setOperator(?string $operator): self
    {
        $this->operator = $operator;
        return $this;
    }

    /**
     * @return T|null
     */
    public function getValue(): ?AbstractDBElement
    {
        return $this->value;
    }

    /**
     * @param  AbstractDBElement|null $value
     * @phpstan-param T|null $value
     */
    public function setValue(AbstractDBElement|null $value): void
    {
        if (!$value instanceof $this->class) {
            throw new \InvalidArgumentException('The value must be an instance of ' . $this->class);
        }

        $this->value = $value;
    }

    /**
     * Checks whether the constraints apply to a structural type or not
     * @phpstan-assert-if-true AbstractStructuralDBElement $this->value
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
            $tmp = [...$tmp, ...self::ALLOWED_OPERATOR_VALUES_STRUCTURAL];
        }

        return $tmp;
    }

    public function isEnabled(): bool
    {
        return $this->operator !== null && $this->operator !== '';
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
        if (!$this->value instanceof AbstractDBElement) {
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
