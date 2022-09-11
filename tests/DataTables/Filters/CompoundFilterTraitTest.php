<?php

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

            protected $filter1;
            private $filter2;
            public $filter3;
            protected $filter4;

            public function __construct($f1, $f2, $f3, $f4) {
                $this->filter1 = $f1;
                $this->filter2 = $f2;
                $this->filter3 = $f3;
                $this->filter4 = $f4;
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

            protected $filter1;
            private $filter2;
            public $filter3;
            protected $filter4;

            public function __construct($f1, $f2, $f3, $f4) {
                $this->filter1 = $f1;
                $this->filter2 = $f2;
                $this->filter3 = $f3;
                $this->filter4 = $f4;
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
