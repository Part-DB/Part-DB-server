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
 * Where an external part link's URL comes from.
 */
enum ExternalPartLinkSourceType: string
{
    /** The URL is computed from a URL template with placeholders like {mpn}. */
    case TEMPLATE = 'template';
    /** The URL is read verbatim from a part parameter with a given name. */
    case PARAMETER = 'parameter';

    public function toTranslationKey(): string
    {
        return 'external_part_link.source_type.' . $this->value;
    }
}
