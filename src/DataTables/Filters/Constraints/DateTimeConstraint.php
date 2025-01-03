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
use RuntimeException;

/**
 * Similar to NumberConstraint but for DateTime values
 */
class DateTimeConstraint extends AbstractConstraint
{
    protected const ALLOWED_OPERATOR_VALUES = ['=', '!=', '<', '>', '<=', '>=', 'BETWEEN'];

    public function __construct(
        string $property,
        string $identifier = null,
        /**
         * The value1 used for comparison (this is the main one used for all mono-value comparisons)
         */
        protected \DateTimeInterface|null $value1 = null,
        protected ?string $operator = null,
        /**
         * The second value used when operator is RANGE; this is the upper bound of the range
         */
        protected \DateTimeInterface|null $value2 = null)
    {
        parent::__construct($property, $identifier);
    }

    public function getValue1(): ?\DateTimeInterface
    {
        return $this->value1;
    }

    public function setValue1(\DateTimeInterface|null $value1): void
    {
        $this->value1 = $value1;
    }

    public function getValue2(): ?\DateTimeInterface
    {
        return $this->value2;
    }

    public function setValue2(?\DateTimeInterface $value2): void
    {
        $this->value2 = $value2;
    }

    public function getOperator(): string|null
    {
        return $this->operator;
    }

    /**
     * @param  string  $operator
     */
    public function setOperator(?string $operator): void
    {
        $this->operator = $operator;
    }

    public function isEnabled(): bool
    {
        return $this->value1 !== null
            && ($this->operator !== null && $this->operator !== '');
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

        if ($this->operator !== 'BETWEEN') {
            $this->addSimpleAndConstraint($queryBuilder, $this->property, $this->identifier, $this->operator, $this->value1);
        }  else {
            if ($this->value2 === null) {
                throw new RuntimeException("Cannot use operator BETWEEN without value2!");
            }

            $this->addSimpleAndConstraint($queryBuilder, $this->property, $this->identifier . '1', '>=', $this->value1);
            $this->addSimpleAndConstraint($queryBuilder, $this->property, $this->identifier . '2', '<=', $this->value2);
        }
    }
}
