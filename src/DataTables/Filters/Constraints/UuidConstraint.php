<?php

declare(strict_types=1);

/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2026 Jan Böhmer (https://github.com/jbtronics)
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
use Symfony\Component\Uid\Uuid;

/**
 * A constraint for exact-match filtering on a property mapped with Doctrine's "uuid" type (see AccessMethod-adjacent
 * request_id/transaction_id columns on AbstractLogEntry). Only equality operators make sense for a UUID identifier,
 * unlike TextConstraint. The parameter is bound with the explicit "uuid" DBAL type so Doctrine converts the submitted
 * string into the column's native representation (e.g. BINARY(16) on MySQL/SQLite) instead of comparing it as plain text.
 */
class UuidConstraint extends AbstractConstraint
{
    final public const ALLOWED_OPERATOR_VALUES = ['=', '!='];

    public function __construct(
        string $property,
        ?string $identifier = null,
        /** @var string|null The value to compare to */
        protected ?string $value = null,
        /** @var string|null The operator to use */
        protected ?string $operator = '',
    ) {
        parent::__construct($property, $identifier);
    }

    public function getValue(): ?string
    {
        return $this->value;
    }

    public function setValue(?string $value): self
    {
        $this->value = $value;
        return $this;
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

    public function isEnabled(): bool
    {
        return $this->value !== null && '' !== $this->value
            && $this->operator !== null && '' !== $this->operator;
    }

    public function apply(QueryBuilder $queryBuilder): void
    {
        if (!$this->isEnabled()) {
            return;
        }

        if (!in_array($this->operator, self::ALLOWED_OPERATOR_VALUES, true)) {
            throw new \RuntimeException('Invalid operator '.$this->operator.' provided. Valid operators are '.implode(', ', self::ALLOWED_OPERATOR_VALUES));
        }

        //If the submitted value is not a syntactically valid UUID, no row can ever match it
        if (!Uuid::isValid($this->value)) {
            $queryBuilder->andWhere('1 = 0');
            return;
        }

        $queryBuilder->andWhere(sprintf('%s %s :%s', $this->property, $this->operator, $this->identifier));
        $queryBuilder->setParameter($this->identifier, $this->value, 'uuid');
    }
}
