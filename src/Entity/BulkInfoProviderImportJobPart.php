<?php

declare(strict_types=1);

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

namespace App\Entity;

use App\Entity\Base\AbstractDBElement;
use App\Entity\Parts\Part;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

enum BulkImportPartStatus: string
{
    case PENDING = 'pending';
    case COMPLETED = 'completed';
    case SKIPPED = 'skipped';
    case FAILED = 'failed';
}

#[ORM\Entity]
#[ORM\Table(name: 'bulk_info_provider_import_job_parts')]
#[ORM\UniqueConstraint(name: 'unique_job_part', columns: ['job_id', 'part_id'])]
class BulkInfoProviderImportJobPart extends AbstractDBElement
{
    #[ORM\ManyToOne(targetEntity: BulkInfoProviderImportJob::class, inversedBy: 'jobParts')]
    #[ORM\JoinColumn(nullable: false)]
    private BulkInfoProviderImportJob $job;

    #[ORM\ManyToOne(targetEntity: Part::class, inversedBy: 'bulkImportJobParts')]
    #[ORM\JoinColumn(nullable: false)]
    private Part $part;

    #[ORM\Column(type: Types::STRING, length: 20, enumType: BulkImportPartStatus::class)]
    private BulkImportPartStatus $status = BulkImportPartStatus::PENDING;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $reason = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $completedAt = null;

    public function __construct(BulkInfoProviderImportJob $job, Part $part)
    {
        $this->job = $job;
        $this->part = $part;
    }

    public function getJob(): BulkInfoProviderImportJob
    {
        return $this->job;
    }

    public function setJob(?BulkInfoProviderImportJob $job): self
    {
        $this->job = $job;
        return $this;
    }

    public function getPart(): Part
    {
        return $this->part;
    }

    public function setPart(?Part $part): self
    {
        $this->part = $part;
        return $this;
    }

    public function getStatus(): BulkImportPartStatus
    {
        return $this->status;
    }

    public function setStatus(BulkImportPartStatus $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function getReason(): ?string
    {
        return $this->reason;
    }

    public function setReason(?string $reason): self
    {
        $this->reason = $reason;
        return $this;
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

    public function markAsCompleted(): self
    {
        $this->status = BulkImportPartStatus::COMPLETED;
        $this->completedAt = new \DateTimeImmutable();
        return $this;
    }

    public function markAsSkipped(string $reason = ''): self
    {
        $this->status = BulkImportPartStatus::SKIPPED;
        $this->reason = $reason;
        $this->completedAt = new \DateTimeImmutable();
        return $this;
    }

    public function markAsFailed(string $reason = ''): self
    {
        $this->status = BulkImportPartStatus::FAILED;
        $this->reason = $reason;
        $this->completedAt = new \DateTimeImmutable();
        return $this;
    }

    public function markAsPending(): self
    {
        $this->status = BulkImportPartStatus::PENDING;
        $this->reason = null;
        $this->completedAt = null;
        return $this;
    }

    public function isPending(): bool
    {
        return $this->status === BulkImportPartStatus::PENDING;
    }

    public function isCompleted(): bool
    {
        return $this->status === BulkImportPartStatus::COMPLETED;
    }

    public function isSkipped(): bool
    {
        return $this->status === BulkImportPartStatus::SKIPPED;
    }

    public function isFailed(): bool
    {
        return $this->status === BulkImportPartStatus::FAILED;
    }
}