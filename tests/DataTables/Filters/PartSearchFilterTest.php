<?php

/**
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 * Copyright (C) 2019 - 2025 Jan Böhmer (https://github.com/jbtronics)
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

namespace App\Tests\DataTables\Filters;

use App\DataTables\Filters\PartSearchFilter;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\DBAL\ParameterType;
use Doctrine\ORM\Query\Expr;
use Doctrine\ORM\Query\Expr\Comparison;
use Doctrine\ORM\Query\Expr\Orx;
use Doctrine\ORM\Query\Parameter;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\TestCase;

final class PartSearchFilterTest extends TestCase
{

    public function testApplyEnforcesNoResultsWhenKeywordEmpty(): void
    {
        $filter = new PartSearchFilter('');

        $qb = $this->createMock(QueryBuilder::class);
        $qb->expects($this->once())
            ->method('add')
            ->with('where', '1 = 0');
        $qb->expects($this->never())->method('andWhere');
        $qb->expects($this->never())->method('setParameters');

        $filter->apply($qb);
    }

    public function testApplyEnforcesNoResultsWhenNothingToSearchForAndNoExactIdSearch(): void
    {
        $filter = (new PartSearchFilter('foo'))
            ->setName(false)
            ->setCategory(false)
            ->setDescription(false)
            ->setComment(false)
            ->setTags(false)
            ->setStorelocation(false)
            ->setOrdernr(false)
            ->setMpn(false)
            ->setSupplier(false)
            ->setManufacturer(false)
            ->setFootprint(false)
            ->setIPN(false)
            ->setDbId(false);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->expects($this->once())
            ->method('add')
            ->with('where', '1 = 0');
        $qb->expects($this->never())->method('andWhere');
        $qb->expects($this->never())->method('setParameters');

        $filter->apply($qb);
    }

    public function testApplyUsesRegexExpressionAndRawParameterWhenRegexEnabled(): void
    {
        $filter = (new PartSearchFilter('foo.*bar'))
            ->setRegex(true);

        $expr = $this->createStub(Expr::class);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('expr')->willReturn($expr);

        $qb->expects($this->never())->method('setParameter');

        // In PR #1406 the filter uses setParameters(ArrayCollection<Parameter>) instead of setParameter() in regex mode.
        $qb->expects($this->once())
            ->method('setParameters')
            ->with($this->callback(function ($params): bool {
                $this->assertInstanceOf(\Doctrine\Common\Collections\ArrayCollection::class, $params);

                /** @var \Doctrine\ORM\Query\Parameter|null $p */
                $p = $params->get(0);
                $this->assertNotNull($p);
                $this->assertSame('search_query', $p->getName());
                $this->assertSame('foo.*bar', $p->getValue());

                return true;
            }));

        // We don't assert the exact expression object (Doctrine internals), only that a WHERE is added.
        $qb->expects($this->once())->method('andWhere');

        $filter->apply($qb);
    }

    public function testApplyEscapesSqlWildcardsAndWrapsLikeParameterWhenRegexDisabled(): void
    {
        $filter = (new PartSearchFilter('10%_off'));
        $expr = $this->createMock(Expr::class);
        $expr->method('orX')->willReturn(new Orx());

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('expr')->willReturn($expr);

        $qb->expects($this->never())->method('setParameter');

        $qb->expects($this->once())
            ->method('setParameters')
            ->with($this->callback(function ($params): bool {
                $this->assertInstanceOf(ArrayCollection::class, $params);

                /** @var Parameter|null $p */
                $p = $params->get(0);
                $this->assertNotNull($p);
                $this->assertSame('search_query0', $p->getName());
                $this->assertSame('%10\\%\\_off%', $p->getValue());

                return true;
            }));

        $qb->expects($this->once())
            ->method('andWhere')
            ->with($this->isInstanceOf(Orx::class));

        $filter->apply($qb);
    }

    public function testApplyAddsExactIdExpressionWhenDbIdSearchEnabledAndKeywordNumeric(): void
    {
        $filter = (new PartSearchFilter('123'))
            ->setDbId(true);

        $expr = $this->createMock(Expr::class);
        $expr->expects($this->once())
            ->method('eq')
            ->with('part.id', ':id_exact')
            ->willReturn(new Comparison('part.id', '=', ':id_exact'));

        $expr->method('orX')->willReturn(new Orx());

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('expr')->willReturn($expr);

        $qb->expects($this->never())->method('setParameter');

        // New structure: LIKE token search is added via andWhere(...), exact ID match via orWhere(...),
        // and all parameters are passed in one consolidated setParameters() call.
        $qb->expects($this->once())
            ->method('setParameters')
            ->with($this->callback(function ($params): bool {
                $this->assertInstanceOf(ArrayCollection::class, $params);

                /** @var Parameter|null $p0 */
                $p0 = $params->get(0);
                $this->assertNotNull($p0);
                $this->assertSame('search_query0', $p0->getName());
                $this->assertSame('%123%', $p0->getValue());

                /** @var Parameter|null $p1 */
                $p1 = $params->get(1);
                $this->assertNotNull($p1);
                $this->assertSame('id_exact', $p1->getName());
                $this->assertSame(123, $p1->getValue());
                $this->assertSame(ParameterType::INTEGER, $p1->getType());

                return true;
            }));

        $qb->expects($this->once())
            ->method('andWhere')
            ->with($this->isInstanceOf(Orx::class));

        $qb->expects($this->once())
            ->method('orWhere')
            ->with($this->isInstanceOf(Comparison::class));

        $filter->apply($qb);
    }

    public function testApplyDoesNotAddExactIdExpressionWhenKeywordNotNumeric(): void
    {
        $filter = (new PartSearchFilter('123abc'))
            ->setDbId(true);

        $expr = $this->createMock(Expr::class);
        $expr->expects($this->never())->method('eq');
        $expr->method('orX')->willReturn(new Orx());

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('expr')->willReturn($expr);

        $qb->expects($this->never())->method('setParameter');

        $qb->expects($this->once())
            ->method('setParameters')
            ->with($this->callback(function ($params): bool {
                $this->assertInstanceOf(ArrayCollection::class, $params);

                /** @var Parameter|null $p */
                $p = $params->get(0);
                $this->assertNotNull($p);
                $this->assertSame('search_query0', $p->getName());
                $this->assertSame('%123abc%', $p->getValue());

                return true;
            }));

        $qb->expects($this->once())->method('andWhere');

        $filter->apply($qb);
    }
}
