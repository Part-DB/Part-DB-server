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

/**
 * @see \App\Tests\Repository\StructuralDBElementRepositoryTest
 * @template TEntityClass of AbstractStructuralDBElement
 * @extends AttachmentContainingDBElementRepository<TEntityClass>
 */
class StructuralDBElementRepository extends AttachmentContainingDBElementRepository
{
    /**
     * @var array An array containing all new entities created by getNewEntityByPath.
     * This is used to prevent creating multiple entities for the same path.
     */
    private array $new_entity_cache = [];

    /**
     * Finds all nodes for the given parent node, ordered by name in a natural sort way
     * @param  AbstractStructuralDBElement|null  $parent
     * @param  string  $nameOrdering  The ordering of the names. Either ASC or DESC
     * @return array
     */
    public function findNodesForParent(?AbstractStructuralDBElement $parent, string $nameOrdering = "ASC"): array
    {
        $qb = $this->createQueryBuilder('e');
        $qb->select('e')
            ->orderBy('NATSORT(e.name)', $nameOrdering);

        if ($parent !== null) {
            $qb->where('e.parent = :parent')
                ->setParameter('parent', $parent);
        } else {
            $qb->where('e.parent IS NULL');
        }
        //@phpstan-ignore-next-line [parent is only defined by the sub classes]
        return $qb->getQuery()->getResult();
    }

    /**
     * Finds all nodes without a parent node. They are our root nodes.
     *
     * @return AbstractStructuralDBElement[]
     */
    public function findRootNodes(): array
    {
        return $this->findNodesForParent(null);
    }

    /**
     * Gets a tree of TreeViewNode elements. The root elements has $parent as parent.
     * The treeview is generic, that means the href are null and ID values are set.
     *
     * @param  AbstractStructuralDBElement|null  $parent  the parent the root elements should have
     * @phpstan-param TEntityClass|null $parent
     *
     * @return TreeViewNode[]
     */
    public function getGenericNodeTree(?AbstractStructuralDBElement $parent = null): array
    {
        $result = [];

        $entities = $this->findNodesForParent($parent);
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
     * @phpstan-param TEntityClass|null $parent
     * @return AbstractStructuralDBElement[] a flattened list containing the tree elements
     * @phpstan-return array<int, TEntityClass>
     */
    public function getFlatList(?AbstractStructuralDBElement $parent = null): array
    {
        $result = [];

        $entities = $this->findNodesForParent($parent);

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
     * @return AbstractStructuralDBElement[]
     * @phpstan-return array<int, TEntityClass>
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
            if (!$entity instanceof AbstractStructuralDBElement) {
                $entity = $this->findOneBy(['name' => $name, 'parent' => $parent]);
            }
            if (null === $entity) {
                $class = $this->getClassName();
                /** @var TEntityClass $entity */
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
        $key = $parent instanceof AbstractStructuralDBElement ? $parent->getFullPath('%->%').'%->%'.$name : $name;
        return $this->new_entity_cache[$key] ?? null;
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
     * @return AbstractStructuralDBElement[]
     * @phpstan-return array<int, TEntityClass>
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

    /**
     * Finds the element with the given name for the use with the InfoProvider System
     * The name search is a bit more fuzzy than the normal findByName, because it is case-insensitive and ignores special characters.
     * Also, it will try to find the element using the additional names field, of the elements.
     * @param  string  $name
     * @return AbstractStructuralDBElement|null
     * @phpstan-return TEntityClass|null
     */
    public function findForInfoProvider(string $name): ?AbstractStructuralDBElement
    {
        //First try to find the element by name
        $qb = $this->createQueryBuilder('e');
        //Use lowercase conversion to be case-insensitive
        $qb->where($qb->expr()->like('LOWER(e.name)', 'LOWER(:name)'));

        $qb->setParameter('name', $name);

        $result = $qb->getQuery()->getResult();

        if (count($result) === 1) {
            return $result[0];
        }

        //If we have no result, try to find the element by alternative names
        $qb = $this->createQueryBuilder('e');
        //Use lowercase conversion to be case-insensitive
        $qb->where($qb->expr()->like('LOWER(e.alternative_names)', 'LOWER(:name)'));
        $qb->setParameter('name', '%'.$name.',%');

        $result = $qb->getQuery()->getResult();

        if (count($result) >= 1) {
            return $result[0];
        }

        //If we find nothing, return null
        return null;
    }

    /**
     * Similar to findForInfoProvider, but will create a new element with the given name if none was found.
     * @param  string  $name
     * @return AbstractStructuralDBElement
     * @phpstan-return TEntityClass
     */
    public function findOrCreateForInfoProvider(string $name): AbstractStructuralDBElement
    {
        $entity = $this->findForInfoProvider($name);
        if (null === $entity) {

            //Try to find if we already have an element cached for this name
            $entity = $this->getNewEntityFromCache($name, null);
            if ($entity !== null) {
                return $entity;
            }

            $class = $this->getClassName();
            /** @var TEntityClass $entity */
            $entity = new $class;
            $entity->setName($name);

            //Set the found name to the alternative names, so the entity can be easily renamed later
            $entity->setAlternativeNames($name);

            $this->setNewEntityToCache($entity);
        }

        return $entity;
    }
}
