<?php
/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2023 Jan Böhmer (https://github.com/jbtronics)
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
use App\Entity\Base\AbstractStructuralDBElement;
use App\Services\Trees\NodesListBuilder;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;
use Symfony\Component\PropertyInfo\Type;
use Symfony\Component\Serializer\NameConverter\NameConverterInterface;

class EntityFilter extends AbstractFilter
{

    public function __construct(
        ManagerRegistry $managerRegistry,
        private NodesListBuilder $nodesListBuilder,
        private EntityManagerInterface $entityManager,
        LoggerInterface $logger = null,
        ?array $properties = null,
        ?NameConverterInterface $nameConverter = null
    ) {
        parent::__construct($managerRegistry, $logger, $properties, $nameConverter);
    }

    protected function filterProperty(
        string $property,
        $value,
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        Operation $operation = null,
        array $context = []
    ): void {
        if (
            !$this->isPropertyEnabled($property, $resourceClass) ||
            !$this->isPropertyMapped($property, $resourceClass, true)
        ) {
            return;
        }

        $metadata = $this->getClassMetadata($resourceClass);
        $target_class = $metadata->getAssociationTargetClass($property);
        //If it is not an association we can not filter the property
        if (!$target_class) {
            return;
        }

        $elements = $this->valueToEntityArray($value, $target_class);

        $parameterName = $queryNameGenerator->generateParameterName($property); // Generate a unique parameter name to avoid collisions with other filters
        $queryBuilder
            ->andWhere(sprintf('o.%s IN (:%s)', $property, $parameterName))
            ->setParameter($parameterName, $elements);
    }

    private function valueToEntityArray(string $value, string $target_class): array
    {
        //Convert value to IDs:
        $elements = [];

        //Split the given value by comm
        foreach (explode(',', $value) as $id) {
            if (trim($id) === '') {
                continue;
            }

            //Check if the given value ends with a plus, then we want to include all direct children
            $include_children = false;
            $include_recursive = false;
            if (str_ends_with($id, '++')) { //Plus Plus means include all children recursively
                $id = substr($id, 0, -2);
                $include_recursive = true;
            } elseif (str_ends_with($id, '+')) {
                $id = substr($id, 0, -1);
                $include_children = true;
            }

            //Get a (shallow) reference to the entitity
            $element = $this->entityManager->getReference($target_class, (int) $id);
            $elements[] = $element;

            //If $element is not structural we are done
            if (!is_a($element, AbstractStructuralDBElement::class)) {
                continue;
            }

            //Get the recursive list of children
            if ($include_recursive) {
                $elements = array_merge($elements, $this->nodesListBuilder->getChildrenFlatList($element));
            } elseif ($include_children) {
                $elements = array_merge($elements, $element->getChildren()->toArray());
            }
        }

        return $elements;
    }

    public function getDescription(string $resourceClass): array
    {
        if (!$this->properties) {
            return [];
        }

        $description = [];
        foreach ($this->properties as $property => $strategy) {
            $description["$property"] = [
                'property' => $property,
                'type' => Type::BUILTIN_TYPE_STRING,
                'required' => false,
                'description' => 'Filter using a comma seperated list of element IDs. Use + to include all direct children and ++ to include all children recursively.',
                'openapi' => [
                    'example' => '',
                    'allowReserved' => false,// if true, query parameters will be not percent-encoded
                    'allowEmptyValue' => true,
                    'explode' => false, // to be true, the type must be Type::BUILTIN_TYPE_ARRAY, ?product=blue,green will be ?product=blue&product=green
                ],
            ];
        }
        return $description;
    }
}