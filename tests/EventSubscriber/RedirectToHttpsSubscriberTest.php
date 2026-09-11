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

namespace App\Tests\EventSubscriber;

use App\EventSubscriber\RedirectToHttpsSubscriber;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Http\HttpUtils;

final class RedirectToHttpsSubscriberTest extends TestCase
{
    private function makeEvent(string $url, bool $isMainRequest = true): RequestEvent
    {
        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = Request::create($url);
        return new RequestEvent($kernel, $request, $isMainRequest ? HttpKernelInterface::MAIN_REQUEST : HttpKernelInterface::SUB_REQUEST);
    }

    public function testHttpRequestIsRedirectedToHttpsWhenEnabled(): void
    {
        $subscriber = new RedirectToHttpsSubscriber(true, new HttpUtils());
        $event = $this->makeEvent('http://example.com/some/path');

        $subscriber->onKernelRequest($event);

        $this->assertTrue($event->hasResponse());
        $response = $event->getResponse();
        $this->assertStringStartsWith('https://', $response->getTargetUrl());
    }

    public function testHttpsRequestIsNotRedirectedWhenEnabled(): void
    {
        $subscriber = new RedirectToHttpsSubscriber(true, new HttpUtils());
        $event = $this->makeEvent('https://example.com/some/path');

        $subscriber->onKernelRequest($event);

        $this->assertFalse($event->hasResponse());
    }

    public function testHttpRequestIsNotRedirectedWhenDisabled(): void
    {
        $subscriber = new RedirectToHttpsSubscriber(false, new HttpUtils());
        $event = $this->makeEvent('http://example.com/some/path');

        $subscriber->onKernelRequest($event);

        $this->assertFalse($event->hasResponse());
    }

    public function testSubRequestIsNotRedirected(): void
    {
        $subscriber = new RedirectToHttpsSubscriber(true, new HttpUtils());
        $event = $this->makeEvent('http://example.com/', false);

        $subscriber->onKernelRequest($event);

        $this->assertFalse($event->hasResponse());
    }

    public function testRedirectUrlPreservesPath(): void
    {
        $subscriber = new RedirectToHttpsSubscriber(true, new HttpUtils());
        $event = $this->makeEvent('http://example.com/admin/parts?q=test');

        $subscriber->onKernelRequest($event);

        $this->assertTrue($event->hasResponse());
        $this->assertStringContainsString('/admin/parts', $event->getResponse()->getTargetUrl());
        $this->assertStringContainsString('q=test', $event->getResponse()->getTargetUrl());
    }

    public function testSubscriberListensToKernelRequestEvent(): void
    {
        $events = RedirectToHttpsSubscriber::getSubscribedEvents();
        $this->assertArrayHasKey('kernel.request', $events);
    }
}
