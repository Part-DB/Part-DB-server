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

use Symfony\Component\Serializer\Normalizer\DenormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

/**
 * The idea of this denormalizer is to allow to override the type of the object created using a certain context key.
 * This is required to resolve the issue of the serializer/API platform not correctly being able to determine the type
 * of the "element" properties of the Attachment and Parameter subclasses.
 */
class OverrideClassDenormalizer implements DenormalizerInterface, DenormalizerAwareInterface
{
    use DenormalizerAwareTrait;

    public const CONTEXT_KEY = '__override_type__';

    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): mixed
    {
        //Deserialize the data with the overridden type
        $overrideType = $context[self::CONTEXT_KEY];
        unset($context[self::CONTEXT_KEY]);

        return $this->denormalizer->denormalize($data, $overrideType, $format, $context);
    }

    public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool
    {
        return isset($context[self::CONTEXT_KEY]);
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            '*' => false,
        ];
    }
}