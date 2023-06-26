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

namespace App\Entity\LabelSystem;

enum BarcodeType: string
{
    case NONE = 'none';
    case QR = 'qr';
    case CODE39 = 'code39';
    case DATAMATRIX = 'datamatrix';
    case CODE93 = 'code93';
    case CODE128 = 'code128';

    /**
     * Returns true if the barcode is none. (Useful for twig templates)
     * @return bool
     */
    public function isNone(): bool
    {
        return $this === self::NONE;
    }

    /**
     * Returns true if the barcode is a 1D barcode (Code39, etc.).
     * @return bool
     */
    public function is1D(): bool
    {
        return match ($this) {
            self::CODE39, self::CODE93, self::CODE128 => true,
            default => false,
        };
    }

    /**
     * Returns true if the barcode is a 2D barcode (QR code, datamatrix).
     * @return bool
     */
    public function is2D(): bool
    {
        return match ($this) {
            self::QR, self::DATAMATRIX => true,
            default => false,
        };
    }
}
