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

class ChoiceConstraint extends AbstractConstraint
{
    final public const ALLOWED_OPERATOR_VALUES = ['ANY', 'NONE'];

    /**
     * @var string[]|int[] The values to compare to
     */
    protected array $value = [];

    /**
     * @var string The operator to use
     */
    protected string $operator = "";

    /**
     * @return string[]|int[]
     */
    public function getValue(): array
    {
        return $this->value;
    }

    /**
     * @param  string[]|int[]  $value
     */
    public function setValue(array $value): ChoiceConstraint
    {
        $this->value = $value;
        return $this;
    }

    public function getOperator(): string
    {
        return $this->operator;
    }

    public function setOperator(string $operator): ChoiceConstraint
    {
        $this->operator = $operator;
        return $this;
    }



    public function isEnabled(): bool
    {
        return $this->operator !== '' && count($this->value) > 0;
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
