<?php
/**
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 * Copyright (C) 2019 - 2020 Jan Böhmer (https://github.com/jbtronics)
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA
 */

namespace App\Repository;


use Doctrine\ORM\EntityRepository;

class AttachmentRepository extends EntityRepository
{
    /**
     * Gets the count of all private/secure attachments.
     * @return int
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
     * Gets the count of all external attachments (attachments only containing an URL)
     * @return int
     * @throws \Doctrine\ORM\NoResultException
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function getExternalAttachments(): int
    {
        $qb = $this->createQueryBuilder('attachment');
        $qb->select('COUNT(attachment)')
            ->where('attachment.path LIKE :http')
            ->orWhere('attachment.path LIKE :https');
        $qb->setParameter('http', 'http://%');
        $qb->setParameter('https', 'https://%');
        $query = $qb->getQuery();
        return (int) $query->getSingleScalarResult();
    }

    /**
     * Gets the count of all attachments where an user uploaded an file.
     * @return int
     * @throws \Doctrine\ORM\NoResultException
     * @throws \Doctrine\ORM\NonUniqueResultException
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