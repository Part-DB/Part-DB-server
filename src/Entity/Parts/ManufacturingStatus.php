<?php
/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2023 Jan Böhmer (https://github.com/jbtronics)
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


namespace App\Entity\Parts;

enum ManufacturingStatus: string
{
    /** Part has been announced, but is not in production yet */
    case ANNOUNCED = 'announced';
    /** Part is in production and will be for the foreseeable future */
    case ACTIVE = 'active';
    /** Not recommended for new designs. */
    case NRFND = 'nrfnd';
    /** End of life: Part will become discontinued soon */
    case EOL = 'eol';
    /** Part is obsolete/discontinued by the manufacturer. */
    case DISCONTINUED = 'discontinued';

    /** Status not set */
    case NOT_SET = '';

    public function toTranslationKey(): string
    {
        return match ($this) {
            self::ANNOUNCED => 'm_status.announced',
            self::ACTIVE => 'm_status.active',
            self::NRFND => 'm_status.nrfnd',
            self::EOL => 'm_status.eol',
            self::DISCONTINUED => 'm_status.discontinued',
            self::NOT_SET => '',
        };
    }
}