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

namespace App\Tests\Entity;

use App\Entity\InfoProviderSystem\BulkImportJobStatus;
use PHPUnit\Framework\TestCase;

class BulkImportJobStatusTest extends TestCase
{
    public function testEnumValues(): void
    {
        $this->assertEquals('pending', BulkImportJobStatus::PENDING->value);
        $this->assertEquals('in_progress', BulkImportJobStatus::IN_PROGRESS->value);
        $this->assertEquals('completed', BulkImportJobStatus::COMPLETED->value);
        $this->assertEquals('stopped', BulkImportJobStatus::STOPPED->value);
        $this->assertEquals('failed', BulkImportJobStatus::FAILED->value);
    }

    public function testEnumCases(): void
    {
        $cases = BulkImportJobStatus::cases();

        $this->assertCount(5, $cases);
        $this->assertContains(BulkImportJobStatus::PENDING, $cases);
        $this->assertContains(BulkImportJobStatus::IN_PROGRESS, $cases);
        $this->assertContains(BulkImportJobStatus::COMPLETED, $cases);
        $this->assertContains(BulkImportJobStatus::STOPPED, $cases);
        $this->assertContains(BulkImportJobStatus::FAILED, $cases);
    }

    public function testFromString(): void
    {
        $this->assertEquals(BulkImportJobStatus::PENDING, BulkImportJobStatus::from('pending'));
        $this->assertEquals(BulkImportJobStatus::IN_PROGRESS, BulkImportJobStatus::from('in_progress'));
        $this->assertEquals(BulkImportJobStatus::COMPLETED, BulkImportJobStatus::from('completed'));
        $this->assertEquals(BulkImportJobStatus::STOPPED, BulkImportJobStatus::from('stopped'));
        $this->assertEquals(BulkImportJobStatus::FAILED, BulkImportJobStatus::from('failed'));
    }

    public function testTryFromInvalidValue(): void
    {
        $this->assertNull(BulkImportJobStatus::tryFrom('invalid'));
        $this->assertNull(BulkImportJobStatus::tryFrom(''));
    }

    public function testFromInvalidValueThrowsException(): void
    {
        $this->expectException(\ValueError::class);
        BulkImportJobStatus::from('invalid');
    }
}
