<?php
/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2025 Jan Böhmer (https://github.com/jbtronics)
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


namespace App\EntityListeners;

use App\Entity\Parts\Part;
use App\Entity\ProjectSystem\ProjectBOMEntry;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Events;
use Doctrine\ORM\Event\OnFlushEventArgs;

/**
 * If a part is deleted, this listener makes sure that all ProjectBOMEntries that reference this part, are updated
 * to not reference the part anymore, but instead store the part name in the name field.
 *
 * This has to be done on the onFlush event, and must skip BOM entries that are themselves already scheduled for
 * deletion (e.g. because their Project is being removed in the same flush, which cascade-removes them too) -
 * not, as one might expect, unconditionally null the reference from a preRemove entity listener. Doctrine's
 * UnitOfWork::computeDeleteExecutionOrder() decides the DELETE statement order by reading each entity's
 * *current* in-memory association value: if a BOM entry is scheduled for deletion together with its part and
 * a preRemove listener already nulled bom_entry->part beforehand, Doctrine no longer sees any dependency
 * between the two and may delete the "parts" row before the "project_bom_entries" row - which still has its
 * old id_part value in the database, since an entity scheduled for deletion never gets an UPDATE - causing a
 * foreign key violation. Entries that are *not* also being deleted still need to be unlinked here, and since
 * onFlush fires after the main changeset computation, recomputeSingleEntityChangeSet() is required for that
 * mutation to actually be persisted.
 */
#[AsDoctrineListener(event: Events::onFlush)]
class PartProjectBOMEntryUnlinkListener
{
    public function onFlush(OnFlushEventArgs $event): void
    {
        $em = $event->getObjectManager();
        $uow = $em->getUnitOfWork();

        foreach ($uow->getScheduledEntityDeletions() as $entity) {
            if (!$entity instanceof Part) {
                continue;
            }

            // Iterate over all ProjectBOMEntries that use this part and put the part name into the name field
            foreach ($entity->getProjectBomEntries() as $bom_entry) {
                if ($uow->isScheduledForDelete($bom_entry)) {
                    continue;
                }

                $old_name = $bom_entry->getName();
                if ($old_name === null || trim($old_name) === '') {
                    $bom_entry->setName($entity->getName());
                } else {
                    $bom_entry->setName($old_name . ' (' . $entity->getName() . ')');
                }

                $old_comment = $bom_entry->getComment();
                if ($old_comment === null || trim($old_comment) === '') {
                    $bom_entry->setComment('Part was deleted: ' . $entity->getName());
                } else {
                    $bom_entry->setComment($old_comment . "\n\n Part was deleted: " . $entity->getName());
                }

                //Remove the part reference
                $bom_entry->setPart(null);

                $uow->recomputeSingleEntityChangeSet(
                    $em->getClassMetadata(ProjectBOMEntry::class),
                    $bom_entry
                );
            }
        }
    }
}
