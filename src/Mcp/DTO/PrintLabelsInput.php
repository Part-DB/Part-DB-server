<?php

declare(strict_types=1);

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

namespace App\Mcp\DTO;

use Symfony\Component\Validator\Constraints as Assert;

readonly class PrintLabelsInput
{
    public function __construct(
        /** @var string What the labels are for: "part", "part_lot" or "storelocation" */
        #[Assert\NotBlank]
        public string $targetType,
        /** @var string Database IDs of the parts, part lots or storage locations, e.g. "35" or "1,2,5-10" */
        #[Assert\NotBlank]
        public string $ids,
        /** @var int|null The label profile to use; omit to use the profile for this target type that has a default printer */
        #[Assert\Positive]
        public ?int $profileId = null,
        /** @var string|null The printer as "<agent id>|<printer id>" (or just the printer id if unique); omit to use the profile's default printer */
        public ?string $printer = null,
    ) {
    }
}
