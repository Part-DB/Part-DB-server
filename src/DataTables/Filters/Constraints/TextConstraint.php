<?php
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

class TextConstraint extends AbstractConstraint
{

    public const ALLOWED_OPERATOR_VALUES = ['=', '!=', 'STARTS', 'ENDS', 'CONTAINS', 'LIKE', 'REGEX'];

    /**
     * @var string|null The operator to use
     */
    protected ?string $operator;

    /**
     * @var string The value to compare to
     */
    protected $value;

    public function __construct(string $property, string $identifier = null, $value = null, string $operator = '')
    {
        parent::__construct($property, $identifier);
        $this->value = $value;
        $this->operator = $operator;
    }

    /**
     * @return string
     */
    public function getOperator(): ?string
    {
        return $this->operator;
    }

    /**
     * @param  string  $operator
     */
    public function setOperator(?string $operator): self
    {
        $this->operator = $operator;
        return $this;
    }

    /**
     * @return string
     */
    public function getValue(): string
    {
        return $this->value;
    }

    /**
     * @param  string  $value
     */
    public function setValue(string $value): self
    {
        $this->value = $value;
        return $this;
    }

    public function isEnabled(): bool
    {
        return $this->value !== null
            && !empty($this->operator);
    }

    public function apply(QueryBuilder $queryBuilder): void
    {
        if(!$this->isEnabled()) {
            return;
        }

        if(!in_array($this->operator, self::ALLOWED_OPERATOR_VALUES, true)) {
            throw new \RuntimeException('Invalid operator '. $this->operator . ' provided. Valid operators are '. implode(', ', self::ALLOWED_OPERATOR_VALUES));
        }

        //Equal and not equal can be handled easily
        if($this->operator === '=' || $this->operator === '!=') {

            $this->addSimpleAndConstraint($queryBuilder, $this->property, $this->identifier, $this->operator, $this->value);
            return;
        }

        //The CONTAINS, LIKE, STARTS and ENDS operators use the LIKE operator, but we have to build the value string differently
        $like_value = null;
        if ($this->operator === 'LIKE') {
            $like_value = $this->value;
        } else if ($this->operator === 'STARTS') {
            $like_value = $this->value . '%';
        } else if ($this->operator === 'ENDS') {
            $like_value = '%' . $this->value;
        } else if ($this->operator === 'CONTAINS') {
            $like_value = '%' . $this->value . '%';
        }

        if ($like_value !== null) {
            $this->addSimpleAndConstraint($queryBuilder, $this->property, $this->identifier, 'LIKE', $like_value);
            return;
        }

        //Regex is only supported on MySQL and needs a special function
        if ($this->operator === 'REGEX') {
            $queryBuilder->andWhere(sprintf('REGEXP(%s, :%s) = 1', $this->property, $this->identifier));
            $queryBuilder->setParameter($this->identifier, $this->value);
        }
    }
}