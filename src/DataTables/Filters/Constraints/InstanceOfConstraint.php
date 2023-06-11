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

/**
 * This constraint allows to filter by a given list of classes, that the given property should be an instance of
 */
class InstanceOfConstraint extends AbstractConstraint
{
    final public const ALLOWED_OPERATOR_VALUES = ['ANY', 'NONE'];

    /**
     * @var string[] The values to compare to (fully qualified class names)
     */
    protected array $value = [];

    /**
     * @var string The operator to use
     */
    protected string $operator = "";

    /**
     * @return string[]
     */
    public function getValue(): array
    {
        return $this->value;
    }

    /**
     * @param  string[]  $value
     * @return $this
     */
    public function setValue(array $value): self
    {
        $this->value = $value;
        return $this;
    }

    public function getOperator(): string
    {
        return $this->operator;
    }

    /**
     * @return $this
     */
    public function setOperator(string $operator): self
    {
        $this->operator = $operator;
        return $this;
    }



    public function isEnabled(): bool
    {
        return $this->operator !== '';
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

        $expressions = [];

        if ($this->operator === 'ANY' || $this->operator === 'NONE') {
            foreach($this->value as $value) {
                //We can not use a parameter here, as this is the only way to pass the FCQN to the query (via binded params, we would need to use ClassMetaData). See: https://github.com/doctrine/orm/issues/4462
                $expressions[] = ($queryBuilder->expr()->isInstanceOf($this->property, $value));
            }

            if($this->operator === 'ANY') {
                $queryBuilder->andWhere($queryBuilder->expr()->orX(...$expressions));
            } else { //NONE
                $queryBuilder->andWhere($queryBuilder->expr()->not($queryBuilder->expr()->orX(...$expressions)));
            }
        } else {
            throw new \RuntimeException('Unknown operator '. $this->operator . ' provided. Valid operators are '. implode(', ', self::ALLOWED_OPERATOR_VALUES));
        }
    }
}
