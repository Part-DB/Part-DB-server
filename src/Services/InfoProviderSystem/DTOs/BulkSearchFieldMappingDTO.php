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

use App\Services\InfoProviderSystem\Providers\InfoProviderInterface;

/**
 * Represents a mapping between a part field and the info providers that should search in that field.
 */
readonly class BulkSearchFieldMappingDTO
{
    /** @var string[] $providers Array of provider keys to search with (e.g., ['digikey', 'farnell']) */
    public array $providers;

    /**
     * @param string $field The field to search in (e.g., 'mpn', 'name', or supplier-specific fields like 'digikey_spn')
     * @param string[]|InfoProviderInterface[] $providers Array of provider keys to search with (e.g., ['digikey', 'farnell'])
     * @param int $priority Priority for this field mapping (1-10, lower numbers = higher priority)
     */
    public function __construct(
        public string $field,
        array $providers = [],
        public int $priority = 1
    ) {
        if ($priority < 1 || $priority > 10) {
            throw new \InvalidArgumentException('Priority must be between 1 and 10');
        }

        //Ensure that providers are provided as keys
        foreach ($providers as &$provider) {
            if ($provider instanceof InfoProviderInterface) {
                $provider = $provider->getProviderKey();
            }
            if (!is_string($provider)) {
                throw new \InvalidArgumentException('Providers must be provided as strings or InfoProviderInterface instances');
            }
        }
        unset($provider);
        $this->providers = $providers;
    }

    /**
     * Create a FieldMappingDTO from legacy array format.
     * @param array{field: string, providers: string[], priority?: int} $data
     */
    public static function fromSerializableArray(array $data): self
    {
        return new self(
            field: $data['field'],
            providers: $data['providers'] ?? [],
            priority: $data['priority'] ?? 1
        );
    }

    /**
     * Convert this DTO to the legacy array format for backwards compatibility.
     * @return array{field: string, providers: string[], priority: int}
     */
    public function toSerializableArray(): array
    {
        return [
            'field' => $this->field,
            'providers' => $this->providers,
            'priority' => $this->priority,
        ];
    }

    /**
     * Check if this field mapping is for a supplier part number field.
     */
    public function isSupplierPartNumberField(): bool
    {
        return str_ends_with($this->field, '_spn');
    }

    /**
     * Get the supplier key from a supplier part number field.
     * Returns null if this is not a supplier part number field.
     */
    public function getSupplierKey(): ?string
    {
        if (!$this->isSupplierPartNumberField()) {
            return null;
        }

        return substr($this->field, 0, -4);
    }
}
