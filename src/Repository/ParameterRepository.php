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
namespace App\Repository;

use App\Entity\Parameters\AbstractParameter;

/**
 * @template TEntityClass of AbstractParameter
 * @extends DBElementRepository<TEntityClass>
 */
class ParameterRepository extends DBElementRepository
{
    /**
     * Find parameters using a parameter name
     * @param  string  $name The name to search for
     * @param  bool  $exact True, if only exact names should match. False, if the name just needs to be contained in the parameter name
     * @phpstan-return array<array{name: string, symbol: string, unit: string}>
     */
    public function autocompleteParamName(string $name, bool $exact = false, int $max_results = 50): array
    {
        $qb = $this->createQueryBuilder('parameter');

        $qb->distinct()
            ->select('parameter.name')
            ->addSelect('parameter.symbol')
            ->addSelect('parameter.unit')
            ->where('ILIKE(parameter.name, :name) = TRUE');
        if ($exact) {
            $qb->setParameter('name', $name);
        } else {
            $qb->setParameter('name', '%'.$name.'%');
        }

        $qb->setMaxResults($max_results);

        return $qb->getQuery()->getArrayResult();
    }
}
