<?php
/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2025 Jan Böhmer (https://github.com/jbtronics)
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

interface BarcodeScanResultInterface
{
    /**
     * Returns all data that was decoded from the barcode in a format, that can be shown in a table to the user.
     * The return values of this function are not meant to be parsed by code again, but should just give a information
     * to the user.
     * The keys of the returned array are the first column of the table and the values are the second column.
     * @return array<string, string|int|float|null>
     */
    public function getDecodedForInfoMode(): array;
}