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

use App\Services\InfoProviderSystem\DTOs\PartDetailDTO;

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
        // TODO: Implement searchByKeyword() method.
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
        // TODO: Implement getDetails() method.
    }
}