<?php

declare(strict_types=1);

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

namespace App\Tests\EventListener;

use App\Entity\Parts\Category;
use App\Entity\Parts\Part;
use App\Entity\ProjectSystem\Project;
use App\Entity\ProjectSystem\ProjectBOMEntry;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Integration test for PartProjectBOMEntryUnlinkListener. Both tests need DATABASE_SQLITE_ENFORCE_FOREIGN_KEYS
 * to actually be enforced (see SQLiteForeignKeysMiddlewareWrapper) to be meaningful - without it, a broken
 * listener would silently leave a dangling id_part reference instead of failing loudly.
 */
final class PartProjectBOMEntryUnlinkListenerTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = self::getContainer()->get('doctrine')->getManager();
    }

    public function testDeletingPartUnlinksAndAnnotatesBomEntryInsteadOfViolatingForeignKey(): void
    {
        $category = $this->entityManager->getRepository(Category::class)->find(1);
        if (!$category) {
            self::markTestSkipped('Test category with ID 1 not found in fixtures');
        }

        $part = new Part();
        $part->setName('Listener Test Part');
        $part->setCategory($category);
        $this->entityManager->persist($part);

        $project = new Project();
        $project->setName('Listener Test Project');
        $this->entityManager->persist($project);

        $entry = (new ProjectBOMEntry())->setQuantity(1.0)->setName('')->setComment('Existing comment');
        $project->addBomEntry($entry);
        $part->addProjectBomEntry($entry);
        $this->entityManager->persist($entry);

        $this->entityManager->flush();

        $partId = $part->getId();
        $entryId = $entry->getId();
        $projectId = $project->getId();

        //Removing the part must not throw a foreign key constraint violation, even though the BOM entry
        //still references it - the listener must re-point/clear that reference within the same flush.
        $this->entityManager->remove($part);
        $this->entityManager->flush();

        $this->entityManager->clear();

        $this->assertNull($this->entityManager->getRepository(Part::class)->find($partId));

        $survivingEntry = $this->entityManager->getRepository(ProjectBOMEntry::class)->find($entryId);
        self::assertNotNull($survivingEntry, 'The BOM entry must not be deleted together with the part');
        self::assertNull($survivingEntry->getPart(), 'The BOM entry must no longer reference the deleted part');
        self::assertSame('Listener Test Part', $survivingEntry->getName());
        self::assertStringContainsString('Existing comment', $survivingEntry->getComment());
        self::assertStringContainsString('Part was deleted: Listener Test Part', $survivingEntry->getComment());

        // Clean up
        $this->entityManager->remove($survivingEntry);
        $this->entityManager->remove($this->entityManager->getRepository(Project::class)->find($projectId));
        $this->entityManager->flush();
    }

    /**
     * Regression test for the actual failure mode this listener has to guard against: removing a Part
     * together with the Project that owns its only BOM entry, in the *same* flush (e.g. cleaning up test
     * fixtures, or various admin flows). Project's bom_entries association cascade-removes the BOM entry,
     * so by flush time it is scheduled for deletion just like the part.
     *
     * If the listener unconditionally nulled bom_entry->part (as a naive preRemove-based implementation
     * would), Doctrine's delete-order computation - which reads the *current* in-memory association value
     * to decide dependencies - would no longer see that the BOM entry depends on the part, and could delete
     * the "parts" row before the "project_bom_entries" row, which still has its old id_part value in the
     * database. That throws a foreign key constraint violation instead of the expected clean cascade delete.
     */
    public function testDeletingPartTogetherWithItsOnlyProjectDoesNotViolateForeignKey(): void
    {
        $category = $this->entityManager->getRepository(Category::class)->find(1);
        if (!$category) {
            self::markTestSkipped('Test category with ID 1 not found in fixtures');
        }

        $part = new Part();
        $part->setName('Listener Test Part (cascade)');
        $part->setCategory($category);
        $this->entityManager->persist($part);

        $project = new Project();
        $project->setName('Listener Test Project (cascade)');
        $this->entityManager->persist($project);

        $entry = (new ProjectBOMEntry())->setQuantity(1.0);
        $project->addBomEntry($entry);
        $part->addProjectBomEntry($entry);
        $this->entityManager->persist($entry);

        $this->entityManager->flush();

        $partId = $part->getId();
        $entryId = $entry->getId();
        $projectId = $project->getId();

        //Removing both the part and its only project in the same flush must not throw a foreign key
        //constraint violation - the cascade-deleted BOM entry must be dropped before the part row.
        $this->entityManager->remove($part);
        $this->entityManager->remove($project);
        $this->entityManager->flush();

        $this->entityManager->clear();

        self::assertNull($this->entityManager->getRepository(Part::class)->find($partId));
        self::assertNull($this->entityManager->getRepository(Project::class)->find($projectId));
        self::assertNull($this->entityManager->getRepository(ProjectBOMEntry::class)->find($entryId));
    }
}
