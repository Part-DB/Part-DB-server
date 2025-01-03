<?php
/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2024 Jan Böhmer (https://github.com/jbtronics)
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


namespace App\Serializer\APIPlatform;

use ApiPlatform\Serializer\ItemNormalizer;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\Serializer\Normalizer\CacheableSupportsMethodInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\SerializerAwareInterface;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * This class decorates API Platform's ItemNormalizer to allow skipping the normalization process by setting the
 * DISABLE_ITEM_NORMALIZER context key to true. This is useful for all kind of serialization operations, where the API
 * Platform subsystem should not be used.
 */
#[AsDecorator("api_platform.serializer.normalizer.item")]
class SkippableItemNormalizer implements NormalizerInterface, DenormalizerInterface, SerializerAwareInterface
{

    public const DISABLE_ITEM_NORMALIZER = 'DISABLE_ITEM_NORMALIZER';

    public function __construct(private readonly ItemNormalizer $inner)
    {

    }

    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): mixed
    {
        return $this->inner->denormalize($data, $type, $format, $context);
    }

    public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool
    {
        if ($context[self::DISABLE_ITEM_NORMALIZER] ?? false) {
            return false;
        }

        return $this->inner->supportsDenormalization($data, $type, $format, $context);
    }

    public function normalize(mixed $object, ?string $format = null, array $context = []): float|int|bool|\ArrayObject|array|string|null
    {
        return $this->inner->normalize($object, $format, $context);
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        if ($context[self::DISABLE_ITEM_NORMALIZER] ?? false) {
            return false;
        }

        return $this->inner->supportsNormalization($data, $format, $context);
    }

    public function setSerializer(SerializerInterface $serializer): void
    {
        $this->inner->setSerializer($serializer);
    }

    public function getSupportedTypes(?string $format): array
    {
        //Don't cache results, as we check for the context
        return [
            'object' => false
        ];
    }
}