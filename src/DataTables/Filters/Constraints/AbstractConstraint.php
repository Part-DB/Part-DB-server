<?php

declare(strict_types=1);

/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2022 Jan Böhmer (https://github.com/jbtronics)
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
namespace App\DataTables\Filters\Constraints;

use App\DataTables\Filters\FilterInterface;

abstract class AbstractConstraint implements FilterInterface
{
    use FilterTrait;

    protected ?string $identifier;


    /**
     * Determines whether this constraint is active or not. This should be decided accordingly to the value of the constraint
     * @return bool True if the constraint is active, false otherwise
     */
    abstract public function isEnabled(): bool;

    public function __construct(
        /**
     * @var string The property where this BooleanConstraint should apply to
     */
    protected string $property,
    ?string $identifier = null)
    {
        $this->identifier = $identifier ?? $this->generateParameterIdentifier($property);
    }
}
