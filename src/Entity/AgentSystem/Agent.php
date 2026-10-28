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
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * A program that prints (or otherwise handles) jobs for its owner, e.g. a label print agent on the user's computer.
 * It registers itself with an API token of its owner and identifies itself with a UUID it generates, so renewing
 * the token keeps the agent and its jobs. Part-DB stores the printers it reports without interpreting them.
 */
#[ORM\Entity]
#[ORM\Table(name: 'agents')]
#[ORM\UniqueConstraint(name: 'agents_owner_uuid', columns: ['id_owner', 'uuid'])]
class Agent
{
    /** An agent that hasn't reported for longer than this is shown as offline */
    public const ONLINE_SECONDS = 120;

    #[ORM\Id]
    #[ORM\Column(type: Types::INTEGER)]
    #[ORM\GeneratedValue]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 36)]
    private string $uuid = '';

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $name = '';

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'id_owner', nullable: false, onDelete: 'CASCADE')]
    private ?User $owner = null;

    #[ORM\Column(type: Types::STRING, length: 64)]
    private string $version = '';

    /**
     * @var list<array<string, mixed>> The printers as reported by the agent; each has a string "id" and optionally a "name"
     */
    #[ORM\Column(type: Types::JSON)]
    private array $printers = [];

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $lastSeenAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUuid(): string
    {
        return $this->uuid;
    }

    public function setUuid(string $uuid): self
    {
        $this->uuid = $uuid;
        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getOwner(): ?User
    {
        return $this->owner;
    }

    public function setOwner(User $owner): self
    {
        $this->owner = $owner;
        return $this;
    }

    public function getVersion(): string
    {
        return $this->version;
    }

    public function setVersion(string $version): self
    {
        $this->version = $version;
        return $this;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getPrinters(): array
    {
        return $this->printers;
    }

    /**
     * @param  list<array<string, mixed>>  $printers
     */
    public function setPrinters(array $printers): self
    {
        $this->printers = $printers;
        return $this;
    }

    public function hasPrinter(string $id): bool
    {
        return $this->findPrinter($id) !== null;
    }

    public function getPrinterName(string $id): string
    {
        $name = $this->findPrinter($id)['name'] ?? '';
        return is_string($name) && $name !== '' ? $name : $id;
    }

    private function findPrinter(string $id): ?array
    {
        foreach ($this->printers as $printer) {
            if (($printer['id'] ?? null) === $id) {
                return $printer;
            }
        }
        return null;
    }

    public function getLastSeenAt(): ?\DateTimeImmutable
    {
        return $this->lastSeenAt;
    }

    public function setLastSeenAt(?\DateTimeImmutable $lastSeenAt): self
    {
        $this->lastSeenAt = $lastSeenAt;
        return $this;
    }

    public function isOnline(\DateTimeImmutable $now = new \DateTimeImmutable()): bool
    {
        return $this->lastSeenAt !== null && $now->getTimestamp() - $this->lastSeenAt->getTimestamp() <= self::ONLINE_SECONDS;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
