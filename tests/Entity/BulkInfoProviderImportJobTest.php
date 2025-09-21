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
use App\Entity\InfoProviderSystem\BulkInfoProviderImportJob;
use App\Entity\UserSystem\User;
use App\Services\InfoProviderSystem\DTOs\BulkSearchFieldMappingDTO;
use App\Services\InfoProviderSystem\DTOs\BulkSearchPartResultDTO;
use App\Services\InfoProviderSystem\DTOs\BulkSearchResponseDTO;
use App\Services\InfoProviderSystem\DTOs\SearchResultDTO;
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

    private function createMockPart(int $id): \App\Entity\Parts\Part
    {
        $part = $this->createMock(\App\Entity\Parts\Part::class);
        $part->method('getId')->willReturn($id);
        $part->method('getName')->willReturn("Test Part {$id}");
        return $part;
    }

    public function testConstruct(): void
    {
        $job = new BulkInfoProviderImportJob();

        $this->assertInstanceOf(\DateTimeImmutable::class, $job->getCreatedAt());
        $this->assertEquals(BulkImportJobStatus::PENDING, $job->getStatus());
        $this->assertEmpty($job->getPartIds());
        $this->assertEmpty($job->getFieldMappings());
        $this->assertEmpty($job->getSearchResultsRaw());
        $this->assertEmpty($job->getProgress());
        $this->assertNull($job->getCompletedAt());
        $this->assertFalse($job->isPrefetchDetails());
    }

    public function testBasicGettersSetters(): void
    {
        $this->job->setName('Test Job');
        $this->assertEquals('Test Job', $this->job->getName());

        // Test with actual parts - this is what actually works
        $parts = [$this->createMockPart(1), $this->createMockPart(2), $this->createMockPart(3)];
        foreach ($parts as $part) {
            $this->job->addPart($part);
        }
        $this->assertEquals([1, 2, 3], $this->job->getPartIds());

        $fieldMappings = [new BulkSearchFieldMappingDTO(field: 'field1', providers: ['provider1', 'provider2'])];
        $this->job->setFieldMappings($fieldMappings);
        $this->assertEquals($fieldMappings, $this->job->getFieldMappings());

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

        // Test with actual parts - setPartIds doesn't actually add parts
        $parts = [
            $this->createMockPart(1),
            $this->createMockPart(2),
            $this->createMockPart(3),
            $this->createMockPart(4),
            $this->createMockPart(5)
        ];
        foreach ($parts as $part) {
            $this->job->addPart($part);
        }
        $this->assertEquals(5, $this->job->getPartCount());
    }

    public function testResultCount(): void
    {
        $this->assertEquals(0, $this->job->getResultCount());

        $searchResults = new BulkSearchResponseDTO([
            new \App\Services\InfoProviderSystem\DTOs\BulkSearchPartResultsDTO(
                part: $this->createMockPart(1),
                searchResults: [new BulkSearchPartResultDTO(searchResult: new SearchResultDTO(provider_key: 'dummy', provider_id: '1234', name: 'Part 1', description: 'A part'))]
            ),
            new \App\Services\InfoProviderSystem\DTOs\BulkSearchPartResultsDTO(
                part: $this->createMockPart(2),
                searchResults: [new BulkSearchPartResultDTO(searchResult: new SearchResultDTO(provider_key: 'dummy', provider_id: '1234', name: 'Part 2', description: 'A part')),
                new BulkSearchPartResultDTO(searchResult: new SearchResultDTO(provider_key: 'dummy', provider_id: '5678', name: 'Part 2 Alt', description: 'Another part'))]
            ),
            new \App\Services\InfoProviderSystem\DTOs\BulkSearchPartResultsDTO(
                part: $this->createMockPart(3),
                searchResults: []
            )
        ]);

        $this->job->setSearchResults($searchResults);
        $this->assertEquals(3, $this->job->getResultCount());
    }

    public function testPartProgressTracking(): void
    {
        // Test with actual parts - setPartIds doesn't actually add parts
        $parts = [
            $this->createMockPart(1),
            $this->createMockPart(2),
            $this->createMockPart(3),
            $this->createMockPart(4)
        ];
        foreach ($parts as $part) {
            $this->job->addPart($part);
        }

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
        // Test with actual parts - setPartIds doesn't actually add parts
        $parts = [
            $this->createMockPart(1),
            $this->createMockPart(2),
            $this->createMockPart(3),
            $this->createMockPart(4),
            $this->createMockPart(5)
        ];
        foreach ($parts as $part) {
            $this->job->addPart($part);
        }

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

        // Test with actual parts - setPartIds doesn't actually add parts
        $parts = [
            $this->createMockPart(1),
            $this->createMockPart(2),
            $this->createMockPart(3),
            $this->createMockPart(4),
            $this->createMockPart(5)
        ];
        foreach ($parts as $part) {
            $this->job->addPart($part);
        }

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

        // Test with actual parts - setPartIds doesn't actually add parts
        $parts = [
            $this->createMockPart(1),
            $this->createMockPart(2),
            $this->createMockPart(3)
        ];
        foreach ($parts as $part) {
            $this->job->addPart($part);
        }

        $this->assertFalse($this->job->isAllPartsCompleted());

        $this->job->markPartAsCompleted(1);
        $this->assertFalse($this->job->isAllPartsCompleted());

        $this->job->markPartAsCompleted(2);
        $this->job->markPartAsSkipped(3, 'Error');
        $this->assertTrue($this->job->isAllPartsCompleted());
    }

    public function testDisplayNameMethods(): void
    {
        // Test with actual parts - setPartIds doesn't actually add parts
        $parts = [
            $this->createMockPart(1),
            $this->createMockPart(2),
            $this->createMockPart(3)
        ];
        foreach ($parts as $part) {
            $this->job->addPart($part);
        }

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
        $parts = [
            $this->createMockPart(1),
            $this->createMockPart(2),
            $this->createMockPart(3)
        ];
        foreach ($parts as $part) {
            $this->job->addPart($part);
        }

        $this->job->markPartAsCompleted(1);
        $this->job->markPartAsSkipped(2, 'Test reason');

        $progress = $this->job->getProgress();

        // The progress array should have keys for all part IDs, even if not completed/skipped
        $this->assertArrayHasKey(1, $progress, 'Progress should contain key for part 1');
        $this->assertArrayHasKey(2, $progress, 'Progress should contain key for part 2');
        $this->assertArrayHasKey(3, $progress, 'Progress should contain key for part 3');

        // Part 1: completed
        $this->assertEquals('completed', $progress[1]['status']);
        $this->assertArrayHasKey('completed_at', $progress[1]);
        $this->assertArrayNotHasKey('reason', $progress[1]);

        // Part 2: skipped
        $this->assertEquals('skipped', $progress[2]['status']);
        $this->assertEquals('Test reason', $progress[2]['reason']);
        $this->assertArrayHasKey('completed_at', $progress[2]);

        // Part 3: should be present but not completed/skipped
        $this->assertEquals('pending', $progress[3]['status']);
        $this->assertArrayNotHasKey('completed_at', $progress[3]);
        $this->assertArrayNotHasKey('reason', $progress[3]);
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
