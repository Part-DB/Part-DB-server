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

use App\Entity\BulkInfoProviderImportJob;
use App\Entity\BulkInfoProviderImportJobPart;
use App\Entity\BulkImportPartStatus;
use App\Entity\Parts\Part;
use PHPUnit\Framework\TestCase;

class BulkInfoProviderImportJobPartTest extends TestCase
{
    private BulkInfoProviderImportJob $job;
    private Part $part;
    private BulkInfoProviderImportJobPart $jobPart;

    protected function setUp(): void
    {
        $this->job = $this->createMock(BulkInfoProviderImportJob::class);
        $this->part = $this->createMock(Part::class);
        
        $this->jobPart = new BulkInfoProviderImportJobPart($this->job, $this->part);
    }

    public function testConstructor(): void
    {
        $this->assertSame($this->job, $this->jobPart->getJob());
        $this->assertSame($this->part, $this->jobPart->getPart());
        $this->assertEquals(BulkImportPartStatus::PENDING, $this->jobPart->getStatus());
        $this->assertNull($this->jobPart->getReason());
        $this->assertNull($this->jobPart->getCompletedAt());
    }

    public function testGetAndSetJob(): void
    {
        $newJob = $this->createMock(BulkInfoProviderImportJob::class);
        
        $result = $this->jobPart->setJob($newJob);
        
        $this->assertSame($this->jobPart, $result);
        $this->assertSame($newJob, $this->jobPart->getJob());
    }

    public function testGetAndSetPart(): void
    {
        $newPart = $this->createMock(Part::class);
        
        $result = $this->jobPart->setPart($newPart);
        
        $this->assertSame($this->jobPart, $result);
        $this->assertSame($newPart, $this->jobPart->getPart());
    }

    public function testGetAndSetStatus(): void
    {
        $result = $this->jobPart->setStatus(BulkImportPartStatus::COMPLETED);
        
        $this->assertSame($this->jobPart, $result);
        $this->assertEquals(BulkImportPartStatus::COMPLETED, $this->jobPart->getStatus());
    }

    public function testGetAndSetReason(): void
    {
        $reason = 'Test reason';
        
        $result = $this->jobPart->setReason($reason);
        
        $this->assertSame($this->jobPart, $result);
        $this->assertEquals($reason, $this->jobPart->getReason());
    }

    public function testGetAndSetCompletedAt(): void
    {
        $completedAt = new \DateTimeImmutable();
        
        $result = $this->jobPart->setCompletedAt($completedAt);
        
        $this->assertSame($this->jobPart, $result);
        $this->assertSame($completedAt, $this->jobPart->getCompletedAt());
    }

    public function testMarkAsCompleted(): void
    {
        $beforeTime = new \DateTimeImmutable();
        
        $result = $this->jobPart->markAsCompleted();
        
        $afterTime = new \DateTimeImmutable();
        
        $this->assertSame($this->jobPart, $result);
        $this->assertEquals(BulkImportPartStatus::COMPLETED, $this->jobPart->getStatus());
        $this->assertInstanceOf(\DateTimeImmutable::class, $this->jobPart->getCompletedAt());
        $this->assertGreaterThanOrEqual($beforeTime, $this->jobPart->getCompletedAt());
        $this->assertLessThanOrEqual($afterTime, $this->jobPart->getCompletedAt());
    }

    public function testMarkAsSkipped(): void
    {
        $reason = 'Skipped for testing';
        $beforeTime = new \DateTimeImmutable();
        
        $result = $this->jobPart->markAsSkipped($reason);
        
        $afterTime = new \DateTimeImmutable();
        
        $this->assertSame($this->jobPart, $result);
        $this->assertEquals(BulkImportPartStatus::SKIPPED, $this->jobPart->getStatus());
        $this->assertEquals($reason, $this->jobPart->getReason());
        $this->assertInstanceOf(\DateTimeImmutable::class, $this->jobPart->getCompletedAt());
        $this->assertGreaterThanOrEqual($beforeTime, $this->jobPart->getCompletedAt());
        $this->assertLessThanOrEqual($afterTime, $this->jobPart->getCompletedAt());
    }

    public function testMarkAsSkippedWithoutReason(): void
    {
        $result = $this->jobPart->markAsSkipped();
        
        $this->assertSame($this->jobPart, $result);
        $this->assertEquals(BulkImportPartStatus::SKIPPED, $this->jobPart->getStatus());
        $this->assertEquals('', $this->jobPart->getReason());
        $this->assertInstanceOf(\DateTimeImmutable::class, $this->jobPart->getCompletedAt());
    }

    public function testMarkAsFailed(): void
    {
        $reason = 'Failed for testing';
        $beforeTime = new \DateTimeImmutable();
        
        $result = $this->jobPart->markAsFailed($reason);
        
        $afterTime = new \DateTimeImmutable();
        
        $this->assertSame($this->jobPart, $result);
        $this->assertEquals(BulkImportPartStatus::FAILED, $this->jobPart->getStatus());
        $this->assertEquals($reason, $this->jobPart->getReason());
        $this->assertInstanceOf(\DateTimeImmutable::class, $this->jobPart->getCompletedAt());
        $this->assertGreaterThanOrEqual($beforeTime, $this->jobPart->getCompletedAt());
        $this->assertLessThanOrEqual($afterTime, $this->jobPart->getCompletedAt());
    }

