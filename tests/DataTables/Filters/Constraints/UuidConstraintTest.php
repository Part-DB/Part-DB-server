<?php
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

declare(strict_types=1);

namespace App\Tests\DataTables\Filters\Constraints;

use App\DataTables\Filters\Constraints\UuidConstraint;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\TestCase;

final class UuidConstraintTest extends TestCase
{
    public function testConstructorDefaults(): void
    {
        $constraint = new UuidConstraint('log.request_id');
        $this->assertNull($constraint->getValue());
        $this->assertSame('', $constraint->getOperator());
        $this->assertFalse($constraint->isEnabled());
    }

    public function testIsEnabledRequiresBothValueAndOperator(): void
    {
        $constraint = new UuidConstraint('log.request_id');

        $constraint->setValue('01a043f6-d219-7bf2-ab24-cebd599e7d44');
        $this->assertFalse($constraint->isEnabled());

        $constraint->setOperator('=');
        $this->assertTrue($constraint->isEnabled());

        $constraint->setValue('');
        $this->assertFalse($constraint->isEnabled());
    }

    public function testApplyDoesNothingWhenDisabled(): void
    {
        $constraint = new UuidConstraint('log.request_id');
        $queryBuilder = $this->createMock(QueryBuilder::class);

        $queryBuilder->expects($this->never())->method('andWhere');
        $queryBuilder->expects($this->never())->method('setParameter');

        $constraint->apply($queryBuilder);
    }

    public function testApplyThrowsOnUnsupportedOperator(): void
    {
        $constraint = new UuidConstraint('log.request_id');
        $constraint->setValue('01a043f6-d219-7bf2-ab24-cebd599e7d44');
        $constraint->setOperator('CONTAINS');

        $this->expectException(\RuntimeException::class);
        $constraint->apply($this->createMock(QueryBuilder::class));
    }

    public function testApplyWithValidUuidBindsExplicitUuidType(): void
    {
        $constraint = new UuidConstraint('log.request_id', 'rid');
        $uuid = '01a043f6-d219-7bf2-ab24-cebd599e7d44';
        $constraint->setValue($uuid);
        $constraint->setOperator('=');

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->expects($this->once())
            ->method('andWhere')
            ->with('log.request_id = :rid')
            ->willReturnSelf();
        //The explicit "uuid" type is what makes Doctrine convert the plain string into the column's native
        //representation (e.g. BINARY(16)) instead of comparing it as plain text.
        $queryBuilder->expects($this->once())
            ->method('setParameter')
            ->with('rid', $uuid, 'uuid');

        $constraint->apply($queryBuilder);
    }

    public function testApplyWithInvalidUuidNeverMatches(): void
    {
        $constraint = new UuidConstraint('log.request_id');
        $constraint->setValue('not-a-uuid');
        $constraint->setOperator('=');

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->expects($this->once())
            ->method('andWhere')
            ->with('1 = 0');
        $queryBuilder->expects($this->never())->method('setParameter');

        $constraint->apply($queryBuilder);
    }
}
