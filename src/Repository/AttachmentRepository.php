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

use App\Entity\Attachments\Attachment;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;

/**
 * @template TEntityClass of Attachment
 * @extends DBElementRepository<TEntityClass>
 */
class AttachmentRepository extends DBElementRepository
{
    /**
     * Gets the count of all private/secure attachments.
     */
    public function getPrivateAttachmentsCount(): int
    {
        $qb = $this->createQueryBuilder('attachment');
        $qb->select('COUNT(attachment)')
            ->where('attachment.internal_path LIKE :like ESCAPE \'#\'');
        $qb->setParameter('like', '#%SECURE#%%');
        $query = $qb->getQuery();

        return (int) $query->getSingleScalarResult();
    }

    /**
     * Gets the count of all external attachments (attachments containing only an external path).
     *
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    public function getExternalAttachments(): int
    {
        $qb = $this->createQueryBuilder('attachment');
        $qb->select('COUNT(attachment)')
            ->andWhere('attachment.internal_path IS NULL')
            ->where('attachment.external_path IS NOT NULL');
        $query = $qb->getQuery();

        return (int) $query->getSingleScalarResult();
    }

    /**
     * Gets the count of all attachments where a user uploaded a file or a file was downloaded from an external source.
     *
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    public function getUserUploadedAttachments(): int
    {
        $qb = $this->createQueryBuilder('attachment');
        $qb->select('COUNT(attachment)')
            ->where('attachment.internal_path LIKE :base ESCAPE \'#\'')
            ->orWhere('attachment.internal_path LIKE :media ESCAPE \'#\'')
            ->orWhere('attachment.internal_path LIKE :secure ESCAPE \'#\'');
        $qb->setParameter('secure', '#%SECURE#%%');
        $qb->setParameter('base', '#%BASE#%%');
        $qb->setParameter('media', '#%MEDIA#%%');
        $query = $qb->getQuery();

        return (int) $query->getSingleScalarResult();
    }
}
