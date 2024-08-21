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

enum PartTableColumns : string implements TranslatableInterface
{

    case NAME = "name";
    case ID = "id";
    case IPN = "ipn";
    case DESCRIPTION = "description";
    case CATEGORY = "category";
    case FOOTPRINT = "footprint";
    case MANUFACTURER = "manufacturer";
    case LOCATION = "storage_location";
    case AMOUNT = "amount";
    case MIN_AMOUNT = "minamount";
    case PART_UNIT = "partUnit";
    case ADDED_DATE = "addedDate";
    case LAST_MODIFIED = "lastModified";
    case NEEDS_REVIEW = "needs_review";
    case FAVORITE = "favorite";
    case MANUFACTURING_STATUS = "manufacturing_status";
    case MPN = "manufacturer_product_number";
    case MASS = "mass";
    case TAGS = "tags";
    case ATTACHMENTS = "attachments";
    case EDIT = "edit";

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        $key = match($this) {
            self::LOCATION => 'part.table.storeLocations',
            self::NEEDS_REVIEW => 'part.table.needsReview',
            self::MANUFACTURING_STATUS => 'part.table.manufacturingStatus',
            self::MPN => 'part.table.mpn',
            default => 'part.table.' . $this->value,
        };

        return $translator->trans($key, locale: $locale);
    }
}