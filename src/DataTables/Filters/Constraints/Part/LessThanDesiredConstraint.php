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
namespace App\DataTables\Filters\Constraints\Part;

use App\DataTables\Filters\Constraints\BooleanConstraint;
use App\Entity\Parts\PartLot;
use Doctrine\ORM\QueryBuilder;

class LessThanDesiredConstraint extends BooleanConstraint
{
    public function __construct(?string $property = null, ?string $identifier = null, ?bool $default_value = null)
    {
        parent::__construct($property ?? '(
                    SELECT COALESCE(SUM(ld_partLot.amount), 0.0)
                    FROM '.PartLot::class.' ld_partLot
                    WHERE ld_partLot.part = part.id
                    AND ld_partLot.instock_unknown = false
                    AND (ld_partLot.expiration_date IS NULL OR ld_partLot.expiration_date > CURRENT_DATE())
                )', $identifier ?? 'amountSumLessThanDesired', $default_value);
    }

    public function apply(QueryBuilder $queryBuilder): void
    {
        //Do not apply a filter if value is null (filter is set to ignore)
        if(!$this->isEnabled()) {
            return;
        }

        //If value is true, we want to filter for parts with stock < desired stock
        if ($this->value) {
            $queryBuilder->andHaving( $this->property . ' < part.minamount');
        } else {
            $queryBuilder->andHaving($this->property . ' >= part.minamount');
        }
    }
}
