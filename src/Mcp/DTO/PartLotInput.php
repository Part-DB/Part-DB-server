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

namespace App\Mcp\DTO;

/**
 * A single stock lot, nested inside create_part/update_part. A present `id` targets an existing lot (belonging
 * to the same part); an absent `id` creates a new one. `amount` may only be given when creating a brand-new lot -
 * to change the amount of an *existing* lot use the dedicated withdraw_part_stock/add_part_stock/stocktake_part_lot
 * tools instead, so stock changes always go through the same logging/validation path as the web UI.
 */
readonly class PartLotInput
{
    public function __construct(
        public ?int $id,
        public ?string $description,
        public ?string $comment,
        public ?string $expirationDate,
        public ?int $storageLocationId,
        public ?bool $instockUnknown,
        public ?float $amount,
        public ?bool $needsRefill,
        public ?int $ownerId,
        public ?string $userBarcode,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            id: PartInputHelpers::int($data, 'id'),
            description: PartInputHelpers::str($data, 'description'),
            comment: PartInputHelpers::str($data, 'comment'),
            expirationDate: PartInputHelpers::str($data, 'expirationDate'),
            storageLocationId: PartInputHelpers::int($data, 'storageLocationId'),
            instockUnknown: PartInputHelpers::bool($data, 'instockUnknown'),
            amount: PartInputHelpers::float($data, 'amount'),
            needsRefill: PartInputHelpers::bool($data, 'needsRefill'),
            ownerId: PartInputHelpers::int($data, 'ownerId'),
            userBarcode: PartInputHelpers::str($data, 'userBarcode'),
        );
    }
}
