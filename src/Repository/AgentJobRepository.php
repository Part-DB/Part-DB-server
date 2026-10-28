<?php
/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2026 Jan Böhmer (https://github.com/jbtronics)
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

use App\Entity\AgentSystem\AgentJob;
use App\Entity\AgentSystem\AgentJobStatus;
use Doctrine\ORM\EntityRepository;

/**
 * @template TEntityClass of AgentJob
 * @extends EntityRepository<TEntityClass>
 */
class AgentJobRepository extends EntityRepository
{
    /**
     * Atomically moves a pending job to claimed. Returns false if another request claimed (or cancelled) it first.
     */
    public function claim(AgentJob $job): bool
    {
        $now = new \DateTimeImmutable();
        $claimed = $this->createQueryBuilder('j')
            ->update()
            ->set('j.status', ':claimed')
            ->set('j.updatedAt', ':now')
            ->where('j.id = :id')
            ->andWhere('j.status = :pending')
            ->setParameter('claimed', AgentJobStatus::CLAIMED)
            ->setParameter('pending', AgentJobStatus::PENDING)
            ->setParameter('now', $now, 'datetime_immutable')
            ->setParameter('id', $job->getId())
            ->getQuery()
            ->execute() === 1;
        if ($claimed) {
            $job->setStatus(AgentJobStatus::CLAIMED);
            $job->setUpdatedAt($now);
        }
        return $claimed;
    }

    /**
     * Deletes finished jobs last updated before the given time.
     */
    public function pruneFinished(\DateTimeImmutable $before): int
    {
        return $this->createQueryBuilder('j')
            ->delete()
            ->where('j.status IN (:finished)')
            ->andWhere('j.updatedAt < :before')
            ->setParameter('finished', [AgentJobStatus::DONE, AgentJobStatus::FAILED, AgentJobStatus::CANCELLED])
            ->setParameter('before', $before, 'datetime_immutable')
            ->getQuery()
            ->execute();
    }
}
