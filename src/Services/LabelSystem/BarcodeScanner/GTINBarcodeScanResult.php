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

use GtinValidation\GtinValidator;

readonly class GTINBarcodeScanResult implements BarcodeScanResultInterface
{

    private GtinValidator $validator;

    public function __construct(
        public string $gtin,
    ) {
        $this->validator = new GtinValidator($this->gtin);
    }

    public function getDecodedForInfoMode(): array
    {
        $obj = $this->validator->getGtinObject();
        return [
            'GTIN' => $this->gtin,
            'GTIN type' => $obj->getType(),
            'Valid' => $this->validator->isValid() ? 'Yes' : 'No',
        ];
    }

    /**
     * Checks if the given input is a valid GTIN. This is used to determine whether a scanned barcode should be interpreted as a GTIN or not.
     * @param  string  $input
     * @return bool
     */
    public static function isValidGTIN(string $input): bool
    {
        try {
            return (new GtinValidator($input))->isValid();
        } catch (\Exception $e) {
            return false;
        }
    }
}
