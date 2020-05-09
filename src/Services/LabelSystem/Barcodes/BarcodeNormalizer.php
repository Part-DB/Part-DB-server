<?php
/**
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 * Copyright (C) 2019 - 2020 Jan Böhmer (https://github.com/jbtronics)
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

namespace App\Services\LabelSystem\Barcodes;


final class BarcodeNormalizer
{
    private const PREFIX_TYPE_MAP = [
        'L' => 'lot',
        'P' => 'part',
        'S' => 'location',
    ];

    /**
     * Parses barcode content and normalizes it.
     * Returns an array in the format ['part', 1]: First entry contains element type, second the ID of the element
     * @param  string  $input
     * @return array
     */
    public function normalizeBarcodeContent(string $input): array
    {
        $input = trim($input);
        $matches = [];

        //Some scanner output '-' as ß, so replace it (ß is never used, so we can replace it safely)
        $input = str_replace('ß', '-', $input);

        //Extract parts from QR code's URL
        if (preg_match('#^https?://.*/scan/(\w+)/(\d+)/?$#', $input, $matches)) {
            return [$matches[1], (int) $matches[2]];
        }

        //New Code39 barcode use L0001 format
        if (preg_match('#^([A-Z])(\d{4,})$#', $input, $matches)) {
            $prefix = $matches[1];
            $id = (int) $matches[2];

            if (!isset(self::PREFIX_TYPE_MAP[$prefix])) {
                throw new \InvalidArgumentException('Unknown prefix ' . $prefix);
            }
            return [self::PREFIX_TYPE_MAP[$prefix], $id];
        }

        //During development the L-000001 format was used
        if (preg_match('#^(\w)-(\d{6,})$#', $input, $matches)) {
            $prefix = $matches[1];
            $id = (int) $matches[2];

            if (!isset(self::PREFIX_TYPE_MAP[$prefix])) {
                throw new \InvalidArgumentException('Unknown prefix ' . $prefix);
            }
            return [self::PREFIX_TYPE_MAP[$prefix], $id];
        }

        //Legacy Part-DB location labels used $L00336 format
        if (preg_match('#^\$L(\d{5,})$#', $input, $matches)) {
            return ['location', (int) $matches[1]];
        }

        //Legacy Part-DB used EAN8 barcodes for part labels. Format 0000001(2) (note the optional 8th digit => checksum)
        if (preg_match('#^(\d{7})\d?$#', $input, $matches)) {
            return ['part', (int) $matches[1]];
        }


        throw new \InvalidArgumentException('Unknown barcode format!');
    }
}