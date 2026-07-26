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

namespace App\State\Mcp;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use ApiPlatform\State\ProviderInterface;
use App\Services\InfoProviderSystem\DTOs\ProviderInfoDTO;
use App\Services\InfoProviderSystem\ProviderRegistry;

/**
 * Used both as the state processor for the MCP list_info_providers tool and as the state provider for the
 * REST GET /api/info_providers collection endpoint.
 */
class ListInfoProvidersProcessor implements ProcessorInterface, ProviderInterface
{
    public function __construct(
        private readonly ProviderRegistry $providerRegistry,
    ) {
    }

    /**
     * @return ProviderInfoDTO[]
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        return $this->listActiveProviders();
    }

    /**
     * @return ProviderInfoDTO[]
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): array
    {
        return $this->listActiveProviders();
    }

    /**
     * @return ProviderInfoDTO[]
     */
    private function listActiveProviders(): array
    {
        $result = [];

        foreach ($this->providerRegistry->getActiveProviders() as $provider) {
            $result[] = $provider->getProviderInfo();
        }

        return $result;
    }
}
