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

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

/**
 * Thrown when an info provider request had to be delayed by the rate limit for longer than the configured
 * maximum wait time, so that a caller gets a clear error instead of a request which seems to hang forever.
 *
 * This is an HTTP exception on purpose: hitting the rate limit is an expected, temporary condition, and every
 * entry point - the web interface, the REST API and the MCP tools alike - should answer it with 429 and a
 * Retry-After header rather than a 500, so that an automated caller can tell "too fast, try again shortly"
 * apart from "something is broken".
 */
class InfoProviderRateLimitExceededException extends TooManyRequestsHttpException
{
    /**
     * @param  int  $waitedSeconds How long this request was already delayed before giving up
     * @param  int  $retryAfter    How many seconds to wait before the next slot is free
     */
    public function __construct(
        public readonly string $providerKey,
        public readonly int $waitedSeconds,
        public readonly int $retryAfter,
    ) {
        parent::__construct($this->retryAfter, sprintf(
            'The rate limit for the info provider "%s" would delay this request by more than %d seconds. '
            .'Retry in %d seconds, or raise the info provider rate limit in the system settings.',
            $this->providerKey, $this->waitedSeconds, $this->retryAfter
        ));
    }
}
