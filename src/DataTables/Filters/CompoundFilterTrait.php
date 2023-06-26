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
namespace App\DataTables\Filters;

use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\QueryBuilder;

trait CompoundFilterTrait
{

    /**
     * Find all child filters that are contained in this filter using reflection.
     * A map is returned to the form "property_name" => $filter_object
     * @return FilterInterface[]
     */
    protected function findAllChildFilters(): array
    {
        $filters = [];
        $reflection = new \ReflectionClass($this);

        foreach ($reflection->getProperties() as $property) {
            $value = $property->getValue($this);
            //We only want filters (objects implementing FilterInterface)
            if($value instanceof FilterInterface) {
                $filters[$property->getName()] = $value;
            }

            //Add filters in collections
            if ($value instanceof Collection) {
                foreach ($value as $key => $filter) {
                    if($filter instanceof FilterInterface) {
                        $filters[$property->getName() . '.' . (string) $key] = $filter;
                    }
                }
            }
        }
        return $filters;
    }

    /**
     * Applies all children filters that are declared as property of this filter using reflection.
     */
    protected function applyAllChildFilters(QueryBuilder $queryBuilder): void
    {
        //Retrieve all child filters and apply them
        $filters = $this->findAllChildFilters();

        foreach ($filters as $filter) {
            $filter->apply($queryBuilder);
        }
    }
}
