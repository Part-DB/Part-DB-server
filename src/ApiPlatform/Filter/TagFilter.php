<?php
/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2024 Jan Böhmer (https://github.com/jbtronics)
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

declare(strict_types=1);


namespace App\ApiPlatform\Filter;

use ApiPlatform\Doctrine\Orm\Filter\AbstractFilter;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use App\Entity\Parts\Tag;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\PropertyInfo\Type;

/**
 * Filters parts by a tag: only the parts which have the tag with the given name (compared case-insensitively) are
 * returned. Tags are entities of their own, which are exposed as the comma separated "tags" property of a part.
 */
final class TagFilter extends AbstractFilter
{
    protected function filterProperty(
        string $property,
        $value,
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        ?Operation $operation = null,
        array $context = []
    ): void {
        //The tags property is not a mapped field anymore, so only check that the filter is enabled for it
        if (!$this->isPropertyEnabled($property, $resourceClass) || !is_string($value)) {
            return;
        }

        $rootAlias = $queryBuilder->getRootAliases()[0];
        $tagAlias = $queryNameGenerator->generateJoinAlias('tag');
        $parameter = $queryNameGenerator->generateParameterName($property);

        $queryBuilder->andWhere(sprintf('EXISTS (SELECT %1$s FROM %2$s %1$s WHERE %1$s MEMBER OF %3$s.tagEntities AND LOWER(%1$s.name) = :%4$s)',
            $tagAlias, Tag::class, $rootAlias, $parameter));
        $queryBuilder->setParameter($parameter, Tag::nameKey($value));
    }

    public function getDescription(string $resourceClass): array
    {
        if (!$this->properties) {
            return [];
        }

        $description = [];
        foreach (array_keys($this->properties) as $property) {
            $description[(string)$property] = [
                'property' => $property,
                'type' => Type::BUILTIN_TYPE_STRING,
                'required' => false,
                'description' => 'Filter for tags of a part',
            ];
        }
        return $description;
    }
}