<?php

declare(strict_types=1);

/**
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 * Copyright (C) 2019 - 2022 Jan Böhmer (https://github.com/jbtronics)
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
namespace App\Repository\Parts;

use App\Entity\Parts\PartCustomState;
use App\Repository\AbstractPartsContainingRepository;
use InvalidArgumentException;

class PartCustomStateRepository extends AbstractPartsContainingRepository
{
    public function getParts(object $element, string $nameOrderDirection = "ASC"): array
    {
        if (!$element instanceof PartCustomState) {
            throw new InvalidArgumentException('$element must be an PartCustomState!');
        }

        return $this->getPartsByField($element, $nameOrderDirection, 'partUnit');
    }

    public function getPartsCount(object $element): int
    {
        if (!$element instanceof PartCustomState) {
            throw new InvalidArgumentException('$element must be an PartCustomState!');
        }

        return $this->getPartsCountByField($element, 'partUnit');
    }
}
