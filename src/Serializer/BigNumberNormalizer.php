<?php

declare(strict_types=1);

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

use Brick\Math\BigDecimal;
use Brick\Math\BigNumber;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * @see \App\Tests\Serializer\BigNumberNormalizerTest
 */
class BigNumberNormalizer implements NormalizerInterface, DenormalizerInterface
{

    public function supportsNormalization($data, string $format = null, array $context = []): bool
    {
        return $data instanceof BigNumber;
    }

    public function normalize($object, string $format = null, array $context = []): string
    {
        if (!$object instanceof BigNumber) {
            throw new \InvalidArgumentException('This normalizer only supports BigNumber objects!');
        }

        return (string) $object;
    }

    /**
     * @return bool[]
     */
    public function getSupportedTypes(?string $format): array
    {
        return [
            BigNumber::class => true,
            BigDecimal::class => true,
        ];
    }

    public function denormalize(mixed $data, string $type, string $format = null, array $context = []): BigNumber|null
    {
        if (!is_a($type, BigNumber::class, true)) {
            throw new \InvalidArgumentException('This normalizer only supports BigNumber objects!');
        }

        return $type::of($data);
    }

    public function supportsDenormalization(mixed $data, string $type, string $format = null, array $context = []): bool
    {
        //data must be a string or a number (int, float, etc.) and the type must be BigNumber or BigDecimal
        return (is_string($data) || is_numeric($data)) && (is_subclass_of($type, BigNumber::class));
    }
}
