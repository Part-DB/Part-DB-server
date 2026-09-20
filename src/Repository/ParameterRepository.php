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
use App\Entity\Parameters\ParameterDefinition;
use App\Entity\Parameters\PartParameter;
use App\Entity\Parts\Part;

/**
 * @template TEntityClass of AbstractParameter
 * @extends DBElementRepository<TEntityClass>
 */
class ParameterRepository extends DBElementRepository
{
    /**
     * UniqueEntity runs before Doctrine flushes orphan removals. Ignore a persisted PartParameter only when it has
     * already been removed from its owning Part's active collection; active database matches remain conflicts.
     *
     * @param array<string, mixed> $criteria
     * @return list<TEntityClass>
     */
    public function findActiveForUniqueValidation(array $criteria): array
    {
        return array_values(array_filter(
            $this->findBy($criteria),
            static function (AbstractParameter $parameter): bool {
                if (!$parameter instanceof PartParameter) {
                    return true;
                }

                $part = $parameter->getElement();

                return !$part instanceof Part || $part->getParameters()->contains($parameter);
            },
        ));
    }

    public function countByDefinition(ParameterDefinition $definition): int
    {
        return (int) $this->createQueryBuilder('parameter')
            ->select('COUNT(parameter.id)')
            ->where('parameter.definition = :definition')
            ->setParameter('definition', $definition)
            ->getQuery()
            ->getSingleScalarResult();
    }

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
