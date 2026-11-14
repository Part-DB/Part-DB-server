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

namespace App\Entity\AgentSystem;

use App\Entity\UserSystem\User;
use App\Repository\AgentJobRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * A job for an agent. Jobs are never re-queued automatically: a claimed job without a result is only shown as stale,
 * so a label is never printed twice by accident.
 */
#[ORM\Entity(repositoryClass: AgentJobRepository::class)]
#[ORM\Table(name: 'agent_jobs')]
#[ORM\Index(columns: ['status'], name: 'agent_jobs_status_idx')]
class AgentJob
{
    public const TYPE_PRINT = 'print';

    /** A claimed job without a result after this time is shown as stale */
    public const STALE_SECONDS = 600;

    /** Finished jobs older than this are deleted */
    public const KEEP_DAYS = 30;

    #[ORM\Id]
    #[ORM\Column(type: Types::INTEGER)]
    #[ORM\GeneratedValue]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Agent::class)]
    #[ORM\JoinColumn(name: 'id_agent', nullable: false, onDelete: 'CASCADE')]
    private ?Agent $agent = null;

    /** @var string The id of the printer, as reported by the agent */
    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $printer = '';

    #[ORM\Column(type: Types::STRING, length: 32)]
    private string $type = self::TYPE_PRINT;

    /**
     * @var array<string, mixed> For print jobs: profile_id, element_type and element_ids
     */
    #[ORM\Column(type: Types::JSON)]
    private array $payload = [];

    #[ORM\Column(type: Types::STRING, length: 16, enumType: AgentJobStatus::class)]
    private AgentJobStatus $status = AgentJobStatus::PENDING;

    #[ORM\Column(type: Types::TEXT)]
    private string $message = '';

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'id_created_by', nullable: true, onDelete: 'SET NULL')]
    private ?User $createdBy = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = $this->createdAt;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAgent(): ?Agent
    {
        return $this->agent;
    }

    public function setAgent(Agent $agent): self
    {
        $this->agent = $agent;
        return $this;
    }

    public function getPrinter(): string
    {
        return $this->printer;
    }

    public function setPrinter(string $printer): self
    {
        $this->printer = $printer;
        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;
        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function getPayload(): array
    {
        return $this->payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function setPayload(array $payload): self
    {
        $this->payload = $payload;
        return $this;
    }

    public function getStatus(): AgentJobStatus
    {
        return $this->status;
    }

    public function setStatus(AgentJobStatus $status): self
    {
        $this->status = $status;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function setMessage(string $message): self
    {
        $this->message = $message;
        return $this;
    }

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?User $createdBy): self
    {
        $this->createdBy = $createdBy;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): self
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    /**
     * True for a claimed job whose agent never reported a result.
     */
    public function isStale(\DateTimeImmutable $now = new \DateTimeImmutable()): bool
    {
        return $this->status === AgentJobStatus::CLAIMED
            && $now->getTimestamp() - $this->updatedAt->getTimestamp() > self::STALE_SECONDS;
    }
}
