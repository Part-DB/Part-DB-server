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

namespace App\Entity;

use App\Entity\Base\AbstractDBElement;
use App\Entity\UserSystem\User;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

enum BulkImportJobStatus: string
{
    case PENDING = 'pending';
    case IN_PROGRESS = 'in_progress';
    case COMPLETED = 'completed';
    case STOPPED = 'stopped';
    case FAILED = 'failed';
}

#[ORM\Entity]
#[ORM\Table(name: 'bulk_info_provider_import_jobs')]
class BulkInfoProviderImportJob extends AbstractDBElement
{
    #[ORM\Column(type: Types::TEXT)]
    private string $name = '';

    #[ORM\Column(type: Types::JSON)]
    private array $partIds = [];

    #[ORM\Column(type: Types::JSON)]
    private array $fieldMappings = [];

    #[ORM\Column(type: Types::JSON)]
    private array $searchResults = [];

    #[ORM\Column(type: Types::STRING, length: 20, enumType: BulkImportJobStatus::class)]
    private BulkImportJobStatus $status = BulkImportJobStatus::PENDING;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $completedAt = null;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $prefetchDetails = false;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private User $createdBy;

    #[ORM\Column(type: Types::JSON)]
    private array $progress = [];

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDisplayNameKey(): string
    {
        return 'info_providers.bulk_import.job_name_template';
    }

    public function getDisplayNameParams(): array
    {
        return ['%count%' => $this->getPartCount()];
    }

    public function getFormattedTimestamp(): string
    {
        return $this->createdAt->format('Y-m-d H:i:s');
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getPartIds(): array
    {
        return $this->partIds;
    }

    public function setPartIds(array $partIds): self
    {
        $this->partIds = $partIds;
        return $this;
    }

    public function getFieldMappings(): array
    {
        return $this->fieldMappings;
    }

    public function setFieldMappings(array $fieldMappings): self
    {
        $this->fieldMappings = $fieldMappings;
        return $this;
    }

    public function getSearchResults(): array
    {
        return $this->searchResults;
    }

    public function setSearchResults(array $searchResults): self
    {
        $this->searchResults = $searchResults;
        return $this;
    }

    public function getStatus(): BulkImportJobStatus
    {
        return $this->status;
    }

    public function setStatus(BulkImportJobStatus $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getCompletedAt(): ?\DateTimeImmutable
    {
        return $this->completedAt;
    }

    public function setCompletedAt(?\DateTimeImmutable $completedAt): self
    {
        $this->completedAt = $completedAt;
        return $this;
    }

    public function isPrefetchDetails(): bool
    {
        return $this->prefetchDetails;
    }

    public function setPrefetchDetails(bool $prefetchDetails): self
    {
        $this->prefetchDetails = $prefetchDetails;
        return $this;
    }

    public function getCreatedBy(): User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(User $createdBy): self
    {
        $this->createdBy = $createdBy;
        return $this;
    }

    public function getProgress(): array
    {
        return $this->progress;
    }

    public function setProgress(array $progress): self
    {
        $this->progress = $progress;
        return $this;
    }

    public function markAsCompleted(): self
    {
        $this->status = BulkImportJobStatus::COMPLETED;
        $this->completedAt = new \DateTimeImmutable();
        return $this;
    }

    public function markAsFailed(): self
    {
        $this->status = BulkImportJobStatus::FAILED;
        $this->completedAt = new \DateTimeImmutable();
        return $this;
    }

    public function markAsStopped(): self
    {
        $this->status = BulkImportJobStatus::STOPPED;
        $this->completedAt = new \DateTimeImmutable();
        return $this;
    }

    public function markAsInProgress(): self
    {
        $this->status = BulkImportJobStatus::IN_PROGRESS;
        return $this;
    }

    public function isPending(): bool
    {
        return $this->status === BulkImportJobStatus::PENDING;
    }

    public function isInProgress(): bool
    {
        return $this->status === BulkImportJobStatus::IN_PROGRESS;
    }

    public function isCompleted(): bool
    {
        return $this->status === BulkImportJobStatus::COMPLETED;
    }

    public function isFailed(): bool
    {
        return $this->status === BulkImportJobStatus::FAILED;
    }

    public function isStopped(): bool
    {
        return $this->status === BulkImportJobStatus::STOPPED;
    }

    public function canBeStopped(): bool
    {
        return $this->status === BulkImportJobStatus::PENDING || $this->status === BulkImportJobStatus::IN_PROGRESS;
    }

    public function getPartCount(): int
    {
        return count($this->partIds);
    }

    public function getResultCount(): int
    {
        $count = 0;
        foreach ($this->searchResults as $partResult) {
            $count += count($partResult['search_results'] ?? []);
        }
        return $count;
    }

    public function markPartAsCompleted(int $partId): self
    {
        $this->progress[$partId] = [
            'status' => 'completed',
            'completed_at' => (new \DateTimeImmutable())->format('c')
        ];
        return $this;
    }

    public function markPartAsSkipped(int $partId, string $reason = ''): self
    {
        $this->progress[$partId] = [
            'status' => 'skipped',
            'reason' => $reason,
            'completed_at' => (new \DateTimeImmutable())->format('c')
        ];
        return $this;
    }

    public function markPartAsPending(int $partId): self
    {
        // Remove from progress array to mark as pending
        unset($this->progress[$partId]);
        return $this;
    }

    public function isPartCompleted(int $partId): bool
    {
        return isset($this->progress[$partId]) && $this->progress[$partId]['status'] === 'completed';
    }

    public function isPartSkipped(int $partId): bool
    {
        return isset($this->progress[$partId]) && $this->progress[$partId]['status'] === 'skipped';
    }

    public function getCompletedPartsCount(): int
    {
        return count(array_filter($this->progress, fn($p) => $p['status'] === 'completed'));
    }

    public function getSkippedPartsCount(): int
    {
        return count(array_filter($this->progress, fn($p) => $p['status'] === 'skipped'));
    }

    public function getProgressPercentage(): float
    {
        $total = $this->getPartCount();
        if ($total === 0) {
            return 100.0;
        }

        $completed = $this->getCompletedPartsCount() + $this->getSkippedPartsCount();
        return round(($completed / $total) * 100, 1);
    }

    public function isAllPartsCompleted(): bool
    {
        $total = $this->getPartCount();
        if ($total === 0) {
            return true;
        }

        $completed = $this->getCompletedPartsCount() + $this->getSkippedPartsCount();
        return $completed >= $total;
    }
}