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


namespace App\Services\InfoProviderSystem\Providers;

/**
 * This enum contains all capabilities (which data it can provide) a provider can have.
 */
enum ProviderCapabilities
{
    /** Basic information about a part, like the name, description, part number, manufacturer etc */
    case BASIC;

    /** Provider can provide a picture for a part */
    case PICTURE;

    /** Provider can provide datasheets for a part */
    case DATASHEET;

    /** Provider can provide prices for a part */
    case PRICE;

    /** Information about the footprint of a part */
    case FOOTPRINT;

    /**
     * Get the order index for displaying capabilities in a stable order.
     * @return int
     */
    public function getOrderIndex(): int
    {
        return match($this) {
            self::BASIC => 1,
            self::PICTURE => 2,
            self::DATASHEET => 3,
            self::PRICE => 4,
            self::FOOTPRINT => 5,
        };
    }

    public function getTranslationKey(): string
    {
        return 'info_providers.capabilities.' . match($this) {
                self::BASIC => 'basic',
                self::FOOTPRINT => 'footprint',
                self::PICTURE => 'picture',
                self::DATASHEET => 'datasheet',
                self::PRICE => 'price',
            };
    }

    public function getFAIconClass(): string
    {
        return 'fa-solid ' . match($this) {
                self::BASIC => 'fa-info-circle',
                self::FOOTPRINT => 'fa-microchip',
                self::PICTURE => 'fa-image',
                self::DATASHEET => 'fa-file-alt',
                self::PRICE => 'fa-money-bill-wave',
            };
    }
}
