<?php

declare(strict_types=1);

/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2024 Jan Böhmer (https://github.com/jbtronics)
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

namespace App\DataTables\DTO;

/**
 * Lightweight data structure representing a Part Lot for table display.
 * Contains only essential fields needed for rendering storage locations.
 */
readonly class PartLotDTO
{
    public function __construct(
        public int $id,
        public ?int $storage_location_id,
        public ?string $storage_location_name,
        public ?string $storage_location_fullPath,
    ) {
    }

    public function getStorageLocation(): ?object
    {
        if ($this->storage_location_id === null) {
            return null;
        }

        // Return a simple object with needed methods for rendering
        return new class($this->storage_location_id, $this->storage_location_name, $this->storage_location_fullPath) {
            public function __construct(
                private readonly int $id,
                private readonly ?string $name,
                private readonly ?string $fullPath,
            ) {
            }

            public function getId(): int
            {
                return $this->id;
            }

            public function getName(): ?string
            {
                return $this->name;
            }

            public function getFullPath(): ?string
            {
                return $this->fullPath;
            }
        };
    }
}
