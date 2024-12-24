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


namespace App\Services\LabelSystem\Barcodes;

/**
 * This class represents the result of a scan of a barcode that was printed by a third party
 * and contains useful information about an item, like a vendor id or the order quantity
 */

class VendorBarcodeScanResult
{
    public function __construct(
        public readonly ?string  $vendor = null,
        public readonly ?string $manufacturer_part_number = null,
        public readonly ?string $vendor_part_number = null,
        public readonly ?string $date_code = null,
        public readonly ?string $quantity = null,
        public readonly ?string $manufacturer = null
    )
    {
    }
}