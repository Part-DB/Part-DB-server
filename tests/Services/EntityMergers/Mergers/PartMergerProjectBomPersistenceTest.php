<?php

declare(strict_types=1);

/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2023 Jan Böhmer (https://github.com/jbtronics)
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
namespace App\Tests\Services\EntityMergers\Mergers;

use App\Entity\Parts\Category;
use App\Entity\Parts\Part;
use App\Entity\ProjectSystem\Project;
use App\Entity\ProjectSystem\ProjectBOMEntry;
use App\Services\EntityMergers\Mergers\PartMerger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * PartMergerTest only exercises the merger against in-memory, unpersisted objects, so it can not catch mistakes
 * that only surface once the entity manager actually has to persist/remove the involved entities (e.g. missing
 * cascades, or a dangling foreign key to the merged-away "other" part once it is deleted). This test therefore
 * performs the merge against real, persisted entities and flushes afterward, like PartController::merge() does.
 */
final class PartMergerProjectBomPersistenceTest extends KernelTestCase
{
    private ?EntityManagerInterface $em = null;
    private ?PartMerger $merger = null;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $this->merger = self::getContainer()->get(PartMerger::class);
    }

    private function getCategory(): Category
    {
        $category = $this->em->getRepository(Category::class)->findOneBy(['name' => 'Node 1']);
        self::assertInstanceOf(Category::class, $category);
        return $category;
    }

    private function newPart(string $name, Category $category): Part
    {
        $part = new Part();
        $part->setName($name);
        $part->setCategory($category);
        return $part;
    }

    public function testMergingPartsUsedInDifferentProjectsDoesNotBreakOnDeleteOfOther(): void
    {
        $category = $this->getCategory();

        $part1 = $this->newPart('Merge Persistence Target', $category);
        $part2 = $this->newPart('Merge Persistence Other', $category);
        $this->em->persist($part1);
        $this->em->persist($part2);

        $projectB = new Project();
        $projectB->setName('Merge Persistence Project B');
        $projectC = new Project();
        $projectC->setName('Merge Persistence Project C');
        $this->em->persist($projectB);
        $this->em->persist($projectC);

        $entryB = (new ProjectBOMEntry())->setQuantity(3.0);
        $projectB->addBomEntry($entryB);
        $part2->addProjectBomEntry($entryB);
        $this->em->persist($entryB);

        $entryC = (new ProjectBOMEntry())->setQuantity(5.0);
        $projectC->addBomEntry($entryC);
        $part2->addProjectBomEntry($entryC);
        $this->em->persist($entryC);

        $this->em->flush();

        $part1Id = $part1->getID();
        $part2Id = $part2->getID();
        $this->em->clear();

        $target = $this->em->getRepository(Part::class)->find($part1Id);
        $other = $this->em->getRepository(Part::class)->find($part2Id);
        self::assertNotNull($target);
        self::assertNotNull($other);

        $merged = $this->merger->merge($target, $other);
        $this->em->persist($merged);
        //Mirrors PartController::merge()/renderPartForm(), which removes the merged-away part after merging
        $this->em->remove($other);
        $this->em->flush();

        self::assertNull($this->em->getRepository(Part::class)->find($part2Id));
        self::assertCount(2, $merged->getProjectBomEntries());
    }

    public function testMergingPartsUsedInSameProjectDeletesRedundantEntryWithoutBreaking(): void
    {
        $category = $this->getCategory();

        $part1 = $this->newPart('Merge Persistence Target 2', $category);
        $part2 = $this->newPart('Merge Persistence Other 2', $category);
        $this->em->persist($part1);
        $this->em->persist($part2);

        $project = new Project();
        $project->setName('Merge Persistence Shared Project');
        $this->em->persist($project);

        $entry1 = (new ProjectBOMEntry())->setQuantity(2.0);
        $project->addBomEntry($entry1);
        $part1->addProjectBomEntry($entry1);
        $this->em->persist($entry1);

        $entry2 = (new ProjectBOMEntry())->setQuantity(4.0);
        $project->addBomEntry($entry2);
        $part2->addProjectBomEntry($entry2);
        $this->em->persist($entry2);

        $this->em->flush();

        $part2Id = $part2->getID();
        $entry2Id = $entry2->getID();
        $part1Id = $part1->getID();
        $this->em->clear();

        $target = $this->em->getRepository(Part::class)->find($part1Id);
        $other = $this->em->getRepository(Part::class)->find($part2Id);
        self::assertNotNull($target);
        self::assertNotNull($other);

        $merged = $this->merger->merge($target, $other);
        $this->em->persist($merged);
        $this->em->remove($other);
        $this->em->flush();

        self::assertNull($this->em->getRepository(Part::class)->find($part2Id));
        //The redundant entry (merged into the surviving one) must actually be deleted, not just detached
        self::assertNull($this->em->getRepository(ProjectBOMEntry::class)->find($entry2Id));
        self::assertCount(1, $merged->getProjectBomEntries());
        self::assertSame(6.0, $merged->getProjectBomEntries()->first()->getQuantity());
    }
}
