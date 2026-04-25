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

use App\Exceptions\ProviderIDNotSupportedException;

trait FixAndValidateUrlTrait
{
    private function fixAndValidateURL(string $url): string
    {
        $originalUrl = $url;

        //Add scheme if missing
        if (!preg_match('/^https?:\/\//', $url)) {
            //Remove any leading slashes
            $url = ltrim($url, '/');

            //If the URL starts with https:/ or http:/, add the missing slash
            //Traefik removes the double slash as secruity measure, so we want to be forgiving and add it back if needed
            //See https://github.com/Part-DB/Part-DB-server/issues/1296
            if (preg_match('/^https?:\/[^\/]/', $url)) {
                $url = preg_replace('/^(https?:)\/([^\/])/', '$1//$2', $url);
            } else {
                $url = 'https://'.$url;
            }
        }

        //If this is not a valid URL with host, domain and path, throw an exception
        if (filter_var($url, FILTER_VALIDATE_URL) === false ||
            parse_url($url, PHP_URL_HOST) === null ||
            parse_url($url, PHP_URL_PATH) === null) {
            throw new ProviderIDNotSupportedException("The given ID is not a valid URL: ".$originalUrl);
        }

        return $url;
    }
}
