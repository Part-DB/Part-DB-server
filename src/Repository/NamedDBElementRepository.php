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

use App\Entity\Base\AbstractNamedDBElement;
use App\Entity\UserSystem\User;
use App\Helpers\Trees\TreeViewNode;

/**
 * @template TEntityClass of AbstractNamedDBElement
 * @extends DBElementRepository<TEntityClass>
 */
class NamedDBElementRepository extends DBElementRepository
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
            /** @var AbstractNamedDBElement $entity */
            $node = new TreeViewNode($entity->getName(), null, null);
            $node->setId($entity->getID());
            $result[] = $node;

            if ($entity instanceof User) {
                if ($entity->isDisabled()) {
                    //If this is a user, then add a badge when it is disabled
                    $node->setIcon('fa-fw fa-treeview fa-solid fa-user-lock text-muted');
                }
                if ($entity->isSamlUser()) {
                    $node->setIcon('fa-fw fa-treeview fa-solid fa-house-user text-muted');
                }
            }

        }

        return $result;
    }

    /**
     * Returns the list of all nodes to use in a select box.
     * @return AbstractNamedDBElement[]
     * @phpstan-return array<int, AbstractNamedDBElement>
     */
    public function toNodesList(): array
    {
        //All nodes are sorted by name
        return $this->findBy([], ['name' => 'ASC']);
    }
}
