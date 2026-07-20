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

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\McpTool;
use App\Entity\Parts\ManufacturingStatus;
use App\Mcp\DTO\InfoProviderPartDetailsInput;
use App\State\Mcp\GetInfoProviderPartDetailsProcessor;

/**
 * This DTO represents a part with all its details.
 */
#[ApiResource(
    description: 'Detailed information about a part from an external info provider (e.g. a distributor or manufacturer catalog), including datasheets, images, parameters and purchase information.',
    operations: [],
    mcp: [
        'get_info_provider_part_details' => new McpTool(
            title: 'Get part details from an info provider',
            description: 'Get full detailed information (datasheets, images, parameters, prices, ...) about a specific part from an external info provider, identified by the provider key and the provider-specific part ID (both returned by search_info_providers).',
            annotations: ['readOnlyHint' => true, 'destructiveHint' => false, 'idempotentHint' => true, 'openWorldHint' => true],
            input: InfoProviderPartDetailsInput::class,
            security: 'is_granted("@info_providers.create_parts")',
            validate: true,
            processor: GetInfoProviderPartDetailsProcessor::class,
        ),
    ],
)]
class PartDetailDTO extends SearchResultDTO
{
    public function __construct(
        string $provider_key,
        string $provider_id,
        string $name,
        string $description,
        ?string $category = null,
        ?string $manufacturer = null,
        ?string $mpn = null,
        ?string $preview_image_url = null,
        ?ManufacturingStatus $manufacturing_status = null,
        ?string $provider_url = null,
        ?string $footprint = null,
        ?string $gtin = null,
        public readonly ?string $notes = null,
        /** @var FileDTO[]|null */
        public readonly ?array $datasheets = null,
        /** @var FileDTO[]|null */
        public readonly ?array $images = null,
        /** @var ParameterDTO[]|null */
        public readonly ?array $parameters = null,
        /** @var PurchaseInfoDTO[]|null */
        public readonly ?array $vendor_infos = null,
        /** The mass of the product in grams */
        public readonly ?float $mass = null,
        /** The URL to the product on the website of the manufacturer */
        public readonly ?string $manufacturer_product_url = null,
    ) {
        parent::__construct(
            provider_key: $provider_key,
            provider_id: $provider_id,
            name: $name,
            description: $description,
            category: $category,
            manufacturer: $manufacturer,
            mpn: $mpn,
            preview_image_url: $preview_image_url,
            manufacturing_status: $manufacturing_status,
            provider_url: $provider_url,
            footprint: $footprint,
            gtin: $gtin
        );
    }
}
