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
            ->where('attachment.path LIKE :like');
        $qb->setParameter('like', '\\%SECURE\\%%');
        $query = $qb->getQuery();

        return (int) $query->getSingleScalarResult();
    }

    /**
     * Gets the count of all external attachments (attachments only containing a URL).
     *
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    public function getExternalAttachments(): int
    {
        $qb = $this->createQueryBuilder('attachment');
        $qb->select('COUNT(attachment)')
            ->where('ILIKE(attachment.path, :http) = TRUE')
            ->orWhere('ILIKE(attachment.path, :https) = TRUE');
        $qb->setParameter('http', 'http://%');
        $qb->setParameter('https', 'https://%');
        $query = $qb->getQuery();

        return (int) $query->getSingleScalarResult();
    }

    /**
     * Gets the count of all attachments where a user uploaded a file.
     *
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    public function getUserUploadedAttachments(): int
    {
        $qb = $this->createQueryBuilder('attachment');
        $qb->select('COUNT(attachment)')
            ->where('attachment.path LIKE :base')
            ->orWhere('attachment.path LIKE :media')
            ->orWhere('attachment.path LIKE :secure');
        $qb->setParameter('secure', '\\%SECURE\\%%');
        $qb->setParameter('base', '\\%BASE\\%%');
        $qb->setParameter('media', '\\%MEDIA\\%%');
        $query = $qb->getQuery();

        return (int) $query->getSingleScalarResult();
    }
}
