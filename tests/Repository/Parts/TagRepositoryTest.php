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

namespace App\Tests\Repository\Parts;

use App\Entity\Parts\Part;
use App\Entity\Parts\Tag;
use App\Repository\Parts\TagRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * The fixtures assign the tags "test" and "Part2" to part 2 only.
 */
final class TagRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private TagRepository $repo;

    protected function setUp(): void
    {
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $this->repo = $this->em->getRepository(Tag::class);
    }

    public function testFindOneByNameIsCaseInsensitive(): void
    {
        $this->assertSame('Part2', $this->repo->findOneByName(' PART2 ')?->getName());
        $this->assertNull($this->repo->findOneByName('unknown'));
    }

    public function testPartsOfATag(): void
    {
        $tag = $this->repo->findOneByName('test');

        $this->assertSame(1, $this->repo->getPartsCount($tag));
        $this->assertSame([2], array_map(static fn(Part $p) => $p->getID(), $this->repo->getParts($tag)));
        $this->assertSame(0, $this->repo->getPartsCount($this->repo->findOneByName('Node 1')));
    }

    public function testPartsOfTheSubtreeAreCountedOnce(): void
    {
        //Group both tags of part 2 below a new tag, so part 2 is found through two tags of the subtree
        $group = new Tag('Group');
        $this->em->persist($group);
        $this->repo->findOneByName('test')->setParent($group);
        $this->repo->findOneByName('Part2')->setParent($group);
        $this->em->flush();

        $this->assertSame(0, $this->repo->getPartsCount($group));
        $this->assertSame(1, $this->repo->getPartsCountRecursive($group));
        $this->assertSame([2], array_map(static fn(Part $p) => $p->getID(), $this->repo->getPartsRecursive($group)));
        $this->assertSame(['Group', 'Part2', 'test'], $this->sorted($this->repo->getSubtreeNames($group)));
    }

    /**
     * @param  string[]  $names
     * @return string[]
     */
    private function sorted(array $names): array
    {
        sort($names);

        return $names;
    }
}
