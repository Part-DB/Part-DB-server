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

use App\EventSubscriber\MaintenanceModeSubscriber;
use App\Services\System\UpdateExecutor;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

final class MaintenanceModeSubscriberTest extends TestCase
{
    private function makeSubscriber(bool $maintenanceActive): MaintenanceModeSubscriber
    {
        $executor = $this->createMock(UpdateExecutor::class);
        $executor->method('isMaintenanceMode')->willReturn($maintenanceActive);
        $executor->method('getMaintenanceInfo')->willReturn(
            $maintenanceActive ? ['reason' => 'Test update', 'enabled_at' => date('Y-m-d H:i:s')] : null
        );
        return new MaintenanceModeSubscriber($executor);
    }

    private function makeEvent(string $url = 'http://example.com/'): RequestEvent
    {
        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = Request::create($url);
        return new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);
    }

    public function testNoMaintenanceModeDoesNotSetResponse(): void
    {
        $subscriber = $this->makeSubscriber(false);
        $event = $this->makeEvent();

        $subscriber->onKernelRequest($event);

        // When not in maintenance mode, no response is ever set regardless of SAPI
        $this->assertFalse($event->hasResponse());
    }

    public function testCliRequestIsNeverBlocked(): void
    {
        // Tests run from CLI (PHP_SAPI === 'cli'), so maintenance mode never blocks CLI requests.
        // This verifies the intentional behaviour: maintenance mode only affects web requests.
        $subscriber = $this->makeSubscriber(true);
        $event = $this->makeEvent();

        $subscriber->onKernelRequest($event);

        // CLI requests pass through even with maintenance active
        $this->assertFalse($event->hasResponse());
    }

    public function testSubRequestIsIgnored(): void
    {
        $subscriber = $this->makeSubscriber(true);
        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = Request::create('http://example.com/');
        $event = new RequestEvent($kernel, $request, HttpKernelInterface::SUB_REQUEST);

        $subscriber->onKernelRequest($event);

        $this->assertFalse($event->hasResponse());
    }

    public function testSubscriberListensToKernelRequest(): void
    {
        $events = MaintenanceModeSubscriber::getSubscribedEvents();
        $this->assertArrayHasKey(KernelEvents::REQUEST, $events);
    }

    public function testSubscriberListensWithHighPriority(): void
    {
        $events = MaintenanceModeSubscriber::getSubscribedEvents();
        $config = $events[KernelEvents::REQUEST];
        // Config is ['methodName', priority]
        $priority = is_array($config) ? (int) ($config[1] ?? 0) : 0;
        $this->assertGreaterThan(0, $priority, 'Maintenance subscriber should run with high priority');
    }
}
