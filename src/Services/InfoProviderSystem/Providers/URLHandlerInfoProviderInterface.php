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

/**
 * If an interface
 */
interface URLHandlerInfoProviderInterface
{
    /**
     * Returns a list of supported domains (e.g. ["digikey.com"])
     * @return array An array of supported domains
     */
    public function getHandledDomains(): array;

    /**
     * Extracts the unique ID of a part from a given URL. It is okay if this is not a canonical ID, as long as it can be used to uniquely identify the part within this provider.
     * @param  string  $url The URL to extract the ID from
     * @return string|null The extracted ID, or null if the URL is not valid for this provider
     */
    public function getIDFromURL(string $url): ?string;
}
