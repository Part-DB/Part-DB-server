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

use ApiPlatform\Metadata\IriConverterInterface;
use ApiPlatform\Metadata\ResourceClassResolverInterface;
use ApiPlatform\Serializer\ItemNormalizer;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\SerializerAwareInterface;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * This class decorates API Platform's ItemNormalizer to work around a bug in API Platform's
 * AbstractItemNormalizer where IRI strings for abstract resource classes with a discriminator map fail
 * deserialization when objectToPopulate is null (the discriminator is checked before the IRI string check).
 * See: https://github.com/Part-DB/Part-DB-server/issues/1370
 *
 * Since API Platform 4.4 split normalization and denormalization into separate services, the same fix is also
 * applied to the dedicated denormalizer service by AbstractResourceIriDenormalizer.
 *
 * Note: entity import/export (EntityImporter/EntityExporter) does not go through this decorator at all, as it
 * uses the "import_export" named serializer, which never includes any API Platform normalizer in the first
 * place (see config/packages/framework.yaml).
 */
#[AsDecorator("api_platform.serializer.normalizer.item")]
class AbstractResourceIriNormalizer implements NormalizerInterface, DenormalizerInterface, SerializerAwareInterface
{
    use AbstractResourceIriDenormalizationTrait;

    public function __construct(
        private readonly ItemNormalizer $inner,
        private readonly IriConverterInterface $iriConverter,
        private readonly ResourceClassResolverInterface $resourceClassResolver,
    ) {
    }

    public function normalize(mixed $object, ?string $format = null, array $context = []): float|int|bool|\ArrayObject|array|string|null
    {
        return $this->inner->normalize($object, $format, $context);
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $this->inner->supportsNormalization($data, $format, $context);
    }

    public function setSerializer(SerializerInterface $serializer): void
    {
        $this->inner->setSerializer($serializer);
    }

    public function getSupportedTypes(?string $format): array
    {
        return $this->inner->getSupportedTypes($format);
    }
}
