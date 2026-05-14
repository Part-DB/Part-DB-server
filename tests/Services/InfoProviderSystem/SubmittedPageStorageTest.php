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

namespace App\Tests\Services\InfoProviderSystem;

use App\Services\InfoProviderSystem\DTOs\BrowserSubmittedPage;
use App\Services\InfoProviderSystem\SubmittedPageStorage;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

final class SubmittedPageStorageTest extends TestCase
{
    private SubmittedPageStorage $storage;
    private Session $session;

    protected function setUp(): void
    {
        $this->session = new Session(new MockArraySessionStorage());
        $request = new Request();
        $request->setSession($this->session);
        $requestStack = new RequestStack();
        $requestStack->push($request);

        $this->storage = new SubmittedPageStorage($requestStack, new ArrayAdapter());
    }

    public function testStoreReturnsToken(): void
    {
        $page = new BrowserSubmittedPage('https://example.com', '<html/>', 'Test');
        $token = $this->storage->store($page);

        $this->assertSame($page->token, $token);
    }

    public function testStoreAndRetrieve(): void
    {
        $page = new BrowserSubmittedPage('https://example.com', '<html>content</html>', 'Test Page');
        $token = $this->storage->store($page);

        $retrieved = $this->storage->retrieve($token);

        $this->assertNotNull($retrieved);
        $this->assertSame($page->url, $retrieved->url);
        $this->assertSame($page->html, $retrieved->html);
        $this->assertSame($page->title, $retrieved->title);
        $this->assertSame($page->token, $retrieved->token);
    }

    public function testRetrieveReturnsNullForUnknownToken(): void
    {
        $this->assertNull($this->storage->retrieve('nonexistent_token_xyz'));
    }

    public function testStoreReturnsSameTokenForSameUrlAndHtml(): void
    {
        $page1 = new BrowserSubmittedPage('https://example.com', '<html/>', 'Title One');
        $page2 = new BrowserSubmittedPage('https://example.com', '<html/>', 'Title Two');

        $this->assertSame($this->storage->store($page1), $this->storage->store($page2));
    }

    public function testRemoveByTokenDeletesFromCache(): void
    {
        $page = new BrowserSubmittedPage('https://example.com', '<html/>', 'Test');
        $token = $this->storage->store($page);

        $this->storage->remove($token);

        $this->assertNull($this->storage->retrieve($token));
    }

    public function testRemoveByPageObjectDeletesFromCache(): void
    {
        $page = new BrowserSubmittedPage('https://example.com', '<html/>', 'Test');
        $this->storage->store($page);

        $this->storage->remove($page);

        $this->assertNull($this->storage->retrieve($page->token));
    }

    public function testRemoveDeletesFromSession(): void
    {
        $page = new BrowserSubmittedPage('https://example.com', '<html/>', 'Test');
        $this->storage->store($page);

        $this->storage->remove($page);

        $this->assertEmpty($this->storage->getRecentPages());
    }

    public function testGetRecentPagesReturnsStoredPages(): void
    {
        $page1 = new BrowserSubmittedPage('https://example.com/1', '<html>1</html>', 'Page 1');
        $page2 = new BrowserSubmittedPage('https://example.com/2', '<html>2</html>', 'Page 2');
        $this->storage->store($page1);
        $this->storage->store($page2);

        $recent = $this->storage->getRecentPages();

        $this->assertCount(2, $recent);
    }

    public function testGetRecentPagesReturnsNewestFirst(): void
    {
        $page1 = new BrowserSubmittedPage('https://example.com/1', '<html>1</html>', 'Page 1');
        $page2 = new BrowserSubmittedPage('https://example.com/2', '<html>2</html>', 'Page 2');
        $this->storage->store($page1);
        $this->storage->store($page2);

        $recent = $this->storage->getRecentPages();

        $this->assertSame($page2->url, $recent[0]->url);
        $this->assertSame($page1->url, $recent[1]->url);
    }

    public function testStoreDeduplicatesSamePageInSession(): void
    {
        $page = new BrowserSubmittedPage('https://example.com', '<html/>', 'Test');
        $this->storage->store($page);
        $this->storage->store($page);

        $this->assertCount(1, $this->storage->getRecentPages());
    }

    public function testStoreMovesResubmittedPageToTop(): void
    {
        $page1 = new BrowserSubmittedPage('https://example.com/1', '<html>1</html>', 'Page 1');
        $page2 = new BrowserSubmittedPage('https://example.com/2', '<html>2</html>', 'Page 2');
        $this->storage->store($page1);
        $this->storage->store($page2);
        // Resubmit page1 — it should move back to the top
        $this->storage->store($page1);

        $recent = $this->storage->getRecentPages();

        $this->assertSame($page1->url, $recent[0]->url);
        $this->assertSame($page2->url, $recent[1]->url);
    }

    public function testGetRecentPagesSilentlyOmitsExpiredEntries(): void
    {
        // Put a token in the session that has no corresponding cache entry (simulates expiry)
        $this->session->set('browser_plugin_recent_urls', ['expired_token_xyz']);

        $this->assertEmpty($this->storage->getRecentPages());
    }

    public function testSessionCappedAtTenEntries(): void
    {
        for ($i = 0; $i < 12; $i++) {
            $page = new BrowserSubmittedPage("https://example.com/{$i}", "<html>{$i}</html>", "Page {$i}");
            $this->storage->store($page);
        }

        $this->assertCount(10, $this->storage->getRecentPages());
    }
}
