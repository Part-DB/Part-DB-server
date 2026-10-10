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


namespace App\Services\InfoProviderSystem\Providers;

use App\Services\InfoProviderSystem\DTOs\ProviderInfoDTO;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Throttles the requests of an info provider to a website, which does not want to be hammered with requests:
 * All requests are spaced by a configurable delay, and after the website has refused a request, no requests are sent
 * at all for some time.
 *
 * The state is kept in the cache (keyed by the provider key), so it is shared between different lookups and PHP processes.
 *
 * The using class must have a $client (HttpClientInterface) and a $partInfoCache (CacheItemPoolInterface) property.
 * @property-read HttpClientInterface $client
 * @property-read CacheItemPoolInterface $partInfoCache
 */
trait ThrottledRequestTrait
{
    /** @var int[] The HTTP status codes with which a website tells us that it does not want our requests (anymore) */
    private const REFUSED_STATUS_CODES = [403, 429, 503];
    /** @var int The time (in seconds) no requests are sent to the website, after it has refused one */
    private const PAUSE_DURATION = 3600;

    abstract public function getProviderInfo(): ProviderInfoDTO;

    /**
     * Returns the minimum time (in seconds) between two requests. 0 disables the delay.
     */
    abstract private function getRequestDelay(): int;

    /**
     * Sends a request to the website. Every request of the provider has to use this method: It spaces the requests,
     * and once the website has refused a request, it does not send any further requests for some time.
     */
    private function throttledRequest(string $method, string $url, array $options = []): ResponseInterface
    {
        $providerInfo = $this->getProviderInfo();

        $paused = $this->partInfoCache->getItem($providerInfo->key . '_paused');
        if ($paused->isHit() && is_array($paused->get()) && ($paused->get()['until'] ?? 0) > time()) {
            throw new \RuntimeException(sprintf(
                'The %s provider is paused until %s, because the website refused a request (%s). No requests are sent to it until then.',
                $providerInfo->name, date('Y-m-d H:i:s T', (int) $paused->get()['until']), $paused->get()['reason'] ?? 'unknown reason'
            ));
        }

        $this->waitForNextRequest($providerInfo->key . '_next_request');

        $response = $this->client->request($method, $url, $options);

        $status = $response->getStatusCode();
        if (in_array($status, self::REFUSED_STATUS_CODES, true)) {
            $until = time() + self::PAUSE_DURATION;

            $paused->set(['until' => $until, 'reason' => 'HTTP status ' . $status]);
            $paused->expiresAfter(self::PAUSE_DURATION);
            $this->partInfoCache->save($paused);

            throw new \RuntimeException(sprintf(
                '%s refused the request (HTTP status %d). The %s provider is paused until %s, no requests are sent to it until then.',
                parse_url($url, PHP_URL_HOST) ?: 'The website', $status, $providerInfo->name, date('Y-m-d H:i:s T', $until)
            ));
        }

        return $response;
    }

    /**
     * Sleeps until the configured delay has passed since the last request to the website. The time of the next
     * allowed request is stored in the cache, so the delay is kept between different lookups and PHP processes too.
     */
    private function waitForNextRequest(string $cacheKey): void
    {
        $delay = $this->getRequestDelay();
        if ($delay <= 0) {
            return;
        }

        $item = $this->partInfoCache->getItem($cacheKey);
        $now = microtime(true);
        $slot = $item->isHit() ? max($now, (float) $item->get()) : $now;

        //Reserve the time after our slot before sleeping, so that parallel requests queue up behind us
        $item->set($slot + $delay);
        $item->expiresAfter((int) ceil($slot - $now) + $delay);
        $this->partInfoCache->save($item);

        //usleep() might return early (e.g. when interrupted by a signal), so sleep until our slot is really reached
        while (($remaining = $slot - microtime(true)) > 0) {
            usleep((int) ceil($remaining * 1_000_000));
        }

        //If we overslept, the next request must not happen before the delay has passed since our (late) request
        $now = microtime(true);
        $item = $this->partInfoCache->getItem($cacheKey);
        if (!$item->isHit() || (float) $item->get() < $now + $delay) {
            $item->set($now + $delay);
            $item->expiresAfter($delay + 1);
            $this->partInfoCache->save($item);
        }
    }
}
