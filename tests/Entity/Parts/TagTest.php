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

namespace App\Tests\Entity\Parts;

use App\Entity\Parts\Part;
use App\Entity\Parts\Tag;
use App\Helpers\BootstrapColor;
use PHPUnit\Framework\TestCase;

final class TagTest extends TestCase
{
    public function testNameIsNormalized(): void
    {
        $this->assertSame('SMD', (new Tag('  SMD '))->getName());
        $this->assertSame('Risk High', (new Tag('Risk   High'))->getName());
        $this->assertSame('Risk High', (new Tag())->setName("Risk\tHigh ")->getName());
    }

    public function testNameKeyIgnoresCaseAndWhitespace(): void
    {
        $this->assertSame(Tag::nameKey('SMD'), Tag::nameKey(' smd '));
        $this->assertSame(Tag::nameKey('Über Tag'), Tag::nameKey('über  tag'));
        $this->assertNotSame(Tag::nameKey('SMD'), Tag::nameKey('THT'));
    }

    public function testSplitNames(): void
    {
        $this->assertSame([], Tag::splitNames(''));
        $this->assertSame([], Tag::splitNames(' , ,'));
        //The first spelling of names which only differ in case wins
        $this->assertSame(['test', 'Part2'], Tag::splitNames('test, Test, Part2'));
        $this->assertSame(['a', 'b c'], Tag::splitNames('a,b  c,'));
    }

    public function testBadgeClass(): void
    {
        $tag = new Tag('SMD');
        $this->assertSame('bg-primary', $tag->getBadgeClass());
        $this->assertSame('bg-info', $tag->getBadgeClass('bg-info'));

        $tag->setColor(BootstrapColor::DANGER);
        $this->assertSame('text-bg-danger', $tag->getBadgeClass('bg-info'));
    }

    public function testColorIsInheritedFromTheParent(): void
    {
        $group = (new Tag('Risk'))->setColor(BootstrapColor::INFO);
        $tag = (new Tag('Risk:High'))->setParent($group);
        $subTag = (new Tag('Risk:High:Now'))->setParent($tag);

        $this->assertSame(BootstrapColor::INFO, $subTag->getEffectiveColor());
        $this->assertSame('text-bg-info', $subTag->getBadgeClass());

        //The own color wins over the inherited one
        $tag->setColor(BootstrapColor::DANGER);
        $this->assertSame('text-bg-danger', $subTag->getBadgeClass());
        $this->assertSame('text-bg-info', $group->getBadgeClass());

        $this->assertNull((new Tag('Ungrouped'))->getEffectiveColor());
    }

    public function testPartSetTagsKeepsAssignedTags(): void
    {
        $part = new Part();
        $part->setTags('SMD, Resistor');
        $smd = $this->findTag($part, 'SMD');

        //A tag which is still wanted keeps its instance, even if it is spelled differently now
        $part->setTags('smd, 0603');
        $this->assertSame($smd, $this->findTag($part, 'SMD'));
        $this->assertSame(['0603', 'SMD'], $part->getTagNames());
        $this->assertNull($this->findTag($part, 'Resistor'));
    }

    public function testPartGetTagsIsSortedNaturally(): void
    {
        $part = new Part();
        $part->setTags('b, Tag10, a, Tag2');
        $this->assertSame('a,b,Tag2,Tag10', $part->getTags());

        $part->setTags('');
        $this->assertSame('', $part->getTags());
        $this->assertCount(0, $part->getTagEntities());
    }

    public function testPartAddAndRemoveTagEntity(): void
    {
        $part = new Part();
        $tag = new Tag('SMD');
        $part->addTagEntity($tag);
        $part->addTagEntity($tag);
        $this->assertCount(1, $part->getTagEntities());

        $part->removeTagEntity($tag);
        $this->assertCount(0, $part->getTagEntities());
    }

    public function testClonedPartHasOwnTagCollection(): void
    {
        $part = new Part();
        $part->setTags('SMD');

        $clone = clone $part;
        $clone->setTags('SMD, THT');

        $this->assertSame('SMD', $part->getTags());
        $this->assertSame('SMD,THT', $clone->getTags());
        //Both refer to the same tag entity
        $this->assertSame($this->findTag($part, 'SMD'), $this->findTag($clone, 'SMD'));
    }

    private function findTag(Part $part, string $name): ?Tag
    {
        foreach ($part->getTagEntities() as $tag) {
            if (Tag::nameKey($tag->getName()) === Tag::nameKey($name)) {
                return $tag;
            }
        }

        return null;
    }
}
