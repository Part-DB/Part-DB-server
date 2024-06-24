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
namespace App\Entity\Base;

use App\Entity\Parts\Part;

interface PartsContainingRepositoryInterface
{
    /**
     * Returns all parts associated with this element.
     *
     * @param object $element  the element for which the parts should be determined
     * @param string $nameOrderDirection  the direction in which the parts should be ordered by name, either ASC or DESC
     *
     * @return Part[]
     */
    public function getParts(object $element, string $nameOrderDirection = "ASC"): array;

    /**
     * Gets the count of the parts associated with this element.
     *
     * @param object $element the element for which the parts should be determined
     */
    public function getPartsCount(object $element): int;
}
