<?php

namespace App\Services\Trees;

use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

final class SidebarTreeUpdater
{
    private const CACHE_KEY = 'sidebar_tree_updated';
    private const TTL = 60 * 60 * 24; // 24 hours

    private CacheInterface $cache;

    public function __construct(TagAwareCacheInterface $treeCache)
    {
        $this->cache = $treeCache;
    }

    /**
     * Returns the time when the sidebar tree was updated the last time.
     * The frontend uses this information to reload the sidebar tree.
     * @return \DateTimeInterface
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