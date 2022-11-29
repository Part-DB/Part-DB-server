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

namespace App\DataTables\Filters\Constraints\Part;

use App\DataTables\Filters\Constraints\AbstractConstraint;
use Doctrine\ORM\Query\Expr;
use Doctrine\ORM\QueryBuilder;

class TagsConstraint extends AbstractConstraint
{
    public const ALLOWED_OPERATOR_VALUES = ['ANY', 'ALL', 'NONE'];

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

    /**
     * Returns a list of tags based on the comma separated tags list
     * @return string[]
     */
    public function getTags(): array
    {
        return explode(',', trim($this->value, ','));
    }

    /**
     * Builds an expression to query for a single tag
     * @param  QueryBuilder  $queryBuilder
     * @param  string  $tag
     * @return Expr\Orx
     */
    protected function getExpressionForTag(QueryBuilder $queryBuilder, string $tag): Expr\Orx
    {
        $tag_identifier_prefix = uniqid($this->identifier . '_', false);

        $expr = $queryBuilder->expr();

        $tmp = $expr->orX(
            $expr->like($this->property, ':' . $tag_identifier_prefix . '_1'),
            $expr->like($this->property, ':' . $tag_identifier_prefix . '_2'),
            $expr->like($this->property, ':' . $tag_identifier_prefix . '_3'),
            $expr->eq($this->property, ':' . $tag_identifier_prefix . '_4'),
        );

        //Set the parameters for the LIKE expression, in each variation of the tag (so with a comma, at the end, at the beginning, and on both ends, and equaling the tag)
        $queryBuilder->setParameter($tag_identifier_prefix . '_1', '%,' . $tag . ',%');
        $queryBuilder->setParameter($tag_identifier_prefix . '_2', '%,' . $tag);
        $queryBuilder->setParameter($tag_identifier_prefix . '_3', $tag . ',%');
        $queryBuilder->setParameter($tag_identifier_prefix . '_4', $tag);

        return $tmp;
    }

    public function apply(QueryBuilder $queryBuilder): void
    {
        if(!$this->isEnabled()) {
            return;
        }

        if(!in_array($this->operator, self::ALLOWED_OPERATOR_VALUES, true)) {
            throw new \RuntimeException('Invalid operator '. $this->operator . ' provided. Valid operators are '. implode(', ', self::ALLOWED_OPERATOR_VALUES));
        }

        $tagsExpressions = [];
        foreach ($this->getTags() as $tag) {
            $tagsExpressions[] = $this->getExpressionForTag($queryBuilder, $tag);
        }

        if ($this->operator === 'ANY') {
            $queryBuilder->andWhere($queryBuilder->expr()->orX(...$tagsExpressions));
            return;
        }

        if ($this->operator === 'ALL') {
            $queryBuilder->andWhere($queryBuilder->expr()->andX(...$tagsExpressions));
            return;
        }

        if ($this->operator === 'NONE') {
            $queryBuilder->andWhere($queryBuilder->expr()->not($queryBuilder->expr()->orX(...$tagsExpressions)));
            return;
        }
    }
}