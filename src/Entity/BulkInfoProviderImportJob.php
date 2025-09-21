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
use App\Entity\Parts\Part;
use App\Entity\UserSystem\User;
use App\Services\InfoProviderSystem\DTOs\BulkSearchResponseDTO;
use App\Services\InfoProviderSystem\DTOs\FieldMappingDTO;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\EntityManagerInterface;

#[ORM\Entity]
#[ORM\Table(name: 'bulk_info_provider_import_jobs')]
class BulkInfoProviderImportJob extends AbstractDBElement
{
    #[ORM\Column(type: Types::TEXT)]
    private string $name = '';

    #[ORM\Column(type: Types::JSON)]
    private array $fieldMappings = [];

    /**
     * @var FieldMappingDTO[] The deserialized field mappings DTOs, cached for performance
     */
    private ?array $fieldMappingsDTO = null;

    #[ORM\Column(type: Types::JSON)]
    private array $searchResults = [];

    /**
     * @var BulkSearchResponseDTO|null The deserialized search results DTO, cached for performance
     */
    private ?BulkSearchResponseDTO $searchResultsDTO = null;

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
    private ?User $createdBy = null;

    /** @var Collection<int, BulkInfoProviderImportJobPart> */
    #[ORM\OneToMany(targetEntity: BulkInfoProviderImportJobPart::class, mappedBy: 'job', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $jobParts;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->jobParts = new ArrayCollection();
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

    public function getJobParts(): Collection
    {
        return $this->jobParts;
    }

    public function addJobPart(BulkInfoProviderImportJobPart $jobPart): self
    {
        if (!$this->jobParts->contains($jobPart)) {
            $this->jobParts->add($jobPart);
            $jobPart->setJob($this);
        }
        return $this;
    }

    public function removeJobPart(BulkInfoProviderImportJobPart $jobPart): self
    {
        if ($this->jobParts->removeElement($jobPart)) {
            if ($jobPart->getJob() === $this) {
                $jobPart->setJob(null);
            }
        }
        return $this;
    }

    public function getPartIds(): array
    {
        return $this->jobParts->map(fn($jobPart) => $jobPart->getPart()->getId())->toArray();
    }

    public function setPartIds(array $partIds): self
    {
        // This method is kept for backward compatibility but should be replaced with addJobPart
        // Clear existing job parts
        $this->jobParts->clear();

        // Add new job parts (this would need the actual Part entities, not just IDs)
        // This is a simplified implementation - in practice, you'd want to pass Part entities
        return $this;
    }

    public function addPart(Part $part): self
    {
        $jobPart = new BulkInfoProviderImportJobPart($this, $part);
        $this->addJobPart($jobPart);
        return $this;
    }

    /**
     * @return FieldMappingDTO[] The deserialized field mappings
     */
    public function getFieldMappings(): array
    {
        if ($this->fieldMappingsDTO === null) {
            // Lazy load the DTOs from the raw JSON data
            $this->fieldMappingsDTO = array_map(
                static fn($data) => FieldMappingDTO::fromSerializableArray($data),
                $this->fieldMappings
            );
        }

        return $this->fieldMappingsDTO;
    }

    /**
     * @param  FieldMappingDTO[]  $fieldMappings
     * @return $this
     */
    public function setFieldMappings(array $fieldMappings): self
    {
        //Ensure that we are dealing with the objects here
        if (count($fieldMappings) > 0 && !$fieldMappings[0] instanceof FieldMappingDTO) {
            throw new \InvalidArgumentException('Expected an array of FieldMappingDTO objects');
        }

        $this->fieldMappingsDTO = $fieldMappings;

        $this->fieldMappings = array_map(
            static fn(FieldMappingDTO $dto) => $dto->toSerializableArray(),
            $fieldMappings
        );
        return $this;
    }

    public function getSearchResultsRaw(): array
    {
        return $this->searchResults;
    }

    public function setSearchResultsRaw(array $searchResults): self
    {
        $this->searchResults = $searchResults;
        return $this;
    }

    public function setSearchResults(BulkSearchResponseDTO $searchResponse): self
    {
        $this->searchResultsDTO = $searchResponse;
        $this->searchResults = $searchResponse->toSerializableRepresentation();
        return $this;
    }

    public function getSearchResults(EntityManagerInterface $entityManager): BulkSearchResponseDTO
    {
        if ($this->searchResultsDTO === null) {
            // Lazy load the DTO from the raw JSON data
            $this->searchResultsDTO = BulkSearchResponseDTO::fromSerializableRepresentation($this->searchResults, $entityManager);
        }
        return $this->searchResultsDTO;
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
        $progress = [];
        foreach ($this->jobParts as $jobPart) {
            $progressData = [
                'status' => $jobPart->getStatus()->value
            ];

            // Only include completed_at if it's not null
            if ($jobPart->getCompletedAt() !== null) {
                $progressData['completed_at'] = $jobPart->getCompletedAt()->format('c');
            }

            // Only include reason if it's not null
            if ($jobPart->getReason() !== null) {
                $progressData['reason'] = $jobPart->getReason();
            }

            $progress[$jobPart->getPart()->getId()] = $progressData;
        }
        return $progress;
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
        return $this->jobParts->count();
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
        $jobPart = $this->findJobPartByPartId($partId);
        if ($jobPart) {
            $jobPart->markAsCompleted();
        }
        return $this;
    }

    public function markPartAsSkipped(int $partId, string $reason = ''): self
    {
        $jobPart = $this->findJobPartByPartId($partId);
        if ($jobPart) {
            $jobPart->markAsSkipped($reason);
        }
        return $this;
    }

    public function markPartAsPending(int $partId): self
    {
        $jobPart = $this->findJobPartByPartId($partId);
        if ($jobPart) {
            $jobPart->markAsPending();
        }
        return $this;
    }

    public function isPartCompleted(int $partId): bool
    {
        $jobPart = $this->findJobPartByPartId($partId);
        return $jobPart ? $jobPart->isCompleted() : false;
    }

    public function isPartSkipped(int $partId): bool
    {
        $jobPart = $this->findJobPartByPartId($partId);
        return $jobPart ? $jobPart->isSkipped() : false;
    }

    public function getCompletedPartsCount(): int
    {
        return $this->jobParts->filter(fn($jobPart) => $jobPart->isCompleted())->count();
    }

    public function getSkippedPartsCount(): int
    {
        return $this->jobParts->filter(fn($jobPart) => $jobPart->isSkipped())->count();
    }

    private function findJobPartByPartId(int $partId): ?BulkInfoProviderImportJobPart
    {
        foreach ($this->jobParts as $jobPart) {
            if ($jobPart->getPart()->getId() === $partId) {
                return $jobPart;
            }
        }
        return null;
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
