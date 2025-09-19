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

namespace App\Services\InfoProviderSystem\DTOs;

use App\Entity\Parts\ManufacturingStatus;
use App\Entity\Parts\Part;

/**
 * Represents a search result from bulk search with additional context information.
 * Uses composition instead of inheritance for better maintainability.
 */
readonly class BulkSearchResultDTO
{
    public function __construct(
        /** The base search result DTO containing provider data */
        public SearchResultDTO $baseDto,
        /** The field that was used to find this result */
        public ?string $sourceField = null,
        /** The actual keyword that was searched for */
        public ?string $sourceKeyword = null,
        /** Local part that matches this search result, if any */
        public ?Part $localPart = null,
        /** Priority for this search result */
        public int $priority = 1
    ) {}

    // Delegation methods for SearchResultDTO properties
    public function getProviderKey(): string
    {
        return $this->baseDto->provider_key;
    }

    public function getProviderId(): string
    {
        return $this->baseDto->provider_id;
    }

    public function getName(): string
    {
        return $this->baseDto->name;
    }

    public function getDescription(): string
    {
        return $this->baseDto->description;
    }

    public function getCategory(): ?string
    {
        return $this->baseDto->category;
    }

    public function getManufacturer(): ?string
    {
        return $this->baseDto->manufacturer;
    }

    public function getMpn(): ?string
    {
        return $this->baseDto->mpn;
    }

    public function getPreviewImageUrl(): ?string
    {
        return $this->baseDto->preview_image_url;
    }

    public function getPreviewImageFile(): ?FileDTO
    {
        return $this->baseDto->preview_image_file;
    }

    public function getManufacturingStatus(): ?ManufacturingStatus
    {
        return $this->baseDto->manufacturing_status;
    }

    public function getProviderUrl(): ?string
    {
        return $this->baseDto->provider_url;
    }

    public function getFootprint(): ?string
    {
        return $this->baseDto->footprint;
    }

    // Backwards compatibility properties for legacy code
    public function __get(string $name): mixed
    {
        return match ($name) {
            'provider_key' => $this->baseDto->provider_key,
            'provider_id' => $this->baseDto->provider_id,
            'name' => $this->baseDto->name,
            'description' => $this->baseDto->description,
            'category' => $this->baseDto->category,
            'manufacturer' => $this->baseDto->manufacturer,
            'mpn' => $this->baseDto->mpn,
            'preview_image_url' => $this->baseDto->preview_image_url,
            'preview_image_file' => $this->baseDto->preview_image_file,
            'manufacturing_status' => $this->baseDto->manufacturing_status,
            'provider_url' => $this->baseDto->provider_url,
            'footprint' => $this->baseDto->footprint,
            default => throw new \InvalidArgumentException("Property '{$name}' does not exist")
        };
    }

    /**
     * Magic isset method for backwards compatibility.
     */
    public function __isset(string $name): bool
    {
        return in_array($name, [
            'provider_key', 'provider_id', 'name', 'description', 'category',
            'manufacturer', 'mpn', 'preview_image_url', 'preview_image_file',
            'manufacturing_status', 'provider_url', 'footprint'
        ], true);
    }
}