<?php

declare(strict_types=1);

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
namespace App\Validator;

interface UniqueValidatableInterface
{
    /**
     * This method should return an array of fields that should be used to compare the objects for the UniqueObjectCollection constraint.
     * All instances of the same class should return the same fields.
     * The value must be a comparable value (e.g. string, int, float, bool, null).
     * @return array An array of the form ['field1' => 'value1', 'field2' => 'value2', ...]
     */
    public function getComparableFields(): array;
}
