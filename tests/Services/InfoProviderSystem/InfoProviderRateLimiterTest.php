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

use App\Exceptions\InfoProviderRateLimitExceededException;
use App\Services\InfoProviderSystem\InfoProviderRateLimiter;
use App\Settings\InfoProviderSystem\InfoProviderGeneralSettings;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

/**
 * @see InfoProviderRateLimiter
 */
final class InfoProviderRateLimiterTest extends TestCase
{
    private function limiter(int $per10s, int $perMinute, int $maxWait): InfoProviderRateLimiter
    {
        $settings = new InfoProviderGeneralSettings();
        $settings->rateLimitPer10Seconds = $per10s;
        $settings->rateLimitPerMinute = $perMinute;
        $settings->rateLimitMaxWait = $maxWait;

        return new InfoProviderRateLimiter(new ArrayAdapter(), $settings);
    }

    public function testRequestsWithinTheLimitPassWithoutDelay(): void
    {
        $limiter = $this->limiter(per10s: 3, perMinute: 0, maxWait: 30);

        $start = microtime(true);
        for ($i = 0; $i < 3; $i++) {
            $limiter->await('test');
        }

        self::assertLessThan(1.0, microtime(true) - $start, 'Requests within the limit must not be delayed');
    }

    public function testExceedingTheLimitFailsAfterTheMaximumWait(): void
    {
        //A maximum wait of 0 turns the delay into an immediate error, which keeps this test fast
        $limiter = $this->limiter(per10s: 2, perMinute: 0, maxWait: 0);

        $limiter->await('test');
        $limiter->await('test');

        $this->expectException(InfoProviderRateLimitExceededException::class);
        $limiter->await('test');
    }

    public function testTheExceptionNamesTheProvider(): void
    {
        $limiter = $this->limiter(per10s: 1, perMinute: 0, maxWait: 0);
        $limiter->await('trustedparts');

        try {
            $limiter->await('trustedparts');
            self::fail('Expected the rate limit to be enforced');
        } catch (InfoProviderRateLimitExceededException $e) {
            self::assertSame('trustedparts', $e->providerKey);
            self::assertStringContainsString('trustedparts', $e->getMessage());
        }
    }

    public function testTheExceptionIsA429WithARetryAfterHeader(): void
    {
        //Hitting the limit is an expected, temporary condition - an automated caller must be able to tell it
        //apart from a real failure, and to know when to come back
        $limiter = $this->limiter(per10s: 1, perMinute: 0, maxWait: 0);
        $limiter->await('test');

        try {
            $limiter->await('test');
            self::fail('Expected the rate limit to be enforced');
        } catch (InfoProviderRateLimitExceededException $e) {
            self::assertSame(429, $e->getStatusCode());
            self::assertArrayHasKey('Retry-After', $e->getHeaders());
            self::assertGreaterThan(0, (int) $e->getHeaders()['Retry-After']);
            self::assertSame($e->retryAfter, (int) $e->getHeaders()['Retry-After']);
        }
    }

    public function testEveryProviderHasItsOwnBudget(): void
    {
        //The limits are per provider account, so exhausting one provider must not block another
        $limiter = $this->limiter(per10s: 1, perMinute: 0, maxWait: 0);

        $limiter->await('trustedparts');
        $limiter->await('digikey');

        $this->expectException(InfoProviderRateLimitExceededException::class);
        $limiter->await('digikey');
    }

    public function testBothWindowsAreEnforced(): void
    {
        //Ten per 10 seconds would allow this, but the minute window only permits two
        $limiter = $this->limiter(per10s: 10, perMinute: 2, maxWait: 0);

        $limiter->await('test');
        $limiter->await('test');

        $this->expectException(InfoProviderRateLimitExceededException::class);
        $limiter->await('test');
    }

    public function testZeroLimitsDisableTheRateLimiting(): void
    {
        $limiter = $this->limiter(per10s: 0, perMinute: 0, maxWait: 0);

        for ($i = 0; $i < 50; $i++) {
            $limiter->await('test');
        }

        self::assertTrue(true, 'Without configured limits, no request is ever delayed or rejected');
    }
}
