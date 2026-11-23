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

namespace App\Tests\EntityListeners;

use App\Entity\LogSystem\ElementEditedLogEntry;
use App\Entity\LogSystem\LogTargetType;
use App\Entity\Parts\Category;
use App\Entity\Parts\Part;
use App\Entity\Parts\Tag;
use App\Services\LogSystem\TimeTravel;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class TagAssignmentListenerTest extends KernelTestCase
{
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
    }

    public function testExistingTagIsReused(): void
    {
        //The fixtures contain the tag "test"
        $existing = $this->em->getRepository(Tag::class)->findOneBy(['name' => 'test']);
        $this->assertInstanceOf(Tag::class, $existing);
        $tagCount = $this->countTags();

        $part = $this->createPart();
        $part->setTags('TEST, Brand new');
        $this->em->flush();

        $this->assertSame($tagCount + 1, $this->countTags());
        $this->assertTrue($part->getTagEntities()->contains($existing));
        $this->assertSame(['Brand new', 'test'], $part->getTagNames());
    }

    public function testPartsShareANewTagCreatedInTheSameFlush(): void
    {
        $tagCount = $this->countTags();

        $first = $this->createPart();
        $first->setTags('Shared');
        $second = $this->createPart();
        $second->setTags('shared');
        $this->em->flush();

        $this->assertSame($tagCount + 1, $this->countTags());
        $this->assertSame($first->getTagEntities()->first(), $second->getTagEntities()->first());
        $this->assertNotNull($first->getTagEntities()->first()->getID());
    }

    public function testExistingPartGetsExistingTag(): void
    {
        $part = $this->createPart();
        $this->em->flush();
        $tagCount = $this->countTags();

        $part->setTags('Part2');
        $this->em->flush();
        $this->em->clear();

        $this->assertSame($tagCount, $this->countTags());
        $reloaded = $this->em->find(Part::class, $part->getID());
        $this->assertSame('Part2', $reloaded->getTags());
    }

    public function testRemovingATagKeepsTheTag(): void
    {
        $part = $this->em->find(Part::class, 2);
        $this->assertSame('Part2,test', $part->getTags());
        $tagCount = $this->countTags();

        $part->setTags('test');
        $this->em->flush();
        $this->em->clear();

        $this->assertSame('test', $this->em->find(Part::class, 2)->getTags());
        $this->assertSame($tagCount, $this->countTags());
    }

    public function testChangingOnlyTheTagsIsAnEditOfThePart(): void
    {
        $part = $this->em->find(Part::class, 2);
        $lastModified = $part->getLastModified();

        $part->setTags('test, Brand new');
        $this->em->flush();

        $this->assertNotEquals($lastModified, $part->getLastModified());

        /** @var ElementEditedLogEntry|null $log */
        $log = $this->em->getRepository(ElementEditedLogEntry::class)
            ->findOneBy(['target_type' => LogTargetType::PART, 'target_id' => 2], ['id' => 'DESC']);
        $this->assertNotNull($log);
        $this->assertSame('Part2,test', $log->getOldData()['tags']);

        //The time travel restores the tags from the log
        self::getContainer()->get(TimeTravel::class)->applyEntry($part, $log);
        $this->assertSame('Part2,test', $part->getTags());
    }

    public function testUnchangedTagsAreNoEdit(): void
    {
        $part = $this->em->find(Part::class, 2);
        $logCount = $this->em->getRepository(ElementEditedLogEntry::class)->count([]);

        //The same tags, only spelled differently, are no change
        $part->setTags('PART2, Test');
        $this->em->flush();

        $this->assertSame($logCount, $this->em->getRepository(ElementEditedLogEntry::class)->count([]));
    }

    private function createPart(): Part
    {
        $part = new Part();
        $part->setName('Tag test part');
        $part->setCategory($this->em->find(Category::class, 1));
        $this->em->persist($part);

        return $part;
    }

    private function countTags(): int
    {
        return $this->em->getRepository(Tag::class)->count([]);
    }
}
