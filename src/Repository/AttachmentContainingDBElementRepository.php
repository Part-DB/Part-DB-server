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

use App\Entity\Attachments\AttachmentContainingDBElement;
use Doctrine\ORM\Mapping\ClassMetadataInfo;

/**
 * @template TEntityClass of AttachmentContainingDBElement
 * @extends NamedDBElementRepository<TEntityClass>
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
        //Convert the ids to a string
        $cache_key = implode(',', $ids);

        //Check if the result is already cached
        if (isset($this->elementsAndPreviewAttachmentCache[$cache_key])) {
            return $this->elementsAndPreviewAttachmentCache[$cache_key];
        }

        $qb = $this->createQueryBuilder('element');
        $q = $qb->select('element')
            ->where('element.id IN (?1)')
            ->setParameter(1, $ids)
            ->getQuery();

        $q->setFetchMode($this->getEntityName(), 'master_picture_attachment', ClassMetadataInfo::FETCH_EAGER);

        $result = $q->getResult();
        $result = array_combine($ids, $result);
        $result = array_map(fn ($id) => $result[$id], $ids);

        //Cache the result
        $this->elementsAndPreviewAttachmentCache[$cache_key] = $result;

        return $result;
    }
}