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

namespace App\EntityListeners;

use App\Entity\Base\DBElement;
use App\Entity\Base\StructuralDBElement;
use App\Entity\UserSystem\Group;
use App\Entity\UserSystem\User;
use App\Services\UserCacheKeyGenerator;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Mapping as ORM;
use function get_class;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

class TreeCacheInvalidationListener
{
    protected $cache;
    protected $keyGenerator;

    public function __construct(TagAwareCacheInterface $treeCache, UserCacheKeyGenerator $keyGenerator)
    {
        $this->cache = $treeCache;
        $this->keyGenerator = $keyGenerator;
    }

    /**
     * @ORM\PostUpdate()
     * @ORM\PostPersist()
     * @ORM\PostRemove()
     */
    public function invalidate(DBElement $element, LifecycleEventArgs $event): void
    {
        //If an element was changed, then invalidate all cached trees with this element class
        if ($element instanceof StructuralDBElement) {
            $secure_class_name = str_replace('\\', '_', get_class($element));
            $this->cache->invalidateTags([$secure_class_name]);
        }

        //If a user change, then invalidate all cached trees for him
        if ($element instanceof User) {
            $secure_class_name = str_replace('\\', '_', get_class($element));
            $tag = $this->keyGenerator->generateKey($element);
            $this->cache->invalidateTags([$tag, $secure_class_name]);
        }

        /* If any group change, then invalidate all cached trees. Users Permissions can be inherited from groups,
            so a change in any group can cause big permisssion changes for users. So to be sure, invalidate all trees */
        if ($element instanceof Group) {
            $tag = 'groups';
            $this->cache->invalidateTags([$tag]);
        }
    }
}
