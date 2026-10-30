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

namespace App\Tests\Repository;

use App\DataTables\Filters\PartSearchFilter;
use App\DataTables\Filters\PartSearchSort;
use App\Entity\Parts\Part;
use App\Repository\PartRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class PartRepositoryAutocompleteTest extends KernelTestCase
{
    private PartRepository $repo;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->repo = self::getContainer()->get('doctrine')->getManager()->getRepository(Part::class);
    }

    /**
     * @param  array<array{name: string}>  $parts
     * @return string[]
     */
    private function getNames(array $parts): array
    {
        return array_column($parts, 'name');
    }

    public function testAutocompleteSearchOrdersByNameByDefault(): void
    {
        $this->assertSame(['Part 1', 'Part 2', 'Part 3'], $this->getNames($this->repo->autocompleteSearch('Part', 10)));
        $this->assertSame(['Part 1', 'Part 2'], $this->getNames($this->repo->autocompleteSearch('Part', 2)));
        $this->assertSame([], $this->repo->autocompleteSearch('does not exist', 10));
    }

    public function testAutocompleteSearchSorted(): void
    {
        $this->assertSame(['Part 3', 'Part 2', 'Part 1'],
            $this->getNames($this->repo->autocompleteSearch('Part', 10, PartSearchSort::NAME, \SortDirection::Descending)));

        //Only Part 2 has a manufacturer, the parts without one are put at the end (ordered by their name)
        $this->assertSame(['Part 2', 'Part 1', 'Part 3'],
            $this->getNames($this->repo->autocompleteSearch('Part', 10, PartSearchSort::MANUFACTURER)));
    }

    public function testAutocompleteSearchReturnsPartData(): void
    {
        $parts = $this->repo->autocompleteSearch('Part', 10);
        [$part1, $part2, $part3] = $parts;

        $this->assertSame('Part 2', $part2['name']);
        $this->assertSame('Node 1', $part2['category']);
        $this->assertSame(1, $part2['category_id']);
        $this->assertSame('Node 1', $part2['footprint']);
        $this->assertSame('Node 1', $part2['manufacturer']);
        $this->assertInstanceOf(\DateTimeImmutable::class, $part2['addedDate']);
        $this->assertCount(3, $part2['preview_attachments']);

        $this->assertNull($part1['footprint']);
        $this->assertNull($part1['manufacturer']);
        $this->assertSame([], $part1['suppliers']);

        //Both orderdetails of Part 3 have the same supplier, it is only listed once
        $this->assertSame(['Node 1'], $part3['suppliers']);
    }

    public function testAutocompleteSearchWithFilterOrdersByNameByDefault(): void
    {
        $filter = new PartSearchFilter('Part');
        $filter->setName(true);

        $this->assertSame(['Part 1', 'Part 2', 'Part 3'], $this->getNames($this->repo->autocompleteSearchWithFilter($filter, 10)));
        $this->assertSame(['Part 1', 'Part 2'], $this->getNames($this->repo->autocompleteSearchWithFilter($filter, 2)));
    }
}
