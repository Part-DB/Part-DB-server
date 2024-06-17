<?php
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

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Parts\Part;
use App\Entity\Parts\PartLot;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\QueryBuilder;

/**
 * @extends NamedDBElementRepository<Part>
 */
class PartRepository extends NamedDBElementRepository
{
    /**
     * Gets the summed up instock of all parts (only parts without a measurement unit).
     *
     * @throws NoResultException
     * @throws NonUniqueResultException
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
     * Gets the number of parts that has price information.
     *
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    public function getPartsCountWithPrice(): int
    {
        $qb = $this->createQueryBuilder('part');
        $qb->select('COUNT(DISTINCT part)')
            ->innerJoin('part.orderdetails', 'orderdetail')
            ->innerJoin('orderdetail.pricedetails', 'pricedetail')
            ->where('pricedetail.price > 0.0');

        $query = $qb->getQuery();

        return (int) ($query->getSingleScalarResult() ?? 0);
    }

    /**
     * @return Part[]
     */
    public function autocompleteSearch(string $query, int $max_limits = 50): array
    {
        $qb = $this->createQueryBuilder('part');
        $qb->select('part')
            ->leftJoin('part.category', 'category')
            ->leftJoin('part.footprint', 'footprint')

            ->where('part.name LIKE :query')
            ->orWhere('part.description LIKE :query')
            ->orWhere('category.name LIKE :query')
            ->orWhere('footprint.name LIKE :query')
            ;

        $qb->setParameter('query', '%'.$query.'%');

        $qb->setMaxResults($max_limits);
        $qb->orderBy('NATSORT(part.name)', 'ASC');

        return $qb->getQuery()->getResult();
    }
}
