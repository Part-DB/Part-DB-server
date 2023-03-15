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

namespace App\Serializer;

use App\Entity\Base\AbstractStructuralDBElement;
use Symfony\Component\Serializer\Normalizer\CacheableSupportsMethodInterface;
use Symfony\Component\Serializer\Normalizer\ContextAwareDenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\ContextAwareNormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;

class StructuralElementNormalizer implements NormalizerInterface, CacheableSupportsMethodInterface
{
    private NormalizerInterface $normalizer;

    public function __construct(ObjectNormalizer $normalizer)
    {
        $this->normalizer = $normalizer;
    }

    public function supportsNormalization($data, string $format = null): bool
    {
        return $data instanceof AbstractStructuralDBElement;
    }

    public function normalize($object, string $format = null, array $context = [])
    {
        if (!$object instanceof AbstractStructuralDBElement) {
            throw new \InvalidArgumentException('This normalizer only supports AbstractStructural objects!');
        }

        $data = $this->normalizer->normalize($object, $format, $context);

        //Remove type field for CSV export
        if ($format === 'csv') {
            unset($data['type']);
        }

        $data['full_name'] = $object->getFullPath('->');

        return $data;
    }

    public function hasCacheableSupportsMethod(): bool
    {
        return true;
    }
}