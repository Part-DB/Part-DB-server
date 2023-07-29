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

use App\Doctrine\Helpers\FieldHelper;
use App\Entity\Base\AbstractDBElement;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
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
        //If no IDs are given, return an empty array
        if (count($ids) === 0) {
            return [];
        }

        $cache_key = implode(',', $ids);

        //Check if the result is already cached
        if (isset($this->find_elements_by_id_cache[$cache_key])) {
            return $this->find_elements_by_id_cache[$cache_key];
        }

        //Otherwise do the query
        $qb = $this->createQueryBuilder('element');
        $qb->select('element')
            ->where('element.id IN (?1)')
            ->setParameter(1, $ids);

        //Order the results in the same order as the IDs in the input array
        FieldHelper::addOrderByFieldParam($qb, 'element.id', 1);

        $q = $qb->getQuery();

        $result = $q->getResult();

        //Cache the result
        $this->find_elements_by_id_cache[$cache_key] = $result;

        return $result;
    }

    /**
     * The elements in the result array will be sorted, so that their order of their IDs matches the order of the IDs in the input array.
     * @param  array  $result_array
     * @phpstan-param list<TEntityClass> $result_array
     * @param  int[]  $ids
     * @return void
     */
    protected function sortResultArrayByIDArray(array &$result_array, array $ids): void
    {
        usort($result_array, static function (AbstractDBElement $a, AbstractDBElement $b) use ($ids) {
            return array_search($a->getID(), $ids, true) <=> array_search($b->getID(), $ids, true);
        });
    }

    protected function setField(AbstractDBElement $element, string $field, int $new_value): void
    {
        $reflection = new ReflectionClass($element::class);
        $property = $reflection->getProperty($field);
        $property->setAccessible(true);
        $property->setValue($element, $new_value);
    }
}
