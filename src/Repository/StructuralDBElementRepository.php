<?php
/**
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 * Copyright (C) 2019 - 2022 Jan Böhmer (https://github.com/jbtronics)
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Base\AbstractStructuralDBElement;
use App\Helpers\Trees\StructuralDBElementIterator;
use App\Helpers\Trees\TreeViewNode;
use RecursiveIteratorIterator;

class StructuralDBElementRepository extends NamedDBElementRepository
{
    /**
     * @var array An array containing all new entities created by getNewEntityByPath.
     * This is used to prevent creating multiple entities for the same path.
     */
    private array $new_entity_cache = [];

    /**
     * Finds all nodes without a parent node. They are our root nodes.
     *
     * @return AbstractStructuralDBElement[]
     */
    public function findRootNodes(): array
    {
        return $this->findBy(['parent' => null], ['name' => 'ASC']);
    }

    /**
     * Gets a tree of TreeViewNode elements. The root elements has $parent as parent.
     * The treeview is generic, that means the href are null and ID values are set.
     *
     * @param AbstractStructuralDBElement|null $parent the parent the root elements should have
     *
     * @return TreeViewNode[]
     */
    public function getGenericNodeTree(?AbstractStructuralDBElement $parent = null): array
    {
        $result = [];

        $entities = $this->findBy(['parent' => $parent], ['name' => 'ASC']);
        foreach ($entities as $entity) {
            /** @var AbstractStructuralDBElement $entity */
            //Make a recursive call to find all children nodes
            $children = $this->getGenericNodeTree($entity);
            $node = new TreeViewNode($entity->getName(), null, $children);
            //Set the ID of this entity to later be able to reconstruct the URL
            $node->setId($entity->getID());
            $result[] = $node;
        }

        return $result;
    }

    /**
     * Gets a flattened hierarchical tree. Useful for generating option lists.
     *
     * @param AbstractStructuralDBElement|null $parent This entity will be used as root element. Set to null, to use global root
     *
     * @return AbstractStructuralDBElement[] a flattened list containing the tree elements
     */
    public function toNodesList(?AbstractStructuralDBElement $parent = null): array
    {
        $result = [];

        $entities = $this->findBy(['parent' => $parent], ['name' => 'ASC']);

        $elementIterator = new StructuralDBElementIterator($entities);
        $recursiveIterator = new RecursiveIteratorIterator($elementIterator, RecursiveIteratorIterator::SELF_FIRST);
        //$result = iterator_to_array($recursiveIterator);

        //We can not use iterator_to_array here, or we get only the parent elements
        foreach ($recursiveIterator as $item) {
            $result[] = $item;
        }

        return $result;
    }

    /**
     * Creates a structure of AbstractStructuralDBElements from a path separated by $separator, which splits the various levels.
     * This function will try to use existing elements, if they are already in the database. If not, they will be created.
     * An array of the created elements will be returned, with the last element being the deepest element.
     * @param  string  $path
     * @param  string  $separator
     * @return AbstractStructuralDBElement[]
     */
    public function getNewEntityFromPath(string $path, string $separator = '->'): array
    {
        $parent = null;
        $result = [];
        foreach (explode($separator, $path) as $name) {
            $name = trim($name);
            if ('' === $name) {
                continue;
            }

            //Use the cache to prevent creating multiple entities for the same path
            $entity = $this->getNewEntityFromCache($name, $parent);

            //See if we already have an element with this name and parent in the database
            if (!$entity) {
                $entity = $this->findOneBy(['name' => $name, 'parent' => $parent]);
            }
            if (null === $entity) {
                $class = $this->getClassName();
                /** @var AbstractStructuralDBElement $entity */
                $entity = new $class;
                $entity->setName($name);
                $entity->setParent($parent);

                $this->setNewEntityToCache($entity);
            }

            $result[] = $entity;
            $parent = $entity;
        }

        return $result;
    }

    private function getNewEntityFromCache(string $name, ?AbstractStructuralDBElement $parent): ?AbstractStructuralDBElement
    {
        $key = $parent ? $parent->getFullPath('%->%').'%->%'.$name : $name;
        if (isset($this->new_entity_cache[$key])) {
            return $this->new_entity_cache[$key];
        }
        return null;
    }

    private function setNewEntityToCache(AbstractStructuralDBElement $entity): void
    {
        $key = $entity->getFullPath('%->%');
        $this->new_entity_cache[$key] = $entity;
    }

    /**
     * Returns an element of AbstractStructuralDBElements queried from a path separated by $separator, which splits the various levels.
     * An array of the created elements will be returned, with the last element being the deepest element.
     * If no element was found, an empty array will be returned.
     * @param  string  $path
     * @param  string  $separator
     * @return AbstractStructuralDBElement[]
     */
    public function getEntityByPath(string $path, string $separator = '->'): array
    {
        $parent = null;
        $result = [];
        foreach (explode($separator, $path) as $name) {
            $name = trim($name);
            if ('' === $name) {
                continue;
            }

            //See if we already have an element with this name and parent
            $entity = $this->findOneBy(['name' => $name, 'parent' => $parent]);
            if (null === $entity) {
                return [];
            }

            $result[] = $entity;
            $parent = $entity;
        }

        return $result;
    }
}
