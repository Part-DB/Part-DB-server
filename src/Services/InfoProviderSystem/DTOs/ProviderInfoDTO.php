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
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\McpToolCollection;
use ApiPlatform\OpenApi\Model\Operation;
use App\Mcp\DTO\ListInfoProvidersInput;
use App\Services\InfoProviderSystem\Providers\ProviderCapabilities;
use App\State\Mcp\ListInfoProvidersProcessor;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * Immutable, structured description of an info provider, returned by InfoProviderInterface::getProviderInfo()
 * and (via the 'info_provider:read' group) exposed as the REST GET /api/info_providers collection and the
 * list_info_providers MCP tool.
 *
 * disabledHelp, oauthAppName and settingsClass are internal-only (used by the settings UI) and deliberately
 * not tagged with the 'info_provider:read' group, so they never appear in the API/MCP output.
 */
#[ApiResource(
    uriTemplate: '/info_providers',
    description: 'An info provider which can be used to search for parts and retrieve part details.',
    operations: [
        new GetCollection(
            openapi: new Operation(summary: 'List the info providers which are currently active and can be used for searching parts.'),
            security: 'is_granted("@info_providers.create_parts")',
            provider: ListInfoProvidersProcessor::class,
        ),
    ],
    normalizationContext: ['groups' => ['info_provider:read']],
    paginationEnabled: false,
    mcp: [
        'list_info_providers' => new McpToolCollection(
            title: 'List available info providers',
            description: 'List the info providers (e.g. distributors like Digikey, Mouser, LCSC) which are currently active and can be used with search_info_providers and get_info_provider_part_details. Ask for the user\'s confirmation before using expensive providers (e.g. distributors with strict rate limits or which cost money to use).',
            annotations: ['readOnlyHint' => true, 'destructiveHint' => false, 'idempotentHint' => true, 'openWorldHint' => false],
            normalizationContext: ['groups' => ['info_provider:read']],
            security: 'is_granted("@info_providers.create_parts")',
            input: ListInfoProvidersInput::class,
            processor: ListInfoProvidersProcessor::class,
        ),
    ],
)]
readonly class ProviderInfoDTO
{
    public function __construct(
        /** @var string A unique key for this provider (e.g. "digikey"), which is saved into the database and used to identify the provider */
        #[Groups(['info_provider:read'])]
        public string $key,
        /** @var string The (user friendly) name of the provider (e.g. "Digikey"), will be translated */
        #[Groups(['info_provider:read'])]
        public string $name,
        /** @var string|null A short description of the provider (e.g. "Digikey is a ..."), will be translated */
        #[Groups(['info_provider:read'])]
        public ?string $description = null,
        /** @var string|null The url of the provider (e.g. "https://www.digikey.com") */
        #[Groups(['info_provider:read'])]
        public ?string $url = null,
        /** @var string|null A help text which is shown when the provider is disabled, explaining how to enable it */
        public ?string $disabledHelp = null,
        /** @var string|null The name of the OAuth app which is used for authentication (e.g. "ip_digikey_oauth"). If this is set a connect button will be shown */
        public ?string $oauthAppName = null,
        /** @var class-string|null The class name of the settings class which contains the settings for this provider (e.g. "App\Settings\InfoProviderSettings\DigikeySettings"). If this is set a link to the settings will be shown */
        public ?string $settingsClass = null,
        /**
         * A list of capabilities this provider supports (which kind of data it can provide).
         * Not every part have to contain all of these data, but the provider should be able to provide them in general.
         * Currently, this list is purely informational and not used in functional checks.
         * @var ProviderCapabilities[]
         */
        #[Groups(['info_provider:read'])]
        public array $capabilities = [],
        /**
         * @var bool True if this provider is considered "expensive" (e.g. it has a strict rate limit or costs money to use), false otherwise.
         * Users should be asked for confirmation before using an expensive provider, when making multiple requests (e.g. when searching for multiple parts at once),
         * and more careful caching should be used for expensive providers.
         */
        #[Groups(['info_provider:read'])]
        public bool $expensive = false,
    ) {
    }
}
