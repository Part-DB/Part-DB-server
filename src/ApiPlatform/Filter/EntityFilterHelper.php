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

use App\Entity\Base\AbstractStructuralDBElement;
use App\Services\Trees\NodesListBuilder;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PropertyInfo\Type;

class EntityFilterHelper
{
    public function __construct(
        private readonly NodesListBuilder $nodesListBuilder,
        private readonly EntityManagerInterface $entityManager)
    {

    }

    public function valueToEntityArray(string $value, string $target_class): array
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

    public function getDescription(array $properties): array
    {
        if ($properties === []) {
            return [];
        }

        $description = [];
        foreach (array_keys($properties) as $property) {
            $description[(string)$property] = [
                'property' => $property,
                'type' => Type::BUILTIN_TYPE_STRING,
                'required' => false,
                'description' => 'Filter using a comma seperated list of element IDs. Use + to include all direct children and ++ to include all children recursively.',
            ];
        }
        return $description;
    }
}