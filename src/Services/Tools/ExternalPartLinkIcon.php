<?php
/**
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 * Copyright (C) 2019 - 2022 Jan Böhmer (https://github.com/jbtronics)
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

declare(strict_types=1);

namespace App\Services\Tools;

/**
 * The icon an external part link can be rendered with. This is a closed whitelist:
 * link definitions cannot supply arbitrary HTML or icon classes.
 */
enum ExternalPartLinkIcon: string
{
    case EXTERNAL_LINK = 'external-link';
    case SEARCH = 'search';
    case SHOPPING_CART = 'shopping-cart';
    case INDUSTRY = 'industry';
    case GLOBE = 'globe';
    case FILE = 'file';
    case BOX = 'box';
    case INFO = 'info';

    public function toTranslationKey(): string
    {
        return 'external_part_link.icon.' . $this->value;
    }

    public function toFontAwesomeClass(): string
    {
        return match ($this) {
            self::EXTERNAL_LINK => 'fas fa-external-link-alt',
            self::SEARCH => 'fas fa-search',
            self::SHOPPING_CART => 'fas fa-shopping-cart',
            self::INDUSTRY => 'fas fa-industry',
            self::GLOBE => 'fas fa-globe',
            self::FILE => 'fas fa-file-alt',
            self::BOX => 'fas fa-box',
            self::INFO => 'fas fa-info-circle',
        };
    }
}
