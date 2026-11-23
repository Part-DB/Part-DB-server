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

namespace App\Tests\DataTables\Filters;

use App\DataTables\Filters\Constraints\Part\TagsConstraint;
use App\DataTables\Filters\PartSearchFilter;
use App\Entity\Parts\Part;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Runs the tag filters against the database, as they query the tag entities in subqueries.
 * The fixtures assign the tags "test" and "Part2" to part 2 only.
 */
final class TagFilteringTest extends KernelTestCase
{
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
    }

    public static function constraintProvider(): iterable
    {
        yield 'any, case-insensitive' => ['ANY', 'PART2', [2]];
        yield 'any of several' => ['ANY', 'unknown, test', [2]];
        yield 'all' => ['ALL', 'test,Part2', [2]];
        yield 'all with a missing tag' => ['ALL', 'test, unknown', []];
        yield 'none' => ['NONE', 'test', [1, 3]];
        yield 'only commas do not filter' => ['ANY', ' , ', [1, 2, 3]];
    }

    /**
     * @param  list<int>  $expected
     */
    #[DataProvider('constraintProvider')]
    public function testTagsConstraint(string $operator, string $value, array $expected): void
    {
        $qb = $this->createPartQuery();
        (new TagsConstraint('part.tagEntities', null, $value, $operator))->apply($qb);

        $this->assertSame($expected, $this->getIds($qb));
    }

    public function testSearchInTags(): void
    {
        $qb = $this->createPartQuery();
        (new PartSearchFilter('ART2'))->setName(false)->setDescription(false)->setComment(false)
            ->setCategory(false)->setStorelocation(false)->setOrdernr(false)->setMpn(false)->setSupplier(false)
            ->setManufacturer(false)->setFootprint(false)->setIpn(false)->setDbId(false)
            ->setTags(true)
            ->apply($qb);

        $this->assertSame([2], $this->getIds($qb));
    }

    public function testRegexSearchInTags(): void
    {
        $qb = $this->createPartQuery();
        (new PartSearchFilter('^Part[0-9]$'))->setName(false)->setDescription(false)->setComment(false)
            ->setCategory(false)->setStorelocation(false)->setOrdernr(false)->setMpn(false)->setSupplier(false)
            ->setManufacturer(false)->setFootprint(false)->setIpn(false)->setDbId(false)
            ->setTags(true)->setRegex(true)
            ->apply($qb);

        $this->assertSame([2], $this->getIds($qb));
    }

    private function createPartQuery(): QueryBuilder
    {
        return $this->em->createQueryBuilder()->select('part.id')->from(Part::class, 'part')->orderBy('part.id');
    }

    /**
     * @return list<int>
     */
    private function getIds(QueryBuilder $qb): array
    {
        return array_map(static fn(array $row): int => (int) $row['id'], $qb->getQuery()->getArrayResult());
    }
}
