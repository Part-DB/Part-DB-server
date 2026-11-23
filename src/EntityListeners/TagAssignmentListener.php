<?php
/**
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 * Copyright (C) 2019 - 2026 Jan Böhmer (https://github.com/jbtronics)
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

namespace App\EntityListeners;

use App\Entity\Parts\Part;
use App\Entity\Parts\Tag;
use App\Repository\Parts\TagRepository;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Events;
use Doctrine\ORM\PersistentCollection;
use Doctrine\ORM\UnitOfWork;

/**
 * Makes parts refer to the existing tag of a name, when tags are assigned by their names.
 *
 * Part::setTags() can not look up tags, as entities have no access to the database. It adds a new Tag object for every
 * name the part does not have a tag for yet. Before these are written, this listener replaces each of them with the
 * tag of that name which already exists (compared case-insensitively), or with the first new tag of that name in the
 * same flush, so two parts getting the same new tag at once share it.
 *
 * This has to run before the EventLoggerListener (hence the priority), so the history does not record tags which are
 * never written, and sees the parts whose tags changed as edited.
 */
#[AsDoctrineListener(event: Events::onFlush, priority: 10)]
final class TagAssignmentListener
{
    public function onFlush(OnFlushEventArgs $event): void
    {
        $em = $event->getObjectManager();
        $uow = $em->getUnitOfWork();

        $this->replaceDuplicateTags($em, $uow);
        $this->markPartsWithChangedTags($em, $uow);
    }

    private function replaceDuplicateTags(EntityManagerInterface $em, UnitOfWork $uow): void
    {
        /** @var array<int, Tag> $newTags */
        $newTags = [];
        foreach ($uow->getScheduledEntityInsertions() as $entity) {
            if ($entity instanceof Tag) {
                $newTags[spl_object_id($entity)] = $entity;
            }
        }
        if ($newTags === []) {
            return;
        }

        /** @var TagRepository $repo */
        $repo = $em->getRepository(Tag::class);

        /** @var array<string, Tag> $canonical The tag which stands for a name, by its name key */
        $canonical = [];
        /** @var array<int, Tag> $replacements The tag each duplicate is replaced with, by the object id of the duplicate */
        $replacements = [];
        foreach ($newTags as $oid => $tag) {
            $key = Tag::nameKey($tag->getName());
            $canonical[$key] ??= $repo->findOneByName($tag->getName()) ?? $tag;
            if ($canonical[$key] !== $tag) {
                $replacements[$oid] = $canonical[$key];
            }
        }
        if ($replacements === []) {
            return;
        }

        foreach ($this->getWrittenTagCollections($uow) as $collection) {
            foreach ($collection->toArray() as $tag) {
                $replacement = $replacements[spl_object_id($tag)] ?? null;
                if ($replacement === null) {
                    continue;
                }
                $collection->removeElement($tag);
                if (!$collection->contains($replacement)) {
                    $collection->add($replacement);
                }
            }
        }

        foreach (array_keys($replacements) as $oid) {
            $uow->detach($newTags[$oid]);
        }
    }

    /**
     * Changing only the tags of a part is not a change of the part for doctrine, as they are stored in a table of
     * their own. As tags used to be a field of the part, such a part is scheduled for an update here, so its last
     * modification date is updated and the history records the change, like before.
     */
    private function markPartsWithChangedTags(EntityManagerInterface $em, UnitOfWork $uow): void
    {
        foreach ($this->getWrittenTagCollections($uow) as $collection) {
            $part = $collection->getOwner();
            if (!$part instanceof Part || $uow->isScheduledForInsert($part) || $uow->isScheduledForUpdate($part)
                || $uow->isScheduledForDelete($part) || !self::hasChanged($collection)) {
                continue;
            }

            $part->updateTimestamps();
            $uow->recomputeSingleEntityChangeSet($em->getClassMetadata(Part::class), $part);
        }
    }

    /**
     * Checks if a tag collection contains other tags than when it was loaded.
     * @template TKey of array-key
     * @template T of object
     * @param  PersistentCollection<TKey, T>  $collection
     */
    public static function hasChanged(PersistentCollection $collection): bool
    {
        $before = array_map(spl_object_id(...), $collection->getSnapshot());
        $after = array_map(spl_object_id(...), $collection->toArray());
        sort($before);
        sort($after);

        return $before !== $after;
    }

    /**
     * Returns the tag collections of the parts, which are written in this flush. A new tag can only be referenced from
     * these, as it is scheduled for insertion by cascading from such a collection.
     * @return iterable<PersistentCollection<array-key, object>>
     */
    private function getWrittenTagCollections(UnitOfWork $uow): iterable
    {
        foreach ($uow->getScheduledCollectionUpdates() as $collection) {
            if ($collection->getOwner() instanceof Part && $collection->getMapping()->fieldName === 'tagEntities') {
                yield $collection;
            }
        }
    }
}
