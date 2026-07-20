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

namespace App\Services\InfoProviderSystem\DTOs;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\McpToolCollection;
use App\Mcp\DTO\ListInfoProvidersInput;
use App\State\Mcp\ListInfoProvidersProcessor;

/**
 * This DTO represents an available info provider (a distributor or manufacturer catalog which can be
 * searched via search_info_providers / get_info_provider_part_details).
 */
#[ApiResource(
    description: 'An info provider which can be used to search for parts and retrieve part details.',
    operations: [],
    mcp: [
        'list_info_providers' => new McpToolCollection(
            title: 'List available info providers',
            description: 'List the info providers (e.g. distributors like Digikey, Mouser, LCSC) which are currently active and can be used with search_info_providers and get_info_provider_part_details.',
            annotations: ['readOnlyHint' => true, 'destructiveHint' => false, 'idempotentHint' => true, 'openWorldHint' => false],
            input: ListInfoProvidersInput::class,
            security: 'is_granted("@info_providers.create_parts")',
            processor: ListInfoProvidersProcessor::class,
        ),
    ],
)]
readonly class InfoProviderDTO
{
    public function __construct(
        /** @var string The unique key of the provider, to be used as provider_key in the other info provider tools */
        public string $key,
        /** @var string The (user friendly) name of the provider (e.g. "Digikey") */
        public string $name,
        /** @var string|null A short description of the provider */
        public ?string $description = null,
        /** @var string|null The url of the provider (e.g. "https://www.digikey.com") */
        public ?string $url = null,
        /** @var string[] The kinds of data this provider can supply (e.g. "PRICE", "DATASHEET", "PICTURE") */
        public array $capabilities = [],
    ) {
    }
}
