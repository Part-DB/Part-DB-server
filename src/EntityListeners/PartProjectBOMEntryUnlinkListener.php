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
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Event\PreRemoveEventArgs;

/**
 * If an part is deleted, this listener makes sure that all ProjectBOMEntries that reference this part, are updated
 * to not reference the part anymore, but instead store the part name in the name field.
 */
#[AsEntityListener(event: "preRemove", entity: Part::class)]
class PartProjectBOMEntryUnlinkListener
{
    public function preRemove(Part $part, PreRemoveEventArgs $event): void
    {
        // Iterate over all ProjectBOMEntries that use this part and put the part name into the name field
        foreach ($part->getProjectBomEntries() as $bom_entry) {
            $old_name = $bom_entry->getName();
            if ($old_name === null || trim($old_name) === '') {
                $bom_entry->setName($part->getName());
            } else {
                $bom_entry->setName($old_name . ' (' . $part->getName() . ')');
            }

            $old_comment = $bom_entry->getComment();
            if ($old_comment === null || trim($old_comment) === '') {
                $bom_entry->setComment('Part was deleted: ' . $part->getName());
            } else {
                $bom_entry->setComment($old_comment . "\n\n Part was deleted: " . $part->getName());
            }

            //Remove the part reference
            $bom_entry->setPart(null);
        }
    }
}
