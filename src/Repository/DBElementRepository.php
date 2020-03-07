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

namespace App\Repository;


use App\Entity\Base\AbstractDBElement;
use Doctrine\ORM\EntityRepository;

class DBElementRepository extends EntityRepository
{
    /**
     * Changes the ID of the given element to a new value.
     * You should only use it to undelete former existing elements, everything else is most likely a bad idea!
     * @param  AbstractDBElement  $element The element whose ID should be changed
     * @param  int  $new_id The new ID
     */
    public function changeID(AbstractDBElement $element, int $new_id): void
    {
        $qb = $this->createQueryBuilder('element');
        $q = $qb->update(get_class($element), 'element')
            ->set('element.id', $new_id)
            ->where('element.id = ?1')
            ->setParameter(1, $element->getID())
            ->getQuery();

        $p = $q->execute();

        $this->setField($element, 'id', $new_id);
    }

    protected function setField(AbstractDBElement $element, string $field, $new_value)
    {
        $reflection = new \ReflectionClass(get_class($element));
        $property = $reflection->getProperty($field);
        $property->setAccessible(true);
        $property->setValue($element, $new_value);
    }
}