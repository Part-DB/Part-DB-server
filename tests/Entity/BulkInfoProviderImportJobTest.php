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
use App\Entity\BulkImportJobStatus;
use App\Entity\UserSystem\User;
use PHPUnit\Framework\TestCase;

class BulkInfoProviderImportJobTest extends TestCase
{
    private BulkInfoProviderImportJob $job;
    private User $user;

    protected function setUp(): void
    {
        $this->user = new User();
        $this->user->setName('test_user');
        
        $this->job = new BulkInfoProviderImportJob();
        $this->job->setCreatedBy($this->user);
    }

    public function testConstruct(): void
    {
        $job = new BulkInfoProviderImportJob();
        
        $this->assertInstanceOf(\DateTimeImmutable::class, $job->getCreatedAt());
        $this->assertEquals(BulkImportJobStatus::PENDING, $job->getStatus());
        $this->assertEmpty($job->getPartIds());
        $this->assertEmpty($job->getFieldMappings());
        $this->assertEmpty($job->getSearchResults());
        $this->assertEmpty($job->getProgress());
        $this->assertNull($job->getCompletedAt());
        $this->assertFalse($job->isPrefetchDetails());
    }

    public function testBasicGettersSetters(): void
    {
        $this->job->setName('Test Job');
        $this->assertEquals('Test Job', $this->job->getName());

        $partIds = [1, 2, 3];
        $this->job->setPartIds($partIds);
        $this->assertEquals($partIds, $this->job->getPartIds());

        $fieldMappings = ['field1' => 'provider1', 'field2' => 'provider2'];
        $this->job->setFieldMappings($fieldMappings);
        $this->assertEquals($fieldMappings, $this->job->getFieldMappings());

        $searchResults = [
            1 => ['search_results' => [['name' => 'Part 1']]],
            2 => ['search_results' => [['name' => 'Part 2'], ['name' => 'Part 2 Alt']]]
        ];
        $this->job->setSearchResults($searchResults);
        $this->assertEquals($searchResults, $this->job->getSearchResults());

        $this->job->setPrefetchDetails(true);
        $this->assertTrue($this->job->isPrefetchDetails());

        $this->assertEquals($this->user, $this->job->getCreatedBy());
    }

    public function testStatusTransitions(): void
    {
        $this->assertTrue($this->job->isPending());
        $this->assertFalse($this->job->isInProgress());
        $this->assertFalse($this->job->isCompleted());
        $this->assertFalse($this->job->isFailed());
        $this->assertFalse($this->job->isStopped());

        $this->job->markAsInProgress();
        $this->assertEquals(BulkImportJobStatus::IN_PROGRESS, $this->job->getStatus());
        $this->assertTrue($this->job->isInProgress());
        $this->assertFalse($this->job->isPending());

        $this->job->markAsCompleted();
        $this->assertEquals(BulkImportJobStatus::COMPLETED, $this->job->getStatus());
        $this->assertTrue($this->job->isCompleted());
        $this->assertNotNull($this->job->getCompletedAt());

        $job2 = new BulkInfoProviderImportJob();
        $job2->markAsFailed();
        $this->assertEquals(BulkImportJobStatus::FAILED, $job2->getStatus());
        $this->assertTrue($job2->isFailed());
        $this->assertNotNull($job2->getCompletedAt());

        $job3 = new BulkInfoProviderImportJob();
        $job3->markAsStopped();
        $this->assertEquals(BulkImportJobStatus::STOPPED, $job3->getStatus());
        $this->assertTrue($job3->isStopped());
        $this->assertNotNull($job3->getCompletedAt());
    }

    public function testCanBeStopped(): void
    {
        $this->assertTrue($this->job->canBeStopped());

        $this->job->markAsInProgress();
        $this->assertTrue($this->job->canBeStopped());

        $this->job->markAsCompleted();
        $this->assertFalse($this->job->canBeStopped());

        $this->job->setStatus(BulkImportJobStatus::FAILED);
        $this->assertFalse($this->job->canBeStopped());

        $this->job->setStatus(BulkImportJobStatus::STOPPED);
        $this->assertFalse($this->job->canBeStopped());
    }

    public function testPartCount(): void
    {
        $this->assertEquals(0, $this->job->getPartCount());

        $this->job->setPartIds([1, 2, 3, 4, 5]);
        $this->assertEquals(5, $this->job->getPartCount());
    }

    public function testResultCount(): void
    {
        $this->assertEquals(0, $this->job->getResultCount());

        $searchResults = [
            1 => ['search_results' => [['name' => 'Part 1']]],
            2 => ['search_results' => [['name' => 'Part 2'], ['name' => 'Part 2 Alt']]],
            3 => ['search_results' => []]
        ];
        $this->job->setSearchResults($searchResults);
        $this->assertEquals(3, $this->job->getResultCount());
    }

