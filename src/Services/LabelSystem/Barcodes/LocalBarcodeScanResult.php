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

use App\Entity\LabelSystem\LabelSupportedElement;

/**
 * This class represents the result of a barcode scan of a barcode that uniquely identifies a local entity,
 * like an internally generated barcode or a barcode that was added manually to the system by a user
 */
class LocalBarcodeScanResult
{
    public function __construct(
        public readonly LabelSupportedElement $target_type,
        public readonly int $target_id,
        public readonly BarcodeSourceType $source_type,
    ) {
    }
}