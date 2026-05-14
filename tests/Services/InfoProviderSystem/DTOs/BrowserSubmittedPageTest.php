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

namespace App\Tests\Services\InfoProviderSystem\DTOs;

use App\Services\InfoProviderSystem\DTOs\BrowserSubmittedPage;
use PHPUnit\Framework\TestCase;

final class BrowserSubmittedPageTest extends TestCase
{
    public function testTokenIsNonEmpty(): void
    {
        $page = new BrowserSubmittedPage('https://example.com', '<html/>', 'Test');
        $this->assertNotEmpty($page->token);
    }

    public function testTokenIsDeterministic(): void
    {
        $page1 = new BrowserSubmittedPage('https://example.com', '<html/>', 'Title A');
        $page2 = new BrowserSubmittedPage('https://example.com', '<html/>', 'Title B');

        // Token is derived from URL + HTML only, title does not affect it
        $this->assertSame($page1->token, $page2->token);
    }

    public function testDifferentUrlProducesDifferentToken(): void
    {
        $page1 = new BrowserSubmittedPage('https://example.com/1', '<html/>', 'Test');
        $page2 = new BrowserSubmittedPage('https://example.com/2', '<html/>', 'Test');

        $this->assertNotSame($page1->token, $page2->token);
    }

    public function testDifferentHtmlProducesDifferentToken(): void
    {
        $page1 = new BrowserSubmittedPage('https://example.com', '<html>A</html>', 'Test');
        $page2 = new BrowserSubmittedPage('https://example.com', '<html>B</html>', 'Test');

        $this->assertNotSame($page1->token, $page2->token);
    }

    public function testTokenMatchesPageTokenProperty(): void
    {
        $page = new BrowserSubmittedPage('https://example.com', '<html>content</html>', 'Test');
        $expected = hash('xxh3', 'https://example.com|<html>content</html>');

        $this->assertSame($expected, $page->token);
    }

    public function testDefaultSubmittedAtIsNow(): void
    {
        $before = new \DateTimeImmutable();
        $page = new BrowserSubmittedPage('https://example.com', '<html/>', 'Test');
        $after = new \DateTimeImmutable();

        $this->assertGreaterThanOrEqual($before->getTimestamp(), $page->submittedAt->getTimestamp());
        $this->assertLessThanOrEqual($after->getTimestamp(), $page->submittedAt->getTimestamp());
    }

    public function testCustomSubmittedAt(): void
    {
        $dt = new \DateTimeImmutable('2025-01-01 12:00:00');
        $page = new BrowserSubmittedPage('https://example.com', '<html/>', 'Test', $dt);

        $this->assertSame($dt, $page->submittedAt);
    }
}
