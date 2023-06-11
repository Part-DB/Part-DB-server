<?php
/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2022 Jan Böhmer (https://github.com/jbtronics)
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

namespace App\Tests\DataTables\Filters;

use App\DataTables\Filters\CompoundFilterTrait;
use App\DataTables\Filters\FilterInterface;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\TestCase;

class CompoundFilterTraitTest extends TestCase
{

    public function testFindAllChildFiltersEmpty(): void
    {
        $filter = new class {
            use CompoundFilterTrait;

            public function _findAllChildFilters()
            {
                return $this->findAllChildFilters();
            }
        };

        $result = $filter->_findAllChildFilters();

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testFindAllChildFilters(): void
    {
        $f1 = $this->createMock(FilterInterface::class);
        $f2 = $this->createMock(FilterInterface::class);
        $f3 = $this->createMock(FilterInterface::class);

        $filter = new class($f1, $f2, $f3, null) {
            use CompoundFilterTrait;

            public function __construct(protected $filter1, private $filter2, public $filter3, protected $filter4)
            {
            }

            public function _findAllChildFilters()
            {
                return $this->findAllChildFilters();
            }
        };

        $result = $filter->_findAllChildFilters();

        $this->assertIsArray($result);
        $this->assertContainsOnlyInstancesOf(FilterInterface::class, $result);
        $this->assertSame([
            'filter1' => $f1,
            'filter2' => $f2,
            'filter3' => $f3
        ], $result);
    }
    
    public function testApplyAllChildFilters(): void
    {
        $f1 = $this->createMock(FilterInterface::class);
        $f2 = $this->createMock(FilterInterface::class);
        $f3 = $this->createMock(FilterInterface::class);

        $f1->expects($this->once())
            ->method('apply')
            ->with($this->isInstanceOf(QueryBuilder::class));

        $f2->expects($this->once())
            ->method('apply')
            ->with($this->isInstanceOf(QueryBuilder::class));

        $f3->expects($this->once())
            ->method('apply')
            ->with($this->isInstanceOf(QueryBuilder::class));

        $filter = new class($f1, $f2, $f3, null) {
            use CompoundFilterTrait;

            public function __construct(protected $filter1, private $filter2, public $filter3, protected $filter4)
            {
            }

            public function _applyAllChildFilters(QueryBuilder $queryBuilder): void
            {
                $this->applyAllChildFilters($queryBuilder);
            }
        };

        $qb = $this->createMock(QueryBuilder::class);
        $filter->_applyAllChildFilters($qb);
    }


}
