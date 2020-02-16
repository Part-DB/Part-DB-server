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
use Doctrine\ORM\UnitOfWork;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Symfony\Component\Serializer\SerializerInterface;

class EventLoggerSubscriber implements EventSubscriber
{
    protected $logger;
    protected $serializer;

    public function __construct(EventLogger $logger, SerializerInterface $serializer)
    {
        $this->logger = $logger;
        $this->serializer = $serializer;
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
                $this->saveChangeSet($entity, $log, $uow);
                $this->logger->log($log);
            }
        }

        foreach ($uow->getScheduledEntityDeletions() as $entity) {
            if ($this->validEntity($entity)) {
                $log = new ElementDeletedLogEntry($entity);
                $this->saveChangeSet($entity, $log, $uow);
                $this->logger->log($log);
            }
        }


        $uow->computeChangeSets();
    }

    protected function saveChangeSet(AbstractDBElement $entity, AbstractLogEntry $logEntry, UnitOfWork $uow): void
    {
        if (!$logEntry instanceof ElementEditedLogEntry && !$logEntry instanceof ElementDeletedLogEntry) {
            throw new \InvalidArgumentException('$logEntry must be ElementEditedLogEntry or ElementDeletedLogEntry!');
        }

        $changeSet = $uow->getEntityChangeSet($entity);
        dump($changeSet);
        $old_data = array_diff(array_combine(array_keys($changeSet), array_column($changeSet, 0)), [null]);
        $logEntry->setOldData($old_data);
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