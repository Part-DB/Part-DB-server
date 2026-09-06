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

namespace App\Mcp\DTO;

use App\Mcp\DTO\Filters\ChoiceFilterInput;
use App\Mcp\DTO\Filters\DateTimeFilterInput;
use App\Mcp\DTO\Filters\EntityFilterInput;
use App\Mcp\DTO\Filters\NumberFilterInput;
use App\Mcp\DTO\Filters\ParameterFilterInput;
use App\Mcp\DTO\Filters\TagsFilterInput;
use App\Mcp\DTO\Filters\TextFilterInput;

/**
 * Input for advanced_search_parts. Every field is optional and, when given, narrows the result further (all given
 * constraints are combined with AND) - this mirrors the constraint system behind the "Filters" tab of the part
 * table in the web UI (see PartFilter), reduced to the fields most useful for an AI agent to search by. Unlike
 * search_parts (a single free-text keyword across a fixed set of fields), this tool lets you constrain individual
 * fields precisely, including fields on related entities (stock lots, parameters, orderdetails).
 *
 * Built directly from the raw MCP tool-call arguments via fromArray() (see AdvancedPartSearchInputProvider),
 * because the nested per-field filter objects and the parameters array can't be reconstructed reliably by the
 * default MCP ObjectMapper-based mapping.
 */
final readonly class AdvancedPartSearchInput
{
    public function __construct(
        public ?TextFilterInput $name,
        public ?TextFilterInput $description,
        public ?TextFilterInput $comment,
        public ?TextFilterInput $ipn,
        public ?TextFilterInput $gtin,
        public ?TextFilterInput $manufacturerProductNumber,
        public ?TextFilterInput $manufacturerProductUrl,
        public ?TextFilterInput $lotDescription,
        public ?TextFilterInput $attachmentName,
        public ?TagsFilterInput $tags,
        public ?ChoiceFilterInput $manufacturingStatus,
        public ?NumberFilterInput $minAmount,
        public ?NumberFilterInput $mass,
        public ?NumberFilterInput $amountSum,
        public ?NumberFilterInput $lotCount,
        public ?NumberFilterInput $orderdetailsCount,
        public ?NumberFilterInput $attachmentsCount,
        public ?NumberFilterInput $parametersCount,
        public ?bool $favorite,
        public ?bool $needsReview,
        public ?bool $obsolete,
        public ?bool $lessThanDesired,
        public ?bool $lotNeedsRefill,
        public ?bool $lotUnknownAmount,
        public ?DateTimeFilterInput $lastModified,
        public ?DateTimeFilterInput $addedDate,
        public ?DateTimeFilterInput $lotExpirationDate,
        public ?EntityFilterInput $category,
        public ?EntityFilterInput $footprint,
        public ?EntityFilterInput $manufacturer,
        public ?EntityFilterInput $storelocation,
        public ?EntityFilterInput $supplier,
        public ?EntityFilterInput $measurementUnit,
        public ?EntityFilterInput $partCustomState,
        public ?EntityFilterInput $attachmentType,
        public ?EntityFilterInput $project,
        /** @var ParameterFilterInput[] Every entry must be matched by at least one of the part's parameters. */
        public array $parameters,
        /** @var string|null Sort results by this field. One of "name", "id", "lastModified", "addedDate",
         *                   "minAmount", "mass". Defaults to "name". */
        public ?string $orderBy,
        /** @var string|null "ASC" or "DESC". Defaults to "ASC". */
        public ?string $orderDirection,
        /** @var int|null Maximum number of results to return (1-200). Defaults to 50. */
        public ?int $limit,
    ) {
    }

    public static function fromArray(array $data): self
    {
        $textFilter = static fn (string $key): ?TextFilterInput => isset($data[$key]) && is_array($data[$key])
            ? TextFilterInput::fromArray($data[$key]) : null;
        $numberFilter = static fn (string $key): ?NumberFilterInput => isset($data[$key]) && is_array($data[$key])
            ? NumberFilterInput::fromArray($data[$key]) : null;
        $dateTimeFilter = static fn (string $key): ?DateTimeFilterInput => isset($data[$key]) && is_array($data[$key])
            ? DateTimeFilterInput::fromArray($data[$key]) : null;
        $entityFilter = static fn (string $key): ?EntityFilterInput => isset($data[$key]) && is_array($data[$key])
            ? EntityFilterInput::fromArray($data[$key]) : null;

        return new self(
            name: $textFilter('name'),
            description: $textFilter('description'),
            comment: $textFilter('comment'),
            ipn: $textFilter('ipn'),
            gtin: $textFilter('gtin'),
            manufacturerProductNumber: $textFilter('manufacturerProductNumber'),
            manufacturerProductUrl: $textFilter('manufacturerProductUrl'),
            lotDescription: $textFilter('lotDescription'),
            attachmentName: $textFilter('attachmentName'),
            tags: isset($data['tags']) && is_array($data['tags']) ? TagsFilterInput::fromArray($data['tags']) : null,
            manufacturingStatus: isset($data['manufacturingStatus']) && is_array($data['manufacturingStatus'])
                ? ChoiceFilterInput::fromArray($data['manufacturingStatus']) : null,
            minAmount: $numberFilter('minAmount'),
            mass: $numberFilter('mass'),
            amountSum: $numberFilter('amountSum'),
            lotCount: $numberFilter('lotCount'),
            orderdetailsCount: $numberFilter('orderdetailsCount'),
            attachmentsCount: $numberFilter('attachmentsCount'),
            parametersCount: $numberFilter('parametersCount'),
            favorite: PartInputHelpers::bool($data, 'favorite'),
            needsReview: PartInputHelpers::bool($data, 'needsReview'),
            obsolete: PartInputHelpers::bool($data, 'obsolete'),
            lessThanDesired: PartInputHelpers::bool($data, 'lessThanDesired'),
            lotNeedsRefill: PartInputHelpers::bool($data, 'lotNeedsRefill'),
            lotUnknownAmount: PartInputHelpers::bool($data, 'lotUnknownAmount'),
            lastModified: $dateTimeFilter('lastModified'),
            addedDate: $dateTimeFilter('addedDate'),
            lotExpirationDate: $dateTimeFilter('lotExpirationDate'),
            category: $entityFilter('category'),
            footprint: $entityFilter('footprint'),
            manufacturer: $entityFilter('manufacturer'),
            storelocation: $entityFilter('storelocation'),
            supplier: $entityFilter('supplier'),
            measurementUnit: $entityFilter('measurementUnit'),
            partCustomState: $entityFilter('partCustomState'),
            attachmentType: $entityFilter('attachmentType'),
            project: $entityFilter('project'),
            parameters: array_map(
                static fn (array $p): ParameterFilterInput => ParameterFilterInput::fromArray($p),
                array_filter(PartInputHelpers::arr($data, 'parameters'), 'is_array'),
            ),
            orderBy: PartInputHelpers::str($data, 'orderBy'),
            orderDirection: PartInputHelpers::str($data, 'orderDirection'),
            limit: PartInputHelpers::int($data, 'limit'),
        );
    }
}
