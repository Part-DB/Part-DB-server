<?php

declare(strict_types=1);

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
namespace App\Tests\EventListener;

use App\EventListener\NormalizingExternalRedirectListener;
use Nelmio\SecurityBundle\EventListener\ExternalRedirectListener;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class NormalizingExternalRedirectListenerTest extends TestCase
{
    public static function normalizeProvider(): \Iterator
    {
        //Malformed special scheme URLs, which browsers interpret as external hosts
        yield ['https:/evil.example/phish', 'https://evil.example/phish'];
        yield ['https:evil.example', 'https://evil.example'];
        yield ['https:///evil.example', 'https://evil.example'];
        yield ['HTTPS:/evil.example', 'https://evil.example'];
        yield ['http:/evil.example', 'http://evil.example'];
        yield ['ftp:/evil.example', 'ftp://evil.example'];
        yield ['wss:/evil.example', 'wss://evil.example'];

        //Unchanged
        yield ['https://example.com/foo', 'https://example.com/foo'];
        yield ['/en/part/1?_redirect=https:/foo', '/en/part/1?_redirect=https:/foo'];
        yield ['//evil.example', '//evil.example'];
        yield ['mailto:foo@example.com', 'mailto:foo@example.com'];
        yield ['en/part/1', 'en/part/1'];
    }

    #[DataProvider('normalizeProvider')]
    public function testNormalizeTarget(string $input, string $expected): void
    {
        $this->assertSame($expected, NormalizingExternalRedirectListener::normalizeTarget($input));
    }

    private function dispatch(string $target, bool $mainRequest = true): RedirectResponse
    {
        $listener = new NormalizingExternalRedirectListener(new ExternalRedirectListener(abort: true));
        $response = new RedirectResponse('/placeholder');
        $response->headers->set('Location', $target);
        $event = new ResponseEvent(
            $this->createStub(HttpKernelInterface::class),
            Request::create('https://partdb.example/en/part/1'),
            $mainRequest ? HttpKernelInterface::MAIN_REQUEST : HttpKernelInterface::SUB_REQUEST,
            $response
        );
        $listener->onKernelResponse($event);

        return $response;
    }

    public static function bypassProvider(): \Iterator
    {
        yield ['https:/evil.example/phish'];
        yield ['https:///evil.example/phish'];
        yield ['http:evil.example'];
        yield ['//evil.example/'];
        yield ['https://evil.example/'];
    }

    #[DataProvider('bypassProvider')]
    public function testExternalRedirectsAreBlocked(string $target): void
    {
        $this->expectException(HttpException::class);
        $this->dispatch($target);
    }

    public function testLocalRedirectsArePassed(): void
    {
        $this->assertSame('/en/part/2', $this->dispatch('/en/part/2')->headers->get('Location'));
        $this->assertSame('https://partdb.example/en/part/2', $this->dispatch('https://partdb.example/en/part/2')->headers->get('Location'));
        //Same host, malformed: normalized, but still allowed
        $this->assertSame('https://partdb.example/en/', $this->dispatch('https:/partdb.example/en/')->headers->get('Location'));
    }

    public function testSubRequestsAreIgnored(): void
    {
        $this->assertSame('https:/evil.example', $this->dispatch('https:/evil.example', false)->headers->get('Location'));
    }
}
