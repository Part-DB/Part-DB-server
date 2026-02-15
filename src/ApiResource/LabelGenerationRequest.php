<?php

declare(strict_types=1);

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

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\RequestBody;
use ApiPlatform\OpenApi\Model\Response;
use App\Entity\LabelSystem\LabelSupportedElement;
use App\State\LabelGenerationProcessor;
use App\Validator\Constraints\Misc\ValidRange;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * API Resource for generating PDF labels for parts, part lots, or storage locations.
 * This endpoint allows generating labels using saved label profiles.
 */
#[ApiResource(
    uriTemplate: '/labels/generate',
    description: 'Generate PDF labels for parts, part lots, or storage locations using label profiles.',
    operations: [
        new Post(
            inputFormats: ['json' => ['application/json']],
            outputFormats: [],
            openapi: new Operation(
                responses: [
                    "200" => new Response(description: "PDF file containing the generated labels"),
                ],
                summary: 'Generate PDF labels',
                description: 'Generate PDF labels for one or more elements using a label profile. Returns a PDF file.',
                requestBody: new RequestBody(
                    description: 'Label generation request',
                    required: true,
                ),
            ),
        )
    ],
    processor: LabelGenerationProcessor::class,
)]
class LabelGenerationRequest
{
    /**
     * @var int The ID of the label profile to use for generation
     */
    #[Assert\NotBlank(message: 'Profile ID is required')]
    #[Assert\Positive(message: 'Profile ID must be a positive integer')]
    public int $profileId = 0;

    /**
     * @var string Comma-separated list of element IDs or ranges (e.g., "1,2,5-10,15")
     */
    #[Assert\NotBlank(message: 'Element IDs are required')]
    #[ValidRange()]
    #[ApiProperty(example: "1,2,5-10,15")]
    public string $elementIds = '';

    /**
     * @var LabelSupportedElement|null Optional: Override the element type. If not provided, uses profile's default.
     */
    public ?LabelSupportedElement $elementType = null;
}