    public function testPartProgressTracking(): void
    {
        $this->job->setPartIds([1, 2, 3, 4]);

        $this->assertFalse($this->job->isPartCompleted(1));
        $this->assertFalse($this->job->isPartSkipped(1));

        $this->job->markPartAsCompleted(1);
        $this->assertTrue($this->job->isPartCompleted(1));
        $this->assertFalse($this->job->isPartSkipped(1));

        $this->job->markPartAsSkipped(2, 'Not found');
        $this->assertFalse($this->job->isPartCompleted(2));
        $this->assertTrue($this->job->isPartSkipped(2));

        $this->job->markPartAsPending(1);
        $this->assertFalse($this->job->isPartCompleted(1));
        $this->assertFalse($this->job->isPartSkipped(1));
    }

    public function testProgressCounts(): void
    {
        $this->job->setPartIds([1, 2, 3, 4, 5]);

        $this->assertEquals(0, $this->job->getCompletedPartsCount());
        $this->assertEquals(0, $this->job->getSkippedPartsCount());

        $this->job->markPartAsCompleted(1);
        $this->job->markPartAsCompleted(2);
        $this->job->markPartAsSkipped(3, 'Error');

        $this->assertEquals(2, $this->job->getCompletedPartsCount());
        $this->assertEquals(1, $this->job->getSkippedPartsCount());
    }

    public function testProgressPercentage(): void
    {
        $emptyJob = new BulkInfoProviderImportJob();
        $this->assertEquals(100.0, $emptyJob->getProgressPercentage());

        $this->job->setPartIds([1, 2, 3, 4, 5]);
        $this->assertEquals(0.0, $this->job->getProgressPercentage());

        $this->job->markPartAsCompleted(1);
        $this->job->markPartAsCompleted(2);
        $this->assertEquals(40.0, $this->job->getProgressPercentage());

        $this->job->markPartAsSkipped(3, 'Error');
        $this->assertEquals(60.0, $this->job->getProgressPercentage());

        $this->job->markPartAsCompleted(4);
        $this->job->markPartAsCompleted(5);
        $this->assertEquals(100.0, $this->job->getProgressPercentage());
    }

    public function testIsAllPartsCompleted(): void
    {
        $emptyJob = new BulkInfoProviderImportJob();
        $this->assertTrue($emptyJob->isAllPartsCompleted());

        $this->job->setPartIds([1, 2, 3]);
        $this->assertFalse($this->job->isAllPartsCompleted());

        $this->job->markPartAsCompleted(1);
        $this->assertFalse($this->job->isAllPartsCompleted());

        $this->job->markPartAsCompleted(2);
        $this->job->markPartAsSkipped(3, 'Error');
        $this->assertTrue($this->job->isAllPartsCompleted());
    }

    public function testDisplayNameMethods(): void
    {
        $this->job->setPartIds([1, 2, 3]);
        
        $this->assertEquals('info_providers.bulk_import.job_name_template', $this->job->getDisplayNameKey());
        $this->assertEquals(['%count%' => 3], $this->job->getDisplayNameParams());
    }

    public function testFormattedTimestamp(): void
    {
        $timestampRegex = '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/';
        $this->assertMatchesRegularExpression($timestampRegex, $this->job->getFormattedTimestamp());
    }

    public function testProgressDataStructure(): void
    {
        $this->job->markPartAsCompleted(1);
        $this->job->markPartAsSkipped(2, 'Test reason');

        $progress = $this->job->getProgress();
        
        $this->assertArrayHasKey(1, $progress);
        $this->assertEquals('completed', $progress[1]['status']);
        $this->assertArrayHasKey('completed_at', $progress[1]);

        $this->assertArrayHasKey(2, $progress);
        $this->assertEquals('skipped', $progress[2]['status']);
        $this->assertEquals('Test reason', $progress[2]['reason']);
        $this->assertArrayHasKey('completed_at', $progress[2]);
    }

    public function testCompletedAtTimestamp(): void
    {
        $this->assertNull($this->job->getCompletedAt());

        $beforeCompletion = new \DateTimeImmutable();
        $this->job->markAsCompleted();
        $afterCompletion = new \DateTimeImmutable();

        $completedAt = $this->job->getCompletedAt();
        $this->assertNotNull($completedAt);
        $this->assertGreaterThanOrEqual($beforeCompletion, $completedAt);
        $this->assertLessThanOrEqual($afterCompletion, $completedAt);

        $customTime = new \DateTimeImmutable('2023-01-01 12:00:00');
        $this->job->setCompletedAt($customTime);
        $this->assertEquals($customTime, $this->job->getCompletedAt());
    }
}