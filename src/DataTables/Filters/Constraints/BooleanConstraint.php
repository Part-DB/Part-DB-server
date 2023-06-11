<?php
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

use Doctrine\ORM\QueryBuilder;

class BooleanConstraint extends AbstractConstraint
{
    public function __construct(string $property, string $identifier = null, /** @var bool|null The value of our constraint */
    protected ?bool $value = null)
    {
        parent::__construct($property, $identifier);
    }

    /**
     * Gets the value of this constraint. Null means "don't filter", true means "filter for true", false means "filter for false".
     */
    public function getValue(): ?bool
    {
        return $this->value;
    }

    /**
     * Sets the value of this constraint. Null means "don't filter", true means "filter for true", false means "filter for false".
     */
    public function setValue(?bool $value): void
    {
        $this->value = $value;
    }

    public function isEnabled(): bool
    {
        return $this->value !== null;
    }


    public function apply(QueryBuilder $queryBuilder): void
    {
        //Do not apply a filter if value is null (filter is set to ignore)
        if(!$this->isEnabled()) {
            return;
        }

        $this->addSimpleAndConstraint($queryBuilder, $this->property, $this->identifier, '=', $this->value);
    }
}