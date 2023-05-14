<?php
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

declare(strict_types=1);

namespace App\EventSubscriber\LogSystem;

use App\Entity\Attachments\Attachment;
use App\Entity\Base\AbstractDBElement;
use App\Entity\LogSystem\AbstractLogEntry;
use App\Entity\LogSystem\CollectionElementDeleted;
use App\Entity\LogSystem\ElementCreatedLogEntry;
use App\Entity\LogSystem\ElementDeletedLogEntry;
use App\Entity\LogSystem\ElementEditedLogEntry;
use App\Entity\Parameters\AbstractParameter;
use App\Entity\Parts\PartLot;
use App\Entity\PriceInformations\Orderdetail;
use App\Entity\PriceInformations\Pricedetail;
use App\Entity\UserSystem\User;
use App\Services\LogSystem\EventCommentHelper;
use App\Services\LogSystem\EventLogger;
use App\Services\LogSystem\EventUndoHelper;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * This event subscriber writes to the event log when entities are changed, removed, created.
 */
class EventLoggerSubscriber implements EventSubscriber
{
    /**
     * @var array The given fields will not be saved, because they contain sensitive information
     */
    protected const FIELD_BLACKLIST = [
        User::class => ['password', 'need_pw_change', 'googleAuthenticatorSecret', 'backupCodes', 'trustedDeviceCookieVersion', 'pw_reset_token', 'backupCodesGenerationDate'],
    ];

    /**
     * @var array If elements of the given class are deleted, a log for the given fields will be triggered
     */
    protected const TRIGGER_ASSOCIATION_LOG_WHITELIST = [
        PartLot::class => ['part'],
        Orderdetail::class => ['part'],
        Pricedetail::class => ['orderdetail'],
        Attachment::class => ['element'],
        AbstractParameter::class => ['element'],
    ];

    protected const MAX_STRING_LENGTH = 2000;

    protected EventLogger $logger;
    protected SerializerInterface $serializer;
    protected EventCommentHelper $eventCommentHelper;
    protected EventUndoHelper $eventUndoHelper;
    protected bool $save_changed_fields;
    protected bool $save_changed_data;
    protected bool $save_removed_data;
    protected bool $save_new_data;
    protected PropertyAccessorInterface $propertyAccessor;

    public function __construct(EventLogger $logger, SerializerInterface $serializer, EventCommentHelper $commentHelper,
        bool $save_changed_fields, bool $save_changed_data, bool $save_removed_data, bool $save_new_data,
        PropertyAccessorInterface $propertyAccessor, EventUndoHelper $eventUndoHelper)
    {
        $this->logger = $logger;
        $this->serializer = $serializer;
        $this->eventCommentHelper = $commentHelper;
        $this->propertyAccessor = $propertyAccessor;
        $this->eventUndoHelper = $eventUndoHelper;

        $this->save_changed_fields = $save_changed_fields;
        $this->save_changed_data = $save_changed_data;
        $this->save_removed_data = $save_removed_data;
        //This option only makes sense if save_changed_data is true
        $this->save_new_data = $save_new_data && $save_changed_data;
    }

    public function onFlush(OnFlushEventArgs $eventArgs): void
    {
        $em = $eventArgs->getObjectManager();
        $uow = $em->getUnitOfWork();

        /*
         * Log changes and deletions of entites.
         * We can not log persist here, because the entities do not have IDs yet...
         */

        foreach ($uow->getScheduledEntityUpdates() as $entity) {
            if ($this->validEntity($entity)) {
                $this->logElementEdited($entity, $em);
            }
        }

        foreach ($uow->getScheduledEntityDeletions() as $entity) {
            if ($this->validEntity($entity)) {
                $this->logElementDeleted($entity, $em);
            }
        }

        /* Do not call $uow->computeChangeSets() in this function, only individual entities should be computed!
         * Otherwise we will run into very strange issues, that some entity changes are no longer updated!
         * This is almost impossible to debug, because it only happens in some cases, and it looks very unrelated to
         * this code (which caused the problem and which took me very long time to find out).
         * So just do not call $uow->computeChangeSets() here ever, even if it is tempting!!
         * If you need to log something from inside here, just call logFromOnFlush() instead of the normal log() function.
         */
    }

    public function postPersist(LifecycleEventArgs $args): void
    {
        //Create a log entry, we have to do this post persist, because we have to know the ID

        /** @var AbstractDBElement $entity */
        $entity = $args->getObject();
        if ($this->validEntity($entity)) {
            $log = new ElementCreatedLogEntry($entity);
            //Add user comment to log entry
            if ($this->eventCommentHelper->isMessageSet()) {
                $log->setComment($this->eventCommentHelper->getMessage());
            }
            if ($this->eventUndoHelper->isUndo()) {
                $undoEvent = $this->eventUndoHelper->getUndoneEvent();

                $log->setUndoneEvent($undoEvent, $this->eventUndoHelper->getMode());

                if ($undoEvent instanceof ElementDeletedLogEntry && $undoEvent->getTargetClass() === $log->getTargetClass()) {
                    $log->setTargetElementID($undoEvent->getTargetID());
                }
                if ($undoEvent instanceof CollectionElementDeleted && $undoEvent->getDeletedElementClass() === $log->getTargetClass()) {
                    $log->setTargetElementID($undoEvent->getDeletedElementID());
                }
            }

            $this->logger->log($log);
        }
    }

    public function postFlush(PostFlushEventArgs $args): void
    {
        $em = $args->getObjectManager();
        $uow = $em->getUnitOfWork();
        // If we have added any ElementCreatedLogEntries added in postPersist, we flush them here.
        $uow->computeChangeSets();
        if ($uow->hasPendingInsertions() || !empty($uow->getScheduledEntityUpdates())) {
            $em->flush();
        }

        //Clear the message provided by user.
        $this->eventCommentHelper->clearMessage();
        $this->eventUndoHelper->clearUndoneEvent();
    }

    /**
     * Check if the given element class has restrictions to its fields.
     *
     * @return bool True if there are restrictions, and further checking is needed
     */
    public function hasFieldRestrictions(AbstractDBElement $element): bool
    {
        foreach (array_keys(static::FIELD_BLACKLIST) as $class) {
            if (is_a($element, $class)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Checks if the field of the given element should be saved (if it is not blacklisted).
     */
    public function shouldFieldBeSaved(AbstractDBElement $element, string $field_name): bool
    {
        foreach (static::FIELD_BLACKLIST as $class => $blacklist) {
            if (is_a($element, $class) && in_array($field_name, $blacklist, true)) {
                return false;
            }
        }

        //By default, allow every field.
        return true;
    }

    public function getSubscribedEvents(): array
    {
        return[
            Events::onFlush,
            Events::postPersist,
            Events::postFlush,
        ];
    }

    protected function logElementDeleted(AbstractDBElement $entity, EntityManagerInterface $em): void
    {
        $log = new ElementDeletedLogEntry($entity);
        //Add user comment to log entry
        if ($this->eventCommentHelper->isMessageSet()) {
            $log->setComment($this->eventCommentHelper->getMessage());
        }
        if ($this->eventUndoHelper->isUndo()) {
            $log->setUndoneEvent($this->eventUndoHelper->getUndoneEvent(), $this->eventUndoHelper->getMode());
        }
        if ($this->save_removed_data) {
            //The 4th param is important here, as we delete the element...
            $this->saveChangeSet($entity, $log, $em, true);
        }
        $this->logger->logFromOnFlush($log);

        //Check if we have to log CollectionElementDeleted entries
        if ($this->save_changed_data) {
            $metadata = $em->getClassMetadata(get_class($entity));
            $mappings = $metadata->getAssociationMappings();
            //Check if class is whitelisted for CollectionElementDeleted entry
            foreach (static::TRIGGER_ASSOCIATION_LOG_WHITELIST as $class => $whitelist) {
                if (is_a($entity, $class)) {
                    //Check names
                    foreach ($mappings as $field => $mapping) {
                        if (in_array($field, $whitelist, true)) {
                            $changed = $this->propertyAccessor->getValue($entity, $field);
                            $log = new CollectionElementDeleted($changed, $mapping['inversedBy'], $entity);
                            if ($this->eventUndoHelper->isUndo()) {
                                $log->setUndoneEvent($this->eventUndoHelper->getUndoneEvent(), $this->eventUndoHelper->getMode());
                            }
                            $this->logger->logFromOnFlush($log);
                        }
                    }
                }
            }
        }
    }

    protected function logElementEdited(AbstractDBElement $entity, EntityManagerInterface $em): void
    {
        $uow = $em->getUnitOfWork();

        /* We have to call that here again, so the foreign entity timestamps, that were changed in updateTimestamp
           get persisted */
        $changeSet = $uow->getEntityChangeSet($entity);

        //Skip log entry, if only the lastModified field has changed...
        if (isset($changeSet['lastModified']) && count($changeSet)) {
            return;
        }

        $log = new ElementEditedLogEntry($entity);
        if ($this->save_changed_data) {
            $this->saveChangeSet($entity, $log, $em);
        } elseif ($this->save_changed_fields) {
            $changed_fields = array_keys($uow->getEntityChangeSet($entity));
            //Remove lastModified field, as this is always changed (gives us no additional info)
            $changed_fields = array_diff($changed_fields, ['lastModified']);
            $log->setChangedFields($changed_fields);
        }
        //Add user comment to log entry
        if ($this->eventCommentHelper->isMessageSet()) {
            $log->setComment($this->eventCommentHelper->getMessage());
        }
        if ($this->eventUndoHelper->isUndo()) {
            $log->setUndoneEvent($this->eventUndoHelper->getUndoneEvent(), $this->eventUndoHelper->getMode());
        }
        $this->logger->logFromOnFlush($log);
    }

    /**
     * Filter out every forbidden field and return the cleaned array.
     */
    protected function filterFieldRestrictions(AbstractDBElement $element, array $fields): array
    {
        unset($fields['lastModified']);

        if (!$this->hasFieldRestrictions($element)) {
            return $fields;
        }

        return array_filter($fields, function ($value, $key) use ($element) {
            //Associative array (save changed data) case
            if (is_string($key)) {
                return $this->shouldFieldBeSaved($element, $key);
            }

            return $this->shouldFieldBeSaved($element, $value);
        }, ARRAY_FILTER_USE_BOTH);
    }

    /**
     * Restrict the length of every string in the given array to MAX_STRING_LENGTH, to save memory in the case of very
     * long strings (e.g. images in notes)
     * @param  array  $fields
     * @return array
     */
    protected function fieldLengthRestrict(array $fields): array
    {
        return array_map(
            static function ($value) {
                if (is_string($value)) {
                    return mb_strimwidth($value, 0, self::MAX_STRING_LENGTH, '...');
                }

                return $value;
            }, $fields);
    }

    protected function saveChangeSet(AbstractDBElement $entity, AbstractLogEntry $logEntry, EntityManagerInterface $em, bool $element_deleted = false): void
    {
        $uow = $em->getUnitOfWork();

        if (!$logEntry instanceof ElementEditedLogEntry && !$logEntry instanceof ElementDeletedLogEntry) {
            throw new \InvalidArgumentException('$logEntry must be ElementEditedLogEntry or ElementDeletedLogEntry!');
        }

        if ($element_deleted) { //If the element was deleted we can use getOriginalData to save its content
            $old_data = $uow->getOriginalEntityData($entity);
        } else { //Otherwise we have to get it from entity changeset
            $changeSet = $uow->getEntityChangeSet($entity);
            $old_data = array_combine(array_keys($changeSet), array_column($changeSet, 0));
            //If save_new_data is enabled, we extract it from the change set
            if ($this->save_new_data) {
                $new_data = array_combine(array_keys($changeSet), array_column($changeSet, 1));
            }
        }
        $old_data = $this->filterFieldRestrictions($entity, $old_data);

        //Restrict length of string fields, to save memory...
        $old_data = $this->fieldLengthRestrict($old_data);

        $logEntry->setOldData($old_data);

        if (!empty($new_data)) {
            $new_data = $this->filterFieldRestrictions($entity, $new_data);
            $new_data = $this->fieldLengthRestrict($new_data);

            $logEntry->setNewData($new_data);
        }
    }

    /**
     * Check if the given entity can be logged.
     *
     * @return bool true, if the given entity can be logged
     */
    protected function validEntity(object $entity): bool
    {
        //Dont log logentries itself!
        return $entity instanceof AbstractDBElement && !$entity instanceof AbstractLogEntry;
    }
}
