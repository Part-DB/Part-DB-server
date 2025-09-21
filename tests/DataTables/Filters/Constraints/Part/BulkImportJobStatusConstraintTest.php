<?php
/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2023 Jan Böhmer (https://github.com/jbtronics)
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

declare(strict_types=1);

namespace App\Tests\DataTables\Filters\Constraints\Part;

use App\DataTables\Filters\Constraints\Part\BulkImportJobStatusConstraint;
use App\Entity\InfoProviderSystem\BulkInfoProviderImportJobPart;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\TestCase;

class BulkImportJobStatusConstraintTest extends TestCase
{
    private BulkImportJobStatusConstraint $constraint;
    private QueryBuilder $queryBuilder;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $this->constraint = new BulkImportJobStatusConstraint();
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->queryBuilder = $this->createMock(QueryBuilder::class);

        $this->queryBuilder->method('getEntityManager')
            ->willReturn($this->entityManager);
    }

    public function testConstructor(): void
    {
        $this->assertEquals([], $this->constraint->getValues());
        $this->assertNull($this->constraint->getOperator());
        $this->assertFalse($this->constraint->isEnabled());
    }

    public function testGetAndSetValues(): void
    {
        $values = ['pending', 'in_progress'];
        $this->constraint->setValues($values);

        $this->assertEquals($values, $this->constraint->getValues());
    }

    public function testGetAndSetOperator(): void
    {
        $operator = 'ANY';
        $this->constraint->setOperator($operator);

        $this->assertEquals($operator, $this->constraint->getOperator());
    }

    public function testIsEnabledWithEmptyValues(): void
    {
        $this->constraint->setOperator('ANY');

        $this->assertFalse($this->constraint->isEnabled());
    }

    public function testIsEnabledWithNullOperator(): void
    {
        $this->constraint->setValues(['pending']);

        $this->assertFalse($this->constraint->isEnabled());
    }

    public function testIsEnabledWithValuesAndOperator(): void
    {
        $this->constraint->setValues(['pending']);
        $this->constraint->setOperator('ANY');

        $this->assertTrue($this->constraint->isEnabled());
    }

    public function testApplyWithEmptyValues(): void
    {
        $this->constraint->setOperator('ANY');

        $this->queryBuilder->expects($this->never())
            ->method('andWhere');

        $this->constraint->apply($this->queryBuilder);
    }

    public function testApplyWithNullOperator(): void
    {
        $this->constraint->setValues(['pending']);

        $this->queryBuilder->expects($this->never())
            ->method('andWhere');

        $this->constraint->apply($this->queryBuilder);
    }

    public function testApplyWithAnyOperator(): void
    {
        $this->constraint->setValues(['pending', 'in_progress']);
        $this->constraint->setOperator('ANY');

        $subQueryBuilder = $this->createMock(QueryBuilder::class);
        $subQueryBuilder->method('select')->willReturnSelf();
        $subQueryBuilder->method('from')->willReturnSelf();
        $subQueryBuilder->method('join')->willReturnSelf();
        $subQueryBuilder->method('where')->willReturnSelf();
        $subQueryBuilder->method('andWhere')->willReturnSelf();
        $subQueryBuilder->method('getDQL')->willReturn('EXISTS_SUBQUERY_DQL');

        $this->entityManager->method('createQueryBuilder')
            ->willReturn($subQueryBuilder);

        $this->queryBuilder->expects($this->once())
            ->method('andWhere')
            ->with('EXISTS (EXISTS_SUBQUERY_DQL)');

        $this->queryBuilder->expects($this->once())
            ->method('setParameter')
            ->with('job_status_values', ['pending', 'in_progress']);

        $this->constraint->apply($this->queryBuilder);
    }

    public function testApplyWithNoneOperator(): void
    {
        $this->constraint->setValues(['completed']);
        $this->constraint->setOperator('NONE');

        $subQueryBuilder = $this->createMock(QueryBuilder::class);
        $subQueryBuilder->method('select')->willReturnSelf();
        $subQueryBuilder->method('from')->willReturnSelf();
        $subQueryBuilder->method('join')->willReturnSelf();
        $subQueryBuilder->method('where')->willReturnSelf();
        $subQueryBuilder->method('andWhere')->willReturnSelf();
        $subQueryBuilder->method('getDQL')->willReturn('EXISTS_SUBQUERY_DQL');

        $this->entityManager->method('createQueryBuilder')
            ->willReturn($subQueryBuilder);

        $this->queryBuilder->expects($this->once())
            ->method('andWhere')
            ->with('NOT EXISTS (EXISTS_SUBQUERY_DQL)');

        $this->queryBuilder->expects($this->once())
            ->method('setParameter')
            ->with('job_status_values', ['completed']);

        $this->constraint->apply($this->queryBuilder);
    }

    public function testApplyWithUnsupportedOperator(): void
    {
        $this->constraint->setValues(['pending']);
        $this->constraint->setOperator('UNKNOWN');

        $subQueryBuilder = $this->createMock(QueryBuilder::class);
        $subQueryBuilder->method('select')->willReturnSelf();
        $subQueryBuilder->method('from')->willReturnSelf();
        $subQueryBuilder->method('join')->willReturnSelf();
        $subQueryBuilder->method('where')->willReturnSelf();
        $subQueryBuilder->method('getDQL')->willReturn('EXISTS_SUBQUERY_DQL');

        $this->entityManager->method('createQueryBuilder')
            ->willReturn($subQueryBuilder);

        // Should not call andWhere for unsupported operator
        $this->queryBuilder->expects($this->never())
            ->method('andWhere');

        $this->constraint->apply($this->queryBuilder);
    }

    public function testSubqueryStructure(): void
    {
        $this->constraint->setValues(['pending']);
        $this->constraint->setOperator('ANY');

        $subQueryBuilder = $this->createMock(QueryBuilder::class);

        $subQueryBuilder->expects($this->once())
            ->method('select')
            ->with('1')
            ->willReturnSelf();

        $subQueryBuilder->expects($this->once())
            ->method('from')
            ->with(BulkInfoProviderImportJobPart::class, 'bip_status')
            ->willReturnSelf();

        $subQueryBuilder->expects($this->once())
            ->method('join')
            ->with('bip_status.job', 'job_status')
            ->willReturnSelf();

        $subQueryBuilder->expects($this->once())
            ->method('where')
            ->with('bip_status.part = part.id')
            ->willReturnSelf();

        $subQueryBuilder->expects($this->once())
            ->method('andWhere')
            ->with('job_status.status IN (:job_status_values)')
            ->willReturnSelf();

        $subQueryBuilder->method('getDQL')->willReturn('EXISTS_SUBQUERY_DQL');

        $this->entityManager->method('createQueryBuilder')
            ->willReturn($subQueryBuilder);

        $this->queryBuilder->method('andWhere');
        $this->queryBuilder->method('setParameter');

        $this->constraint->apply($this->queryBuilder);
    }

    public function testValuesAndOperatorMutation(): void
    {
        // Test that values and operator can be changed after creation
        $this->constraint->setValues(['pending']);
        $this->constraint->setOperator('ANY');
        $this->assertTrue($this->constraint->isEnabled());

        $this->constraint->setValues([]);
        $this->assertFalse($this->constraint->isEnabled());

        $this->constraint->setValues(['completed']);
        $this->assertTrue($this->constraint->isEnabled());

        $this->constraint->setOperator(null);
        $this->assertFalse($this->constraint->isEnabled());

        $this->constraint->setOperator('NONE');
        $this->assertTrue($this->constraint->isEnabled());
    }
}
