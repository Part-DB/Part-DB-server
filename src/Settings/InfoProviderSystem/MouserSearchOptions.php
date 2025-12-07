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


namespace App\Settings\InfoProviderSystem;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

enum MouserSearchOptions: string implements TranslatableInterface
{
    case NONE = "None";
    case ROHS = "Rohs";
    case IN_STOCK = "InStock";
    case ROHS_AND_INSTOCK = "RohsAndInStock";

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        $key = match($this) {
            self::NONE => "settings.ips.mouser.searchOptions.none",
            self::ROHS => "settings.ips.mouser.searchOptions.rohs",
            self::IN_STOCK => "settings.ips.mouser.searchOptions.inStock",
            self::ROHS_AND_INSTOCK => "settings.ips.mouser.searchOptions.rohsAndInStock",
        };

        return $translator->trans($key, locale: $locale);
    }
}