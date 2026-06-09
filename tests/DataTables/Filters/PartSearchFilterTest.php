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
use App\Settings\BehaviorSettings\SearchSettings;
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
    private function makeSearchSettings(
        bool $enableAdvancedSearch = false,
        int $searchTokenLimit = 3,
        bool $escapeSQLWildcards = true,
    ): SearchSettings {
        $settings = $this->createMock(SearchSettings::class);
        $settings->enableAdvancedSearch = $enableAdvancedSearch;
        $settings->searchTokenLimit = $searchTokenLimit;
        $settings->escapeSQLWildcards = $escapeSQLWildcards;

        return $settings;
    }

    public function testApplyReturnsEarlyWhenKeywordEmpty(): void
    {
        $filter = new PartSearchFilter('', $this->makeSearchSettings());

        $qb = $this->createMock(QueryBuilder::class);
        $qb->expects($this->never())->method('andWhere');
        $qb->expects($this->never())->method('setParameter');

        $filter->apply($qb);
    }

    public function testApplyReturnsEarlyWhenNothingToSearchForAndNoExactIdSearch(): void
    {
        $filter = (new PartSearchFilter('foo', $this->makeSearchSettings()))
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
        $qb->expects($this->never())->method('andWhere');
        $qb->expects($this->never())->method('setParameter');

        $filter->apply($qb);
    }

    public function testApplyUsesRegexExpressionAndRawParameterWhenRegexEnabled(): void
    {
        $filter = (new PartSearchFilter('foo.*bar', $this->makeSearchSettings()))
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
                $p = $params->get('search_query');
                $this->assertNotNull($p);
                $this->assertSame('foo.*bar', $p->getValue());

                return true;
            }));

        // We don't assert the exact expression object (Doctrine internals), only that a WHERE is added.
        $qb->expects($this->once())->method('andWhere');

        $filter->apply($qb);
    }

    public function testApplyEscapesSqlWildcardsAndWrapsLikeParameterWhenRegexDisabled(): void
    {
        $filter = (new PartSearchFilter('10%_off', $this->makeSearchSettings(escapeSQLWildcards: true)))
            ->setRegex(false);

        $expr = $this->createMock(Expr::class);
        $expr->method('orX')->willReturn(new Orx());

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('expr')->willReturn($expr);

        $qb->expects($this->once())
            ->method('setParameter')
            ->with('search_query', '%10\%\_off%');

        $qb->expects($this->once())
            ->method('andWhere')
            ->with($this->isInstanceOf(Orx::class));

        $filter->apply($qb);
    }

    public function testApplyAddsExactIdExpressionWhenDbIdSearchEnabledAndKeywordNumeric(): void
    {
        $filter = (new PartSearchFilter('123', $this->makeSearchSettings()))
            ->setDbId(true);

        $expr = $this->createMock(Expr::class);
        $expr->expects($this->once())
            ->method('eq')
            ->with('part.id', ':id_exact')
            ->willReturn(new Comparison('part.id', '=', ':id_exact'));

        $expr->method('orX')->willReturn(new Orx());

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('expr')->willReturn($expr);

        $qb->expects($this->once())
            ->method('setParameter')
            ->with('id_exact', 123, ParameterType::INTEGER);

        $qb->expects($this->once())
            ->method('andWhere')
            ->with($this->isInstanceOf(Orx::class));

        $filter->apply($qb);
    }

    public function testApplyDoesNotAddExactIdExpressionWhenKeywordNotNumeric(): void
    {
        $filter = (new PartSearchFilter('123abc', $this->makeSearchSettings()))
            ->setDbId(true);

        $expr = $this->createMock(Expr::class);
        $expr->expects($this->never())->method('eq');
        $expr->method('orX')->willReturn(new Orx());

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('expr')->willReturn($expr);

        // It should still set the search_query parameter for LIKE (default regex=false)
        $qb->expects($this->once())
            ->method('setParameter')
            ->with('search_query', '%123abc%');

        $qb->expects($this->once())->method('andWhere');

        $filter->apply($qb);
    }
}