    public function testMarkAsFailedWithoutReason(): void
    {
        $result = $this->jobPart->markAsFailed();
        
        $this->assertSame($this->jobPart, $result);
        $this->assertEquals(BulkImportPartStatus::FAILED, $this->jobPart->getStatus());
        $this->assertEquals('', $this->jobPart->getReason());
        $this->assertInstanceOf(\DateTimeImmutable::class, $this->jobPart->getCompletedAt());
    }

    public function testMarkAsPending(): void
    {
        // First mark as completed to have something to reset
        $this->jobPart->markAsCompleted();
        
        $result = $this->jobPart->markAsPending();
        
        $this->assertSame($this->jobPart, $result);
        $this->assertEquals(BulkImportPartStatus::PENDING, $this->jobPart->getStatus());
        $this->assertNull($this->jobPart->getReason());
        $this->assertNull($this->jobPart->getCompletedAt());
    }

    public function testIsPending(): void
    {
        $this->assertTrue($this->jobPart->isPending());
        
        $this->jobPart->setStatus(BulkImportPartStatus::COMPLETED);
        $this->assertFalse($this->jobPart->isPending());
        
        $this->jobPart->setStatus(BulkImportPartStatus::SKIPPED);
        $this->assertFalse($this->jobPart->isPending());
        
        $this->jobPart->setStatus(BulkImportPartStatus::FAILED);
        $this->assertFalse($this->jobPart->isPending());
    }

    public function testIsCompleted(): void
    {
        $this->assertFalse($this->jobPart->isCompleted());
        
        $this->jobPart->setStatus(BulkImportPartStatus::COMPLETED);
        $this->assertTrue($this->jobPart->isCompleted());
        
        $this->jobPart->setStatus(BulkImportPartStatus::SKIPPED);
        $this->assertFalse($this->jobPart->isCompleted());
        
        $this->jobPart->setStatus(BulkImportPartStatus::FAILED);
        $this->assertFalse($this->jobPart->isCompleted());
    }

    public function testIsSkipped(): void
    {
        $this->assertFalse($this->jobPart->isSkipped());
        
        $this->jobPart->setStatus(BulkImportPartStatus::SKIPPED);
        $this->assertTrue($this->jobPart->isSkipped());
        
        $this->jobPart->setStatus(BulkImportPartStatus::COMPLETED);
        $this->assertFalse($this->jobPart->isSkipped());
        
        $this->jobPart->setStatus(BulkImportPartStatus::FAILED);
        $this->assertFalse($this->jobPart->isSkipped());
    }

    public function testIsFailed(): void
    {
        $this->assertFalse($this->jobPart->isFailed());
        
        $this->jobPart->setStatus(BulkImportPartStatus::FAILED);
        $this->assertTrue($this->jobPart->isFailed());
        
        $this->jobPart->setStatus(BulkImportPartStatus::COMPLETED);
        $this->assertFalse($this->jobPart->isFailed());
        
        $this->jobPart->setStatus(BulkImportPartStatus::SKIPPED);
        $this->assertFalse($this->jobPart->isFailed());
    }

    public function testBulkImportPartStatusEnum(): void
    {
        $this->assertEquals('pending', BulkImportPartStatus::PENDING->value);
        $this->assertEquals('completed', BulkImportPartStatus::COMPLETED->value);
        $this->assertEquals('skipped', BulkImportPartStatus::SKIPPED->value);
        $this->assertEquals('failed', BulkImportPartStatus::FAILED->value);
    }

    public function testStatusTransitions(): void
    {
        // Test pending -> completed
        $this->assertTrue($this->jobPart->isPending());
        $this->jobPart->markAsCompleted();
        $this->assertTrue($this->jobPart->isCompleted());
        
        // Test completed -> pending
        $this->jobPart->markAsPending();
        $this->assertTrue($this->jobPart->isPending());
        
        // Test pending -> skipped
        $this->jobPart->markAsSkipped('Test reason');
        $this->assertTrue($this->jobPart->isSkipped());
        
        // Test skipped -> pending
        $this->jobPart->markAsPending();
        $this->assertTrue($this->jobPart->isPending());
        
        // Test pending -> failed
        $this->jobPart->markAsFailed('Test error');
        $this->assertTrue($this->jobPart->isFailed());
        
        // Test failed -> pending
        $this->jobPart->markAsPending();
        $this->assertTrue($this->jobPart->isPending());
    }

    public function testReasonAndCompletedAtConsistency(): void
    {
        // Initially no reason or completion time
        $this->assertNull($this->jobPart->getReason());
        $this->assertNull($this->jobPart->getCompletedAt());
        
        // After marking as skipped, should have reason and completion time
        $this->jobPart->markAsSkipped('Skipped reason');
        $this->assertEquals('Skipped reason', $this->jobPart->getReason());
        $this->assertInstanceOf(\DateTimeImmutable::class, $this->jobPart->getCompletedAt());
        
        // After marking as pending, reason and completion time should be cleared
        $this->jobPart->markAsPending();
        $this->assertNull($this->jobPart->getReason());
        $this->assertNull($this->jobPart->getCompletedAt());
        
        // After marking as failed, should have reason and completion time
        $this->jobPart->markAsFailed('Failed reason');
        $this->assertEquals('Failed reason', $this->jobPart->getReason());
        $this->assertInstanceOf(\DateTimeImmutable::class, $this->jobPart->getCompletedAt());
        
        // After marking as completed, should have completion time (reason may remain from previous state)
        $this->jobPart->markAsCompleted();
        $this->assertInstanceOf(\DateTimeImmutable::class, $this->jobPart->getCompletedAt());
    }
}