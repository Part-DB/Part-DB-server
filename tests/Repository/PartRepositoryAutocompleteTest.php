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
     * @param  Part[]  $parts
     * @return string[]
     */
    private function getNames(array $parts): array
    {
        return array_map(static fn(Part $part) => $part->getName(), $parts);
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
            $this->getNames($this->repo->autocompleteSearch('Part', 10, PartSearchSort::NAME, true)));

        //Only Part 2 has a manufacturer, the parts without one are put at the end (ordered by their name)
        $this->assertSame(['Part 2', 'Part 1', 'Part 3'],
            $this->getNames($this->repo->autocompleteSearch('Part', 10, PartSearchSort::MANUFACTURER)));
    }

    public function testAutocompleteSearchWithFilterOrdersByNameByDefault(): void
    {
        $filter = new PartSearchFilter('Part');
        $filter->setName(true);

        $this->assertSame(['Part 1', 'Part 2', 'Part 3'], $this->getNames($this->repo->autocompleteSearchWithFilter($filter, 10)));
        $this->assertSame(['Part 1', 'Part 2'], $this->getNames($this->repo->autocompleteSearchWithFilter($filter, 2)));
    }
}
