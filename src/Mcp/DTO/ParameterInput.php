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
 * A single part parameter (e.g. "Voltage: 5V typ."), nested inside create_part/update_part. A present `id`
 * targets an existing parameter (belonging to the same part); an absent `id` creates a new one.
 */
readonly class ParameterInput
{
    public function __construct(
        public ?int $id,
        public string $name,
        public ?string $group,
        public ?string $symbol,
        public ?float $valueMin,
        public ?float $valueTypical,
        public ?float $valueMax,
        public ?string $unit,
        public ?string $valueText,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            id: PartInputHelpers::int($data, 'id'),
            name: (string) ($data['name'] ?? ''),
            group: PartInputHelpers::str($data, 'group'),
            symbol: PartInputHelpers::str($data, 'symbol'),
            valueMin: PartInputHelpers::float($data, 'valueMin'),
            valueTypical: PartInputHelpers::float($data, 'valueTypical'),
            valueMax: PartInputHelpers::float($data, 'valueMax'),
            unit: PartInputHelpers::str($data, 'unit'),
            valueText: PartInputHelpers::str($data, 'valueText'),
        );
    }
}
