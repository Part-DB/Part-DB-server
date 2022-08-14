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

namespace App\Repository\Parts;

use App\Entity\Parts\Part;
use App\Entity\Parts\Supplier;
use App\Repository\AbstractPartsContainingRepository;
use Doctrine\ORM\QueryBuilder;
use InvalidArgumentException;

class SupplierRepository extends AbstractPartsContainingRepository
{
    public function getParts(object $element, array $order_by = ['name' => 'ASC']): array
    {
        if (!$element instanceof Supplier) {
            throw new InvalidArgumentException('$element must be an Supplier!');
        }

        $qb = new QueryBuilder($this->getEntityManager());

        $qb->select('part')
            ->from(Part::class, 'part')
            ->leftJoin('part.orderdetails', 'orderdetail')
            ->where('orderdetail.supplier = ?1')
            ->setParameter(1, $element);

        foreach ($order_by as $field => $order) {
            $qb->addOrderBy('part.'.$field, $order);
        }

        return $qb->getQuery()->getResult();
    }

    public function getPartsCount(object $element): int
    {
        if (!$element instanceof Supplier) {
            throw new InvalidArgumentException('$element must be an Supplier!');
        }

        $qb = new QueryBuilder($this->getEntityManager());

        $qb->select('COUNT(part.id)')
            ->from(Part::class, 'part')
            ->leftJoin('part.orderdetails', 'orderdetail')
            ->where('orderdetail.supplier = ?1')
            ->setParameter(1, $element);

        return (int) $qb->getQuery()->getSingleScalarResult();
    }
}
