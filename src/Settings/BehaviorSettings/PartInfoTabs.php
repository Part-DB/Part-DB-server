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


namespace App\Settings\BehaviorSettings;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The tabs (sections) of the part info page. The values are the element IDs of the tab panes, which are also used
 * as URL fragment to select a tab.
 */
enum PartInfoTabs: string implements TranslatableInterface
{
    case PART_LOTS = "part_lots";
    case COMMENT = "comment";
    case SPECIFICATIONS = "specifications";
    case ATTACHMENTS = "attachments";
    case SUPPLIERS = "suppliers";
    case ASSOCIATIONS = "associations";
    case HISTORY = "history";
    case PROJECTS = "projects";
    case TOOLS = "tools";
    case EXTENDED_INFO = "extended_info";

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        $key = match($this) {
            self::PART_LOTS => 'part.part_lots.label',
            self::COMMENT => 'comment.label',
            self::SPECIFICATIONS => 'part.info.specifications',
            self::ATTACHMENTS => 'attachment.labelp',
            self::SUPPLIERS => 'vendor.partinfo.shopping_infos',
            self::ASSOCIATIONS => 'part.edit.tab.associations',
            self::HISTORY => 'vendor.partinfo.history',
            self::PROJECTS => 'project.labelp',
            self::TOOLS => 'tools.label',
            self::EXTENDED_INFO => 'extended_info.label',
        };

        return $translator->trans($key, locale: $locale);
    }
}
