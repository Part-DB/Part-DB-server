<?php
/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2025 Jan Böhmer (https://github.com/jbtronics)
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

use Throwable;

class OAuthReconnectRequiredException extends \RuntimeException
{
    private string $providerName = "unknown";

    public function __construct(string $message = "You need to reconnect the OAuth connection for this provider!", int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    public static function forProvider(string $providerName): self
    {
        $exception = new self("You need to reconnect the OAuth connection for the provider '$providerName'!");
        $exception->providerName = $providerName;
        return $exception;
    }

    public function getProviderName(): string
    {
        return $this->providerName;
    }
}
