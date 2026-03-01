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


namespace App\Services\LabelSystem\BarcodeScanner;

final readonly class AmazonBarcodeScanResult implements BarcodeScanResultInterface
{
    public function __construct(public string $asin) {
        if (!self::isAmazonBarcode($asin)) {
            throw new \InvalidArgumentException("The provided input '$asin' is not a valid Amazon barcode (ASIN)");
        }
    }

    public static function isAmazonBarcode(string $input): bool
    {
        //Amazon barcodes are 10 alphanumeric characters
        return preg_match('/^[A-Z0-9]{10}$/i', $input) === 1;
    }

    public function getDecodedForInfoMode(): array
    {
        return [
            'ASIN' => $this->asin,
        ];
    }
}
