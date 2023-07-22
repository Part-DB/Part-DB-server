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

namespace App\Services\Trees;

use App\Entity\Attachments\AttachmentContainingDBElement;
use App\Entity\Base\AbstractDBElement;
use App\Entity\Base\AbstractNamedDBElement;
use App\Entity\Base\AbstractStructuralDBElement;
use App\Repository\AttachmentContainingDBElementRepository;
use App\Repository\DBElementRepository;
use App\Repository\StructuralDBElementRepository;
use App\Services\UserSystem\UserCacheKeyGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

/**
 *  This service gives you a flat list containing all structured entities in the order of the structure.
 * @see \App\Tests\Services\Trees\NodesListBuilderTest
 */
class NodesListBuilder
{
    public function __construct(protected EntityManagerInterface $em, protected TagAwareCacheInterface $cache, protected UserCacheKeyGenerator $keyGenerator)
    {
    }

    /**
     * Gets a flattened hierarchical tree. Useful for generating option lists.
     * In difference to the Repository Function, the results here are cached.
     *
     * @template T of AbstractDBElement
     *
     * @param string                           $class_name the class name of the entity you want to retrieve
     * @phpstan-param class-string<T> $class_name
     * @param AbstractStructuralDBElement|null $parent     This entity will be used as root element. Set to null, to use global root
     *
     * @return AbstractDBElement[] a flattened list containing the tree elements
     * @phpstan-return list<T>
     */
    public function typeToNodesList(string $class_name, ?AbstractStructuralDBElement $parent = null): array
    {
        /**
         * We can not cache the entities directly, because loading them from cache will break the doctrine proxies.
         */
        //Retrieve the IDs of the elements
        $ids = $this->getFlattenedIDs($class_name, $parent);

        //Retrieve the elements from the IDs, the order is the same as in the $ids array
        /** @var DBElementRepository $repo */
        $repo = $this->em->getRepository($class_name);

        if ($repo instanceof AttachmentContainingDBElementRepository) {
            return $repo->getElementsAndPreviewAttachmentByIDs($ids);
        }

        return $repo->findByIDInMatchingOrder($ids);
    }

    /**
     * This functions returns the (cached) list of the IDs of the elements for the flattened tree.
     * @param  string  $class_name
     * @param  AbstractStructuralDBElement|null  $parent
     * @return int[]
     */
    private function getFlattenedIDs(string $class_name, ?AbstractStructuralDBElement $parent = null): array
    {
        $parent_id = $parent instanceof AbstractStructuralDBElement ? $parent->getID() : '0';
        // Backslashes are not allowed in cache keys
        $secure_class_name = str_replace('\\', '_', $class_name);
        $key = 'list_'.$this->keyGenerator->generateKey().'_'.$secure_class_name.$parent_id;

        return $this->cache->get($key, function (ItemInterface $item) use ($class_name, $parent, $secure_class_name) {
            // Invalidate when groups, an element with the class or the user changes
            $item->tag(['groups', 'tree_list', $this->keyGenerator->generateKey(), $secure_class_name]);

            /** @var StructuralDBElementRepository $repo */
            $repo = $this->em->getRepository($class_name);

            return array_map(fn(AbstractDBElement $element) => $element->getID(), $repo->getFlatList($parent));
        });
    }

    /**
     *  Returns a flattened list of all (recursive) children elements of the given AbstractStructuralDBElement.
     *  The value is cached for performance reasons.
     *
     * @template T of AbstractStructuralDBElement
     * @param T $element
     * @return AbstractStructuralDBElement[]
     *
     * @phpstan-return list<T>
     */
    public function getChildrenFlatList(AbstractStructuralDBElement $element): array
    {
        return $this->typeToNodesList($element::class, $element);
    }
}
