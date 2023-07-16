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


namespace App\Services\InfoProviderSystem\Providers;

use App\Services\InfoProviderSystem\DTOs\FileDTO;
use App\Services\InfoProviderSystem\DTOs\PartDetailDTO;
use App\Services\InfoProviderSystem\DTOs\SearchResultDTO;
use Symfony\Component\DependencyInjection\Attribute\When;

/**
 * This is a provider, which is used during tests
 */
#[When(env: 'test')]
class TestProvider implements InfoProviderInterface
{

    public function getProviderInfo(): array
    {
        return [
            'name' => 'Test Provider',
            'description' => 'This is a test provider',
            //'url' => 'https://example.com',
            'disabled_help' => 'This provider is disabled for testing purposes'
        ];
    }

    public function getProviderKey(): string
    {
        return 'test';
    }

    public function isActive(): bool
    {
        return true;
    }

    public function searchByKeyword(string $keyword): array
    {
        return [
            new SearchResultDTO(provider_key: $this->getProviderKey(), provider_id: 'element1', name: 'Element 1', description: 'fd'),
            new SearchResultDTO(provider_key: $this->getProviderKey(), provider_id: 'element2', name: 'Element 2', description: 'fd'),
            new SearchResultDTO(provider_key: $this->getProviderKey(), provider_id: 'element3', name: 'Element 3', description: 'fd'),
        ];
    }

    public function getCapabilities(): array
    {
        return [
            ProviderCapabilities::BASIC,
            ProviderCapabilities::FOOTPRINT,
        ];
    }

    public function getDetails(string $id): PartDetailDTO
    {
        return new PartDetailDTO(
            provider_key: $this->getProviderKey(),
            provider_id: $id,
            name: 'Test Element',
            description: 'fd',
            manufacturer: 'Test Manufacturer',
            mpn: '1234',
            provider_url: 'https://invalid.invalid',
            footprint: 'Footprint',
            notes: 'Notes',
            datasheets: [
                new FileDTO('https://invalid.invalid/invalid.pdf', 'Datasheet')
            ],
            images: [
                new FileDTO('https://invalid.invalid/invalid.png', 'Image')
            ]
        );
    }
}