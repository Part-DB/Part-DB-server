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

use App\Services\InfoProviderSystem\Providers\InfoProviderInterface;

/**
 * An exception denoting that a required info provider is not active. This can be used to display a user-friendly error message,
 * when a user tries to use an info provider that is not active.
 */
class InfoProviderNotActiveException extends \RuntimeException
{
    public function __construct(public readonly string $providerKey, public readonly string $friendlyName)
    {
        parent::__construct(sprintf('The info provider "%s" (%s) is not active.', $this->friendlyName, $this->providerKey));
    }

    /**
     * Creates an instance of this exception from an info provider instance
     * @param  InfoProviderInterface  $provider
     * @return self
     */
    public static function fromProvider(InfoProviderInterface $provider): self
    {
        return new self($provider->getProviderKey(), $provider->getProviderInfo()['name'] ?? '???');
    }
}
