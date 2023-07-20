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

use App\Entity\Base\AbstractDBElement;
use Doctrine\ORM\EntityRepository;
use ReflectionClass;

/**
 * @template TEntityClass of AbstractDBElement
 * @extends EntityRepository<TEntityClass>
 */
class DBElementRepository extends EntityRepository
{
    private array $find_elements_by_id_cache = [];

    /**
     * Changes the ID of the given element to a new value.
     * You should only use it to undelete former existing elements, everything else is most likely a bad idea!
     *
     * @param AbstractDBElement $element The element whose ID should be changed
     * @phpstan-param TEntityClass $element
     * @param int     $new_id  The new ID
     */
    public function changeID(AbstractDBElement $element, int $new_id): void
    {
        $qb = $this->createQueryBuilder('element');
        $q = $qb->update($element::class, 'element')
            ->set('element.id', $new_id)
            ->where('element.id = ?1')
            ->setParameter(1, $element->getID())
            ->getQuery();

        //Do the renaming
        $q->execute();

        $this->setField($element, 'id', $new_id);
    }

    /**
     * Find all elements that match a list of IDs.
     *
     * @return AbstractDBElement[]
     * @phpstan-return list<TEntityClass>
     */
    public function getElementsFromIDArray(array $ids): array
    {
        $qb = $this->createQueryBuilder('element');
        $q = $qb->select('element')
            ->where('element.id IN (?1)')
            ->setParameter(1, $ids)
            ->getQuery();

        return $q->getResult();
    }

    /**
     * Returns the elements with the given IDs in the same order, as they were given in the input array.
     *
     * @param  array  $ids
     * @return array
     */
    public function findByIDInMatchingOrder(array $ids): array
    {
        $cache_key = implode(',', $ids);

        //Check if the result is already cached
        if (isset($this->find_elements_by_id_cache[$cache_key])) {
            return $this->find_elements_by_id_cache[$cache_key];
        }

        //Otherwise do the query
        $qb = $this->createQueryBuilder('element');
        $q = $qb->select('element')
            ->where('element.id IN (?1)')
            ->setParameter(1, $ids)
            ->getQuery();

        $result = $q->getResult();
        $result = array_combine($ids, $result);
        $result = array_map(fn ($id) => $result[$id], $ids);

        //Cache the result
        $this->find_elements_by_id_cache[$cache_key] = $result;

        return $result;
    }

    protected function setField(AbstractDBElement $element, string $field, int $new_value): void
    {
        $reflection = new ReflectionClass($element::class);
        $property = $reflection->getProperty($field);
        $property->setAccessible(true);
        $property->setValue($element, $new_value);
    }
}
