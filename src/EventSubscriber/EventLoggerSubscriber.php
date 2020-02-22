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

namespace App\EventSubscriber;

use App\Entity\Base\AbstractDBElement;
use App\Entity\LogSystem\AbstractLogEntry;
use App\Entity\LogSystem\ElementCreatedLogEntry;
use App\Entity\LogSystem\ElementDeletedLogEntry;
use App\Entity\LogSystem\ElementEditedLogEntry;
use App\Services\LogSystem\EventLogger;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;

class EventLoggerSubscriber implements EventSubscriber
{
    protected $logger;

    public function __construct(EventLogger $logger)
    {
        $this->logger = $logger;
    }

    public function onFlush(OnFlushEventArgs $eventArgs)
    {
        $em = $eventArgs->getEntityManager();
        $uow = $em->getUnitOfWork();

        /*
         * Log changes and deletions of entites.
         * We can not log persist here, because the entities do not have IDs yet...
         */

        foreach ($uow->getScheduledEntityUpdates() as $entity) {
            if ($this->validEntity($entity)) {
                $log = new ElementEditedLogEntry($entity);
                $this->logger->log($log);
            }
        }

        foreach ($uow->getScheduledEntityDeletions() as $entity) {
            if ($this->validEntity($entity)) {
                $log = new ElementDeletedLogEntry($entity);
                $this->logger->log($log);
            }
        }


        $uow->computeChangeSets();
    }

    public function postPersist(LifecycleEventArgs $args)
    {
        //Create an log entry

        /** @var AbstractDBElement $entity */
        $entity = $args->getObject();
        if ($this->validEntity($entity)) {
            $log = new ElementCreatedLogEntry($entity);
            $this->logger->log($log);
        }
    }

    public function postFlush(PostFlushEventArgs $args)
    {
        $em = $args->getEntityManager();
        $uow = $em->getUnitOfWork();
        // If the we have added any ElementCreatedLogEntries added in postPersist, we flush them here.
        if ($uow->hasPendingInsertions()) {
            $em->flush();
        }
    }

    /**
     * Check if the given entity can be logged.
     * @param object $entity
     * @return bool True, if the given entity can be logged.
     */
    protected function validEntity(object $entity): bool
    {
        //Dont log logentries itself!
        if ($entity instanceof AbstractDBElement && !$entity instanceof AbstractLogEntry) {
            return true;
        }

        return false;
    }

    /**
     * @inheritDoc
     */
    public function getSubscribedEvents()
    {
        return[
            Events::onFlush,
            Events::postPersist,
            Events::postFlush
        ];
    }
}