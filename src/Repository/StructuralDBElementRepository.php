<?php
/**
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony)
 *
 * Copyright (C) 2019 Jan Böhmer (https://github.com/jbtronics)
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA
 *
 */

namespace App\Repository;


use App\Entity\Base\StructuralDBElement;
use Doctrine\ORM\EntityRepository;

class StructuralDBElementRepository extends EntityRepository
{

    /**
     * Finds all nodes without a parent node. They are our root nodes.
     *
     * @return StructuralDBElement[]
     */
    public function findRootNodes() : array
    {
        return $this->findBy(['parent' => null], ['name' => 'ASC']);
    }

    /**
     * Gets a flattened hierachical tree. Useful for generating option lists.
     * @param StructuralDBElement|null $parent This entity will be used as root element. Set to null, to use global root
     * @return StructuralDBElement[] A flattened list containing the tree elements.
     */
    public function toNodesList(?StructuralDBElement $parent = null): array
    {
        $result = array();

        $entities = $this->findBy(['parent' => $parent], ['name' => 'ASC']);

        /**
         * I think it is very difficult to replace this recursive array_merge,
         * so if you want to change it you should have a better idea than adding each list to $result array
         * and do an array_merge(...$result) at the end.
         */

        foreach ($entities as $entity) {
            /** @var StructuralDBElement $entity */
            $result[] = $entity;
            $result = array_merge($result, $this->toNodesList($entity));
        }

        return $result;
    }

}