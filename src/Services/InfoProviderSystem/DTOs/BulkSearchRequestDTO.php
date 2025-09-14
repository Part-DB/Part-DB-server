<?php

declare(strict_types=1);

namespace App\Services\InfoProviderSystem\DTOs;

use App\Entity\Parts\Part;

readonly class BulkSearchRequestDTO
{
    /**
     * @param  array  $fieldMappings
     * @param  bool  $prefetchDetails
     * @param  Part[]  $parts The parts for which the bulk search should be performed.
     */
    public function __construct(
        public array $fieldMappings,
        public bool $prefetchDetails = false,
        public array $parts = []
    ) {}
}
