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
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\PropertyInfo\Type;

/**
 * Due to their nature, tags are stored in a single string, separated by commas, which requires some more complex search logic.
 * This filter allows to easily search for tags in a part entity.
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
        // Ignore filter if property is not enabled or mapped
        if (
            !$this->isPropertyEnabled($property, $resourceClass) ||
            !$this->isPropertyMapped($property, $resourceClass)
        ) {
            return;
        }

        //Escape any %, _ or \ in the tag
        $value = addcslashes($value, '%_\\');

        $tag_identifier_prefix = $queryNameGenerator->generateParameterName($property);

        $expr = $queryBuilder->expr();

        $tmp = $expr->orX(
            'ILIKE(o.'.$property.', :' . $tag_identifier_prefix . '_1) = TRUE',
            'ILIKE(o.'.$property.', :' . $tag_identifier_prefix . '_2) = TRUE',
            'ILIKE(o.'.$property.', :' . $tag_identifier_prefix . '_3) = TRUE',
            'ILIKE(o.'.$property.', :' . $tag_identifier_prefix . '_4) = TRUE',
        );

        $queryBuilder->andWhere($tmp);

        //Set the parameters for the LIKE expression, in each variation of the tag (so with a comma, at the end, at the beginning, and on both ends, and equaling the tag)
        $queryBuilder->setParameter($tag_identifier_prefix . '_1', '%,' . $value . ',%');
        $queryBuilder->setParameter($tag_identifier_prefix . '_2', '%,' . $value);
        $queryBuilder->setParameter($tag_identifier_prefix . '_3', $value . ',%');
        $queryBuilder->setParameter($tag_identifier_prefix . '_4', $value);
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