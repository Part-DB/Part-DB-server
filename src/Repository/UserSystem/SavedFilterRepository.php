<?php
/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2026 Jan Böhmer (https://github.com/jbtronics)
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

namespace App\Repository\UserSystem;

use App\Entity\UserSystem\SavedFilter;
use App\Entity\UserSystem\User;
use Doctrine\ORM\EntityRepository;

/**
 * @extends EntityRepository<SavedFilter>
 */
class SavedFilterRepository extends EntityRepository
{
    /**
     * Returns the saved filters, which the given user can see: their own filters and the shared filters of all users.
     * Without a user (e.g. for the anonymous user) only the shared filters are returned.
     * @return SavedFilter[]
     */
    public function findVisibleFor(?User $user): array
    {
        $qb = $this->createQueryBuilder('f')
            ->leftJoin('f.owner', 'o')
            ->addSelect('o')
            ->orderBy('f.name', 'ASC')
            ->addOrderBy('f.id', 'ASC');

        if ($user instanceof User && $user->getID() !== null) {
            $qb->where('f.shared = true OR f.owner = :user')
                ->setParameter('user', $user);
        } else {
            $qb->where('f.shared = true');
        }

        return $qb->getQuery()->getResult();
    }
}
