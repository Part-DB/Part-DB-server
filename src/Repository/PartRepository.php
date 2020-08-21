<?php
/**
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 * Copyright (C) 2019 - 2020 Jan Böhmer (https://github.com/jbtronics)
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

declare(strict_types=1);

/**
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 * Copyright (C) 2019 Jan Böhmer (https://github.com/jbtronics)
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA
 */

namespace App\Repository;

use App\Entity\Parts\PartLot;
use Doctrine\ORM\QueryBuilder;

class PartRepository extends NamedDBElementRepository
{
    /**
     * Gets the summed up instock of all parts (only parts without an measurent unit).
     *
     * @throws \Doctrine\ORM\NoResultException
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function getPartsInstockSum(): float
    {
        $qb = new QueryBuilder($this->getEntityManager());
        $qb->select('SUM(part_lot.amount)')
            ->from(PartLot::class, 'part_lot')
            ->leftJoin('part_lot.part', 'part')
            ->where('part.partUnit IS NULL');

        $query = $qb->getQuery();

        return (float) ($query->getSingleScalarResult() ?? 0.0);
    }

    /**
     * Gets the number of parts that has price informations.
     *
     * @throws \Doctrine\ORM\NoResultException
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function getPartsCountWithPrice(): int
    {
        $qb = $this->createQueryBuilder('part');
        $qb->select('COUNT(part)')
            ->innerJoin('part.orderdetails', 'orderdetail')
            ->innerJoin('orderdetail.pricedetails', 'pricedetail')
            ->where('pricedetail.price > 0.0');

        $query = $qb->getQuery();

        return (int) ($query->getSingleScalarResult() ?? 0);
    }
}
