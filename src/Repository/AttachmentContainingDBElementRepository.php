<?php
/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2023 Jan Böhmer (https://github.com/jbtronics)
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


namespace App\Repository;

use App\Doctrine\Helpers\FieldHelper;
use App\Entity\Attachments\AttachmentContainingDBElement;
use Doctrine\ORM\Mapping\ClassMetadata;

/**
 * @template TEntityClass of AttachmentContainingDBElement
 * @extends NamedDBElementRepository<TEntityClass>
 * @see \App\Tests\Repository\AttachmentContainingDBElementRepositoryTest
 */
class AttachmentContainingDBElementRepository extends NamedDBElementRepository
{
    /**
     * @var array This array is used to cache the results of getElementsAndPreviewAttachmentByIDs function.
     */
    private array $elementsAndPreviewAttachmentCache = [];

    /**
     * Similar to the findByIDInMatchingOrder function, but it also hints to doctrine that the master picture attachment should be fetched eagerly.
     * @param  array  $ids
     * @return array
     * @phpstan-return array<int, TEntityClass>
     */
    public function getElementsAndPreviewAttachmentByIDs(array $ids): array
    {
        //If no IDs are given, return an empty array
        if (count($ids) === 0) {
            return [];
        }

        //Convert the ids to a string
        $cache_key = implode(',', $ids);

        //Check if the result is already cached
        if (isset($this->elementsAndPreviewAttachmentCache[$cache_key])) {
            return $this->elementsAndPreviewAttachmentCache[$cache_key];
        }

        $qb = $this->createQueryBuilder('element')
            ->select('element')
            ->where('element.id IN (?1)')
            //Order the results in the same order as the IDs in the input array (mysql supports this native, for SQLite we emulate it)
            ->setParameter(1, $ids);

        //Order the results in the same order as the IDs in the input array
        FieldHelper::addOrderByFieldParam($qb, 'element.id', 1);

        $q = $qb->getQuery();

        $q->setFetchMode($this->getEntityName(), 'master_picture_attachment', ClassMetadata::FETCH_EAGER);

        $result = $q->getResult();

        //Cache the result
        $this->elementsAndPreviewAttachmentCache[$cache_key] = $result;

        return $result;
    }
}