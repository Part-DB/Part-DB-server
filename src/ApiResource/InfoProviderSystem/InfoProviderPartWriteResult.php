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

namespace App\ApiResource\InfoProviderSystem;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\McpTool;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use App\Entity\Parts\Part;
use App\Mcp\DTO\InfoProviderCreatePartInput;
use App\Mcp\DTO\InfoProviderUpdatePartInput;
use App\State\Mcp\CreatePartFromInfoProviderProcessor;
use App\State\Mcp\UpdatePartFromInfoProviderProcessor;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * The outcome of creating or updating a part from an info provider.
 *
 * The web UI merges provider data into a part and then lets a user review and save it. Callers without a
 * human in the loop get that review after the fact instead: the response lists every field the write actually
 * changed, so an automated job can log what it did - or, with dry_run, what it would have done.
 */
#[ApiResource(
    shortName: 'InfoProviderPartWrite',
    description: 'Creating or updating a part from an external info provider, using the same data mapping and '
        .'merge rules as the web UI.',
    normalizationContext: ['groups' => [
        'info_provider_write:read',
        'part:read', 'api:basic:read', 'provider_reference:read',
        'part_lot:read', 'orderdetail:read', 'pricedetail:read', 'parameter:read', 'attachment:read',
        'eda_info:read',
    ]],
    operations: [
        new Post(
            uriTemplate: '/info_providers/update_part',
            //200, not API Platform's default 201: the response describes an outcome, and on a dry run - or when
            //the provider had nothing new - nothing was created at all
            status: 200,
            openapi: new Operation(
                summary: 'Update an existing part from an info provider',
                description: 'Fetches the part again from the info provider it was created with (or from the '
                    .'provider given explicitly) and merges the result into the stored part, exactly as the '
                    .'"update from info provider" button in the web UI does - but saves right away instead of '
                    .'showing a form. Set dry_run to see what would change without writing anything.',
            ),
            security: 'is_granted("@info_providers.create_parts")',
            input: InfoProviderUpdatePartInput::class,
            validate: true,
            processor: UpdatePartFromInfoProviderProcessor::class,
        ),
        new Post(
            uriTemplate: '/info_providers/create_part',
            //200 for the same reason as the update operation: a dry run creates nothing, so a fixed 201 would lie
            status: 200,
            openapi: new Operation(
                summary: 'Create a new part from an info provider',
                description: 'Creates a new part from a provider-specific part ID (as returned by the provider '
                    .'search), using Part-DB\'s own field mapping, so the result is identical to creating the '
                    .'part through the web UI. Set dry_run to see which fields would be filled without writing '
                    .'anything.',
            ),
            security: 'is_granted("@info_providers.create_parts") and is_granted("@parts.create")',
            input: InfoProviderCreatePartInput::class,
            validate: true,
            processor: CreatePartFromInfoProviderProcessor::class,
        ),
    ],
    mcp: [
        'update_part_from_info_provider' => new McpTool(
            title: 'Update a part from its info provider',
            description: 'Re-fetch an existing part from the info provider it was created with and merge the '
                .'current provider data into it (prices, datasheets, parameters, ...), using the same merge rules '
                .'as the web UI: empty fields are filled, fields which already have a value are kept. Returns the '
                .'list of fields that actually changed. Use dry_run: true to preview the changes without saving. '
                .'Pass provider_key and provider_id to update from a different provider part than the one the part '
                .'is linked to.',
            annotations: ['readOnlyHint' => false, 'destructiveHint' => false, 'idempotentHint' => false, 'openWorldHint' => true],
            security: 'is_granted("@info_providers.create_parts")',
            input: InfoProviderUpdatePartInput::class,
            validate: true,
            processor: UpdatePartFromInfoProviderProcessor::class,
        ),
        'create_part_from_info_provider' => new McpTool(
            title: 'Create a part from an info provider',
            description: 'Create a new part in the inventory from an info provider part (identified by provider '
                .'key and provider-specific ID, both returned by search_info_providers). Part-DB does the field '
                .'mapping itself, so the new part looks exactly as if it had been created through the web UI, '
                .'including datasheets, images, parameters and purchase information. Pass category_id to say where the '
                .'part belongs - a part must have a category and providers rarely report one that exists here. '
                .'Use dry_run: true to preview which fields would be filled, and whether the part would be '
                .'accepted, without creating anything.',
            annotations: ['readOnlyHint' => false, 'destructiveHint' => false, 'idempotentHint' => false, 'openWorldHint' => true],
            security: 'is_granted("@info_providers.create_parts") and is_granted("@parts.create")',
            input: InfoProviderCreatePartInput::class,
            validate: true,
            processor: CreatePartFromInfoProviderProcessor::class,
        ),
    ],
)]
class InfoProviderPartWriteResult
{
    private function __construct(
        /** @var string What happened: "created", "updated", or "unchanged" if the provider had nothing new */
        #[Groups(['info_provider_write:read'])]
        public readonly string $status,

        /** @var bool True if nothing was written, because dry_run was requested */
        #[Groups(['info_provider_write:read'])]
        public readonly bool $dry_run,

        /**
         * @var PartFieldChange[] The fields this write changed (or would have changed on a dry run).
         * Empty for status "unchanged".
         */
        #[Groups(['info_provider_write:read'])]
        public readonly array $changes,

        /**
         * @var int|null The database ID of the written part. Null on a dry run, where no part was written -
         * for a new part no ID exists yet, and for an updated one the stored part is unchanged.
         */
        #[Groups(['info_provider_write:read'])]
        public readonly ?int $part_id,

        /** @var Part|null The written part. Null on a dry run, for the same reason as part_id. */
        #[Groups(['info_provider_write:read'])]
        public readonly ?Part $part,
    ) {
    }

    /**
     * @param PartFieldChange[] $changes
     */
    public static function of(string $status, array $changes, Part $part): self
    {
        return new self($status, false, $changes, $part->getId(), $part);
    }

    /**
     * @param PartFieldChange[] $changes
     */
    public static function dryRun(string $status, array $changes): self
    {
        return new self($status, true, $changes, null, null);
    }
}
