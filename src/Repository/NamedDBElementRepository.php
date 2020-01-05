<?php

declare(strict_types=1);

/**
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
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
 */

namespace App\Repository;

use App\Entity\Base\NamedDBElement;
use App\Helpers\Trees\TreeViewNode;
use Doctrine\ORM\EntityRepository;

class NamedDBElementRepository extends EntityRepository
{
    /**
     * Gets a tree of TreeViewNode elements. The root elements has $parent as parent.
     * The treeview is generic, that means the href are null and ID values are set.
     *
     * @return TreeViewNode[]
     */
    public function getGenericNodeTree(): array
    {
        $result = [];

        $entities = $this->findBy([], ['name' => 'ASC']);
        foreach ($entities as $entity) {
            /** @var NamedDBElement $entity */
            $node = new TreeViewNode($entity->getName(), null, null);
            $node->setId($entity->getID());
            $result[] = $node;
        }

        return $result;
    }
}
