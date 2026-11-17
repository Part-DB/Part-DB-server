<?php

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

use App\Entity\Base\AbstractPartsContainingDBElement;
use App\Entity\Base\PartsContainingRepositoryInterface;
use App\Entity\Parts\Part;
use InvalidArgumentException;

/**
 * @template TEntityClass of AbstractPartsContainingDBElement
 * @extends StructuralDBElementRepository<TEntityClass>
 */
abstract class AbstractPartsContainingRepository extends StructuralDBElementRepository implements PartsContainingRepositoryInterface
{
    /**
     * Returns all parts associated with this element.
     *
     * @param  object  $element  the element for which the parts should be determined
     * @param string $nameOrderDirection  the direction in which the parts should be ordered by name, either ASC or DESC
     *
     * @return Part[]
     */
    abstract public function getParts(object $element, string $nameOrderDirection = "ASC"): array;

    /**
     * Gets the count of the parts associated with this element.
     *
     * @param  object  $element  the element for which the parts should be determined
     * @return int
     */
    abstract public function getPartsCount(object $element): int;

    /**
     * Returns the field of the Part entity, which references this element type, or null if the parts are not
     * associated via a single field (e.g. via part lots or orderdetails).
     * If a field is given, the recursive functions load the parts of the whole subtree with a single query.
     */
    protected function getPartsFieldName(): ?string
    {
        return null;
    }

    /**
     * Returns the count of the parts associated with this element and all its children.
     * @return int
     */
    public function getPartsCountRecursive(AbstractPartsContainingDBElement $element): int
    {
        $elements = $this->getSubtree($element);

        $field_name = $this->getPartsFieldName();
        if ($field_name === null) {
            return array_sum(array_map($this->getPartsCount(...), $elements));
        }

        $count = 0;
        foreach (array_chunk($elements, 1000) as $chunk) {
            $count += (int) $this->getEntityManager()->createQueryBuilder()
                ->select('COUNT(part.id)')
                ->from(Part::class, 'part')
                ->where('part.'.$field_name.' IN (:elements)')
                ->setParameter('elements', $chunk)
                ->getQuery()
                ->getSingleScalarResult();
        }

        return $count;
    }

    /**
     * Returns the parts associated with this element and all its children. The parts are ordered depth-first by
     * element (children ordered by name), and by name inside each element, like getParts() does.
     * @return Part[]
     */
    public function getPartsRecursive(AbstractPartsContainingDBElement $element): array
    {
        $elements = $this->getSubtree($element);

        $field_name = $this->getPartsFieldName();
        if ($field_name === null) {
            return array_merge(...array_map(fn ($e) => $this->getParts($e), $elements));
        }

        $parts_by_element = [];
        foreach (array_chunk($elements, 1000) as $chunk) {
            $rows = $this->getEntityManager()->createQueryBuilder()
                ->select('part', 'IDENTITY(part.'.$field_name.') AS element_id')
                ->from(Part::class, 'part')
                ->where('part.'.$field_name.' IN (:elements)')
                ->setParameter('elements', $chunk)
                ->orderBy('NATSORT(part.name)', 'ASC')
                ->getQuery()
                ->getResult();
            foreach ($rows as $row) {
                $parts_by_element[$row['element_id']][] = $row[0];
            }
        }

        $parts = [];
        foreach ($elements as $e) {
            array_push($parts, ...($parts_by_element[$e->getID()] ?? []));
        }

        return $parts;
    }

    protected function getPartsByField(object $element, string $nameOrderDirection, string $field_name): array
    {
        if (!$element instanceof AbstractPartsContainingDBElement) {
            throw new InvalidArgumentException('$element must be an instance of AbstractPartContainingDBElement!');
        }

        $repo = $this->getEntityManager()->getRepository(Part::class);

        //Build a query builder to get the parts with a custom order by

        $qb = $repo->createQueryBuilder('part')
            ->where('part.'.$field_name.' = :element')
            ->setParameter('element', $element)
            ->orderBy('NATSORT(part.name)', $nameOrderDirection);

        return $qb->getQuery()->getResult();
    }

    protected function getPartsCountByField(object $element, string $field_name): int
    {
        if (!$element instanceof AbstractPartsContainingDBElement) {
            throw new InvalidArgumentException('$element must be an instance of AbstractPartContainingDBElement!');
        }

        $repo = $this->getEntityManager()->getRepository(Part::class);

        return $repo->count([$field_name => $element]);
    }
}
