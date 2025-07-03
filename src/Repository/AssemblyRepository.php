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

namespace App\Repository;

use App\Entity\AssemblySystem\Assembly;

/**
 * @template TEntityClass of Assembly
 * @extends DBElementRepository<TEntityClass>
 */
class AssemblyRepository extends StructuralDBElementRepository
{
    /**
     * @return Assembly[]
     */
    public function autocompleteSearch(string $query, int $max_limits = 50): array
    {
        $qb = $this->createQueryBuilder('assembly');
        $qb->select('assembly')
            ->where('ILIKE(assembly.name, :query) = TRUE')
            ->orWhere('ILIKE(assembly.description, :query) = TRUE');

        $qb->setParameter('query', '%'.$query.'%');

        $qb->setMaxResults($max_limits);
        $qb->orderBy('NATSORT(assembly.name)', 'ASC');

        return $qb->getQuery()->getResult();
    }
}