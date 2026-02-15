<?php

declare(strict_types=1);

/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2024 Jan Böhmer (https://github.com/jbtronics)
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

namespace App\DataTables\DTO;

use App\Entity\Parts\ManufacturingStatus;

/**
 * Lightweight DTO for PartsDataTable containing only data needed for table rendering.
 * This avoids loading full Part entities with all relationships, significantly improving performance.
 * 
 * The DTO is populated directly from optimized query results, selecting only required fields.
 */
class PartDTO
{
    /** @var PartLotDTO[] */
    private array $partLots = [];
    
    /** @var int[] */
    private array $attachmentIds = [];
    
    /** @var array<array{id: int, name: string}> */
    private array $projects = [];

    public function __construct(
        // Core Part fields
        public readonly int $id,
        public readonly string $name,
        public readonly ?string $ipn,
        public readonly ?string $description,
        public readonly float $minamount,
        public readonly ?string $manufacturer_product_number,
        public readonly ?float $mass,
        public readonly ?string $gtin,
        public readonly string $tags,
        public readonly bool $favorite,
        public readonly bool $needs_review,
        public readonly ?\DateTimeInterface $addedDate,
        public readonly ?\DateTimeInterface $lastModified,
        public readonly ?ManufacturingStatus $manufacturing_status,
        
        // Related entity IDs and names (pre-joined for display)
        public readonly ?int $category_id,
        public readonly ?string $category_name,
        public readonly ?int $footprint_id,
        public readonly ?string $footprint_name,
        public readonly ?int $manufacturer_id,
        public readonly ?string $manufacturer_name,
        public readonly ?int $partUnit_id,
        public readonly ?string $partUnit_name,
        public readonly ?string $partUnit_unit,
        public readonly ?int $partCustomState_id,
        public readonly ?string $partCustomState_name,
        public readonly ?int $master_picture_attachment_id,
        public readonly ?string $master_picture_attachment_filename,
        public readonly ?string $master_picture_attachment_name,
        public readonly ?int $footprint_attachment_id,
        public readonly ?int $builtProject_id,
        public readonly ?string $builtProject_name,
        
        // Computed/aggregated fields
        public readonly float $amountSum,
        public readonly float $expiredAmountSum,
        public readonly bool $hasUnknownAmount,
    ) {
    }

    // Compatibility methods that match Part entity interface

    public function getId(): int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function isFavorite(): bool
    {
        return $this->favorite;
    }

    public function isNeedsReview(): bool
    {
        return $this->needs_review;
    }

    public function isNotEnoughInstock(): bool
    {
        return $this->amountSum < $this->minamount;
    }

    public function isAmountUnknown(): bool
    {
        return $this->hasUnknownAmount;
    }

    public function getAmountSum(): float
    {
        return $this->amountSum;
    }

    public function getExpiredAmountSum(): float
    {
        return $this->expiredAmountSum;
    }

    /**
     * Get built project as a simple object compatible with renderer needs
     */
    public function getBuiltProject(): ?object
    {
        if ($this->builtProject_id === null) {
            return null;
        }

        return new class($this->builtProject_id, $this->builtProject_name) {
            public function __construct(
                private readonly int $id,
                private readonly ?string $name,
            ) {
            }

            public function getId(): int
            {
                return $this->id;
            }

            public function getName(): ?string
            {
                return $this->name;
            }
        };
    }

    /**
     * Get part unit as a simple object compatible with renderer needs
     */
    public function getPartUnit(): ?object
    {
        if ($this->partUnit_id === null) {
            return null;
        }

        return new class($this->partUnit_id, $this->partUnit_name, $this->partUnit_unit) {
            public function __construct(
                private readonly int $id,
                private readonly ?string $name,
                private readonly ?string $unit,
            ) {
            }

            public function getId(): int
            {
                return $this->id;
            }

            public function getName(): ?string
            {
                return $this->name;
            }

            public function getUnit(): ?string
            {
                return $this->unit;
            }
        };
    }

    /**
     * Get category as a simple object compatible with renderer needs
     */
    public function getCategory(): ?object
    {
        if ($this->category_id === null) {
            return null;
        }

        return new class($this->category_id, $this->category_name) {
            public function __construct(
                private readonly int $id,
                private readonly ?string $name,
            ) {
            }

            public function getId(): int
            {
                return $this->id;
            }

            public function getName(): ?string
            {
                return $this->name;
            }
        };
    }

    /**
     * Get footprint as a simple object compatible with renderer needs
     */
    public function getFootprint(): ?object
    {
        if ($this->footprint_id === null) {
            return null;
        }

        return new class($this->footprint_id, $this->footprint_name) {
            public function __construct(
                private readonly int $id,
                private readonly ?string $name,
            ) {
            }

            public function getId(): int
            {
                return $this->id;
            }

            public function getName(): ?string
            {
                return $this->name;
            }
        };
    }

    /**
     * Get manufacturer as a simple object compatible with renderer needs
     */
    public function getManufacturer(): ?object
    {
        if ($this->manufacturer_id === null) {
            return null;
        }

        return new class($this->manufacturer_id, $this->manufacturer_name) {
            public function __construct(
                private readonly int $id,
                private readonly ?string $name,
            ) {
            }

            public function getId(): int
            {
                return $this->id;
            }

            public function getName(): ?string
            {
                return $this->name;
            }
        };
    }

    /**
     * Get part custom state as a simple object compatible with renderer needs
     */
    public function getPartCustomState(): ?object
    {
        if ($this->partCustomState_id === null) {
            return null;
        }

        return new class($this->partCustomState_id, $this->partCustomState_name) {
            public function __construct(
                private readonly int $id,
                private readonly ?string $name,
            ) {
            }

            public function getId(): int
            {
                return $this->id;
            }

            public function getName(): ?string
            {
                return $this->name;
            }
        };
    }

    /**
     * Get master picture attachment as a simple object for rendering
     */
    public function getMasterPictureAttachment(): ?object
    {
        if ($this->master_picture_attachment_id === null) {
            return null;
        }

        return new class($this->master_picture_attachment_id, $this->master_picture_attachment_name, $this->master_picture_attachment_filename) {
            public function __construct(
                private readonly int $id,
                private readonly ?string $name,
                private readonly ?string $filename,
            ) {
            }

            public function getId(): int
            {
                return $this->id;
            }

            public function getName(): ?string
            {
                return $this->name;
            }

            public function getFilename(): ?string
            {
                return $this->filename;
            }
        };
    }

    /**
     * Get footprint's master picture attachment
     */
    public function getFootprintAttachment(): ?object
    {
        if ($this->footprint_attachment_id === null) {
            return null;
        }

        return new class($this->footprint_attachment_id) {
            public function __construct(
                private readonly int $id,
            ) {
            }

            public function getId(): int
            {
                return $this->id;
            }
        };
    }

    /**
     * @return PartLotDTO[]
     */
    public function getPartLots(): array
    {
        return $this->partLots;
    }

    /**
     * @param PartLotDTO[] $partLots
     */
    public function setPartLots(array $partLots): void
    {
        $this->partLots = $partLots;
    }

    /**
     * Get attachment IDs for rendering
     * @return int[]
     */
    public function getAttachments(): array
    {
        return $this->attachmentIds;
    }

    /**
     * @param int[] $attachmentIds
     */
    public function setAttachments(array $attachmentIds): void
    {
        $this->attachmentIds = $attachmentIds;
    }

    /**
     * Get projects where this part is used
     * @return array<array{id: int, name: string}>
     */
    public function getProjects(): array
    {
        return $this->projects;
    }

    /**
     * @param array<array{id: int, name: string}> $projects
     */
    public function setProjects(array $projects): void
    {
        $this->projects = $projects;
    }
}
