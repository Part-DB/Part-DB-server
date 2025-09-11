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


namespace App\Settings\BehaviorSettings;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

enum SidebarItems: string implements TranslatableInterface
{
    case TOOLS = "tools";
    case CATEGORIES = "categories";
    case LOCATIONS = "locations";
    case FOOTPRINTS = "footprints";
    case MANUFACTURERS = "manufacturers";
    case SUPPLIERS = "suppliers";
    case PROJECTS = "projects";

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        $key = match($this) {
            self::TOOLS => 'tools.label',
            self::CATEGORIES => 'category.labelp',
            self::LOCATIONS => 'storelocation.labelp',
            self::FOOTPRINTS => 'footprint.labelp',
            self::MANUFACTURERS => 'manufacturer.labelp',
            self::SUPPLIERS => 'supplier.labelp',
            self::PROJECTS => 'project.labelp',
        };

        return $translator->trans($key, locale: $locale);
    }
}