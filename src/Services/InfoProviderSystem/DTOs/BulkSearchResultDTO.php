<?php

declare(strict_types=1);

namespace App\Services\InfoProviderSystem\DTOs;

use App\Entity\Parts\Part;

class BulkSearchResultDTO extends SearchResultDTO
{
    public function __construct(
        SearchResultDTO $baseDto,
        public readonly ?string $sourceField = null,
        public readonly ?string $sourceKeyword = null,
        public readonly ?Part $localPart = null,
        public readonly int $priority = 1
    ) {
        parent::__construct(
            provider_key: $baseDto->provider_key,
            provider_id: $baseDto->provider_id,
            name: $baseDto->name,
            description: $baseDto->description,
            category: $baseDto->category,
            manufacturer: $baseDto->manufacturer,
            mpn: $baseDto->mpn,
            preview_image_url: $baseDto->preview_image_url,
            manufacturing_status: $baseDto->manufacturing_status,
            provider_url: $baseDto->provider_url,
            footprint: $baseDto->footprint
        );
    }
}