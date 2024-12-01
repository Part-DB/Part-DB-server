<?php
/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2024 Jan Böhmer (https://github.com/jbtronics)
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


namespace App\Helpers;

use Symfony\Component\HttpFoundation\IpUtils;

/**
 * Utils to assist with IP anonymization.
 * The IPUtils::anonymize has a certain edgecase with local-link addresses, which is handled here.
 * See: https://github.com/Part-DB/Part-DB-server/issues/782
 */
final class IPAnonymizer
{
    public static function anonymize(string $ip): string
    {
        /**
         * If the IP contains a % symbol, then it is a local-link address with scoping according to RFC 4007
         * In that case, we only care about the part before the % symbol, as the following functions, can only work with
         * the IP address itself. As the scope can leak information (containing interface name), we do not want to
         * include it in our anonymized IP data.
         */
        if (str_contains($ip, '%')) {
            $ip = substr($ip, 0, strpos($ip, '%'));
        }

        return IpUtils::anonymize($ip);
    }
}