<?php
/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2026 Jan Böhmer (https://github.com/jbtronics)
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

declare(strict_types=1);

namespace App\Services\InfoProviderSystem;

use App\Services\InfoProviderSystem\DTOs\BrowserSubmittedPage;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Stores browser-submitted pages for the browser extension feature.
 *
 * Each page is stored as a {@see BrowserSubmittedPage} DTO in the application cache with a short TTL.
 * The session holds only a compact list of recently submitted URLs so that pages can be listed
 * without bloating the session with HTML content.
 */
class SubmittedPageStorage
{
    private const CACHE_KEY_PREFIX = 'browser_plugin_html_';
    private const CACHE_TTL = 1800; // 30 minutes
    private const SESSION_KEY = 'browser_plugin_recent_urls';
    private const MAX_RECENT = 10;

    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly CacheItemPoolInterface $cache,
    ) {
    }

    /**
     * Stores a submitted page in the cache and records its URL in the session's recent list.
     * @return string The token under which the page was stored, derived from the URL and HTML. This token is used to retrieve the page later. It is the same value as $page->token.
     */
    public function store(BrowserSubmittedPage $page): string
    {
        $item = $this->cache->getItem($this->cacheKey($page));
        $item->set($page);
        $item->expiresAfter(self::CACHE_TTL);
        $this->cache->save($item);

        $session = $this->requestStack->getSession();
        $tokens = array_values(array_filter(
            $session->get(self::SESSION_KEY, []),
            static fn(string $u): bool => $u !== $page->token,
        ));
        array_unshift($tokens, $page->url);
        $session->set(self::SESSION_KEY, array_slice($tokens, 0, self::MAX_RECENT));

        return $page->token;
    }

    /**
     * Retrieves the stored page via its token (which is derived from the URL and HTML). Returns null if not found or expired.
     */
    public function retrieve(string $token): ?BrowserSubmittedPage
    {
        $item = $this->cache->getItem($this->cacheKey($token));
        if (!$item->isHit()) {
            return null;
        }
        return $item->get();
    }

    /**
     * Returns the list of recently submitted pages, newest first.
     * Pages whose cache entry has expired are silently omitted.
     * The list depends on the session and thus is per-browser and per-user.
     *
     * @return BrowserSubmittedPage[]
     */
    public function getRecentPages(): array
    {
        $tokens = $this->requestStack->getSession()->get(self::SESSION_KEY, []);
        $pages = [];
        foreach ($tokens as $token) {
            $page = $this->retrieve($token);
            if ($page !== null) {
                $pages[] = $page;
            }
        }
        return $pages;
    }

    /**
     * Removes a page from both the cache and the recent list.
     * @param BrowserSubmittedPage|string $page The page or its token to remove.
     */
    public function remove(BrowserSubmittedPage|string $page): void
    {
        $this->cache->deleteItem($this->cacheKey($page));

        $token = is_string($page) ? $page : $page->token;

        $session = $this->requestStack->getSession();
        //Remove the token from the recent list in the session:
        $tokens = array_values(array_filter(
            $session->get(self::SESSION_KEY, []),
            static fn(string $u): bool => $u !== $token
        ));
        $session->set(self::SESSION_KEY, $tokens);
    }

    private function cacheKey(BrowserSubmittedPage|string $token): string
    {
        if (!is_string($token)) {
            $token = $token->token;
        }

        return self::CACHE_KEY_PREFIX . $token;
    }
}
