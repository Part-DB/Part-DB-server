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

namespace App\Serializer;

use App\Mcp\DTO\StructuralElementOverview;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Normalizes StructuralElementOverview as a plain {id, name} object, without the JSON-LD "@id"/"@type"
 * metadata that ApiPlatform\JsonLd\Serializer\ItemNormalizer would otherwise add (as a blank/genid node,
 * since this DTO is intentionally not an API resource). That metadata is useless clutter for the list_X
 * MCP tools, whose whole point is a lean id+name overview.
 *
 * @see \App\Tests\Serializer\StructuralElementOverviewNormalizerTest
 */
class StructuralElementOverviewNormalizer implements NormalizerInterface
{
    public function supportsNormalization($data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof StructuralElementOverview;
    }

    /**
     * @return array{id: int, name: string}
     */
    public function normalize($object, ?string $format = null, array $context = []): array
    {
        if (!$object instanceof StructuralElementOverview) {
            throw new \InvalidArgumentException('This normalizer only supports StructuralElementOverview objects!');
        }

        return [
            'id' => $object->id,
            'name' => $object->name,
        ];
    }

    /**
     * @return bool[]
     */
    public function getSupportedTypes(?string $format): array
    {
        return [
            StructuralElementOverview::class => true,
        ];
    }
}
