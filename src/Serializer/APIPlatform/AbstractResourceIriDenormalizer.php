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
use ApiPlatform\Serializer\ItemDenormalizer;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\SerializerAwareInterface;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * As of API Platform 4.4, denormalization was split out of ItemNormalizer into a dedicated ItemDenormalizer
 * service ("api_platform.serializer.denormalizer.item"), which is used directly (not through
 * "api_platform.serializer.normalizer.item") when denormalizing nested relations. This class applies the same
 * IRI/abstract-class workaround as AbstractResourceIriNormalizer to that dedicated service, so it also works for
 * relation denormalization. See AbstractResourceIriNormalizer for details.
 */
#[AsDecorator("api_platform.serializer.denormalizer.item")]
class AbstractResourceIriDenormalizer implements DenormalizerInterface, SerializerAwareInterface
{
    use AbstractResourceIriDenormalizationTrait;

    public function __construct(
        private readonly ItemDenormalizer $inner,
        private readonly IriConverterInterface $iriConverter,
        private readonly ResourceClassResolverInterface $resourceClassResolver,
    ) {
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
