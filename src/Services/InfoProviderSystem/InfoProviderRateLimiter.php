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

use App\Exceptions\InfoProviderRateLimitExceededException;
use App\Settings\InfoProviderSystem\InfoProviderGeneralSettings;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\RateLimiter\CompoundLimiter;
use Symfony\Component\RateLimiter\LimiterInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\CacheStorage;

/**
 * Paces the requests Part-DB sends to a single info provider.
 *
 * Providers enforce short-window rate limits (TrustedParts, for example, allows 50 requests per 10 seconds and
 * 150 per minute) and often bill per request, while Part-DB can produce bursts far above that: a bulk import or
 * a bulk refresh walks through hundreds of parts as fast as the network allows. Worse, the limit is per account,
 * so an external script using the same API credentials shares the budget - and neither side can see the other's
 * request count.
 *
 * This limiter is therefore the one place all provider traffic is paced, and it stores its counters in the
 * shared cache pool rather than in memory, so every web request, console command and background job counts
 * against the same budget. Each provider gets its own counters, since the limits are per provider account.
 *
 * Requests are delayed, not rejected: the caller waits until the next slot is free, which is what a bulk
 * operation wants. Only when the wait would exceed the configured maximum does it throw, so an interactive
 * request cannot hang indefinitely.
 *
 * Two things this deliberately does not do. It does not count cached provider responses - it is called from
 * PartInfoRetriever inside the cache callbacks, so only requests which really reach the provider are counted.
 * And it does not synchronize its counter updates with a lock, which means parallel processes can slightly
 * under-count; the configured limit should therefore stay below the provider's real one (the defaults do).
 */
final class InfoProviderRateLimiter
{
    /**
     * @var LimiterInterface[] Limiters per provider key, built lazily
     */
    private array $limiters = [];

    /**
     * @var float How long to sleep at most in one go, so a changed limit or a freed slot is noticed reasonably fast
     */
    private const MAX_SLEEP_STEP = 1.0;

    public function __construct(
        private readonly CacheItemPoolInterface $cache,
        private readonly InfoProviderGeneralSettings $settings,
    ) {
    }

    /**
     * Waits until the given provider may be queried again, and counts this request against its budget.
     *
     * @throws InfoProviderRateLimitExceededException if the wait would exceed the configured maximum
     */
    public function await(string $provider_key): void
    {
        $limiter = $this->limiterFor($provider_key);

        //No limits configured at all - nothing to pace
        if ($limiter === null) {
            return;
        }

        $max_wait = max(0, $this->settings->rateLimitMaxWait);
        $waited = 0.0;

        while (true) {
            $limit = $limiter->consume();

            if ($limit->isAccepted()) {
                return;
            }

            if ($waited >= $max_wait) {
                //Tell the caller when it is worth trying again, instead of leaving it to guess
                $retry_after = max(1, $limit->getRetryAfter()->getTimestamp() - time());

                throw new InfoProviderRateLimitExceededException($provider_key, $max_wait, $retry_after);
            }

            $sleep = min(self::MAX_SLEEP_STEP, $max_wait - $waited);
            usleep((int) ($sleep * 1_000_000));
            $waited += $sleep;
        }
    }

    /**
     * Builds the limiter for a provider, or returns null if no limit is configured.
     */
    private function limiterFor(string $provider_key): ?LimiterInterface
    {
        if (array_key_exists($provider_key, $this->limiters)) {
            return $this->limiters[$provider_key];
        }

        $storage = new CacheStorage($this->cache);
        $limiters = [];

        foreach ($this->windows() as $name => [$limit, $interval]) {
            $factory = new RateLimiterFactory([
                'id' => 'info_provider_'.$name,
                'policy' => 'sliding_window',
                'limit' => $limit,
                'interval' => $interval,
            ], $storage);

            $limiters[] = $factory->create($provider_key);
        }

        return $this->limiters[$provider_key] = match (count($limiters)) {
            0 => null,
            1 => $limiters[0],
            default => new CompoundLimiter($limiters),
        };
    }

    /**
     * @return array<string, array{int, string}> The configured windows as limit and interval, keyed by a name
     *                                           which is part of the counter's cache key
     */
    private function windows(): array
    {
        $windows = [];

        //A limit of 0 disables that window
        if ($this->settings->rateLimitPer10Seconds > 0) {
            $windows['10s'] = [$this->settings->rateLimitPer10Seconds, '10 seconds'];
        }

        if ($this->settings->rateLimitPerMinute > 0) {
            $windows['1m'] = [$this->settings->rateLimitPerMinute, '1 minute'];
        }

        return $windows;
    }
}
