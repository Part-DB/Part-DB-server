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
namespace App\DataTables\Filters\Constraints\Part;

use App\DataTables\Filters\Constraints\AbstractConstraint;
use App\Entity\Parts\Tag;
use Doctrine\ORM\QueryBuilder;

class TagsConstraint extends AbstractConstraint
{
    final public const ALLOWED_OPERATOR_VALUES = ['ANY', 'ALL', 'NONE'];

    public function __construct(string $property, ?string $identifier = null,
        protected ?string $value = null,
        protected ?string $operator = '')
    {
        parent::__construct($property, $identifier);
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

    public function getValue(): ?string
    {
        return $this->value;
    }

    public function setValue(?string $value): self
    {
        $this->value = $value;
        return $this;
    }

    public function isEnabled(): bool
    {
        return $this->value !== null
            && ($this->operator !== null && $this->operator !== '');
    }

    /**
     * Returns a list of tags based on the comma separated tags list
     * @return string[]
     */
    public function getTags(): array
    {
        return Tag::splitNames($this->value ?? '');
    }

    /**
     * Builds an expression, which is true if the tag with the given name is assigned to the element.
     * The property of this constraint has to be the tag collection of the element (e.g. "part.tagEntities").
     */
    protected function getExpressionForTag(QueryBuilder $queryBuilder, string $tag): string
    {
        //The identifier serves as the alias of the tag in the subquery and as the name of its parameter
        $identifier = $this->generateParameterIdentifier('tag');
        $queryBuilder->setParameter($identifier, Tag::nameKey($tag));

        return sprintf('EXISTS (SELECT %1$s FROM %2$s %1$s WHERE %1$s MEMBER OF %3$s AND LOWER(%1$s.name) = :%1$s)',
            $identifier, Tag::class, $this->property);
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

        //A value without any tag name (e.g. only commas) does not restrict anything
        if ($tagsExpressions === []) {
            return;
        }

        if ($this->operator === 'ANY') {
            $queryBuilder->andWhere($queryBuilder->expr()->orX(...$tagsExpressions));
            return;
        }

        if ($this->operator === 'ALL') {
            $queryBuilder->andWhere($queryBuilder->expr()->andX(...$tagsExpressions));
            return;
        }

        //@phpstan-ignore-next-line Keep this check to ensure that everything has the same structure even if we add a new operator
        if ($this->operator === 'NONE') {
            $queryBuilder->andWhere($queryBuilder->expr()->not($queryBuilder->expr()->orX(...$tagsExpressions)));
            return;
        }
    }
}
