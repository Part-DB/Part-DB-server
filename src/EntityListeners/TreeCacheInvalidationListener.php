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

namespace App\EntityListeners;

use App\Entity\Base\AbstractDBElement;
use App\Entity\Base\AbstractStructuralDBElement;
use App\Entity\UserSystem\Group;
use App\Entity\UserSystem\User;
use App\Services\Cache\ElementCacheTagGenerator;
use App\Services\Cache\UserCacheKeyGenerator;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostRemoveEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

class TreeCacheInvalidationListener
{
    public function __construct(
        protected TagAwareCacheInterface $cache,
        protected UserCacheKeyGenerator $keyGenerator,
        protected ElementCacheTagGenerator $tagGenerator
    )
    {
    }

    #[ORM\PostUpdate]
    #[ORM\PostPersist]
    #[ORM\PostRemove]
    public function invalidate(AbstractDBElement $element, PostUpdateEventArgs|PostPersistEventArgs|PostRemoveEventArgs $event): void
    {
        //For all changes, we invalidate the cache for all elements of this class
        $tags = [$this->tagGenerator->getElementTypeCacheTag($element)];


        //For changes on structural elements, we also invalidate the sidebar tree
        if ($element instanceof AbstractStructuralDBElement) {
            $tags[] = 'sidebar_tree_update';
        }

        //For user changes, we invalidate the cache for this user
        if ($element instanceof User) {
            $tags[] = $this->keyGenerator->generateKey($element);
        }

        /* If any group change, then invalidate all cached trees. Users Permissions can be inherited from groups,
            so a change in any group can cause big permisssion changes for users. So to be sure, invalidate all trees */
        if ($element instanceof Group) {
            $tags[] = 'groups';
        }

        //Invalidate the cache for the given tags
        $this->cache->invalidateTags($tags);
    }
}
