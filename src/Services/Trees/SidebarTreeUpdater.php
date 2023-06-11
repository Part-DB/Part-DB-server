<?php
/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2022 Jan Böhmer (https://github.com/jbtronics)
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

namespace App\Services\Trees;

use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

final class SidebarTreeUpdater
{
    private const CACHE_KEY = 'sidebar_tree_updated';
    private const TTL = 60 * 60 * 24;

    public function __construct(
        // 24 hours
        private readonly TagAwareCacheInterface $cache
    )
    {
    }

    /**
     * Returns the time when the sidebar tree was updated the last time.
     * The frontend uses this information to reload the sidebar tree.
     */
    public function getLastTreeUpdate(): \DateTimeInterface
    {
        return $this->cache->get(self::CACHE_KEY, function (ItemInterface $item) {
            $item->expiresAfter(self::TTL);

            //This tag and therfore this whole cache gets cleared by TreeCacheInvalidationListener when a structural element is changed
            $item->tag('sidebar_tree_update');

            return new \DateTime();
        });
    }
}