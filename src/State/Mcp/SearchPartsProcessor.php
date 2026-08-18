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


namespace App\State\Mcp;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\DataTables\Filters\PartSearchFilter;
use App\Entity\Parts\Part;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;

class SearchPartsProcessor implements ProcessorInterface
{

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {

    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        if (!$data instanceof PartSearchFilter) {
            return [];
        }

        $qb = $this->entityManager->getRepository(Part::class)->createQueryBuilder('part');

        $data->apply($qb);
        $this->addJoins($qb);

        $qb->addGroupBy('part');

        return $qb->getQuery()->getResult();
    }

    private function addJoins(QueryBuilder $qb): void
    {
        $dql = $qb->getDQL();

        if (str_contains($dql, '_category')) {
            $qb->leftJoin('part.category', '_category');
        }
        if (str_contains($dql, '_storelocations')) {
            $qb->leftJoin('part.partLots', '_partLots');
            $qb->leftJoin('_partLots.storage_location', '_storelocations');
        }
        if (str_contains($dql, '_orderdetails') || str_contains($dql, '_suppliers')) {
            $qb->leftJoin('part.orderdetails', '_orderdetails');
            $qb->leftJoin('_orderdetails.supplier', '_suppliers');
        }
        if (str_contains($dql, '_manufacturer')) {
            $qb->leftJoin('part.manufacturer', '_manufacturer');
        }
        if (str_contains($dql, '_footprint')) {
            $qb->leftJoin('part.footprint', '_footprint');
        }
    }
}
