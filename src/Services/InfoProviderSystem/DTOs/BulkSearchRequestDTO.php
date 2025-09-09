<?php

declare(strict_types=1);

namespace App\Services\InfoProviderSystem\DTOs;

class BulkSearchRequestDTO
{
    public function __construct(
        public readonly array $fieldMappings,
        public readonly bool $prefetchDetails = false,
        public readonly array $partIds = []
    ) {}
}