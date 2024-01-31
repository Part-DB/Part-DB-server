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
 * This enum represents the different types, where a barcode/QR-code can be generated from
 */
enum BarcodeSourceType
{
    /** This Barcode was generated using Part-DB internal recommended barcode generator */
    case INTERNAL;
    /** This barcode is containing an internal part number (IPN) */
    case IPN;
    /**
     * This barcode is a custom barcode from a third party like a vendor, which was set via the vendor_barcode
     * field of a part lot.
     */
    case VENDOR;
}