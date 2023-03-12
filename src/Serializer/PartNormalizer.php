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

use App\Entity\Parts\Part;
use App\Entity\Parts\PartLot;
use App\Entity\Parts\Storelocation;
use Symfony\Component\Serializer\Normalizer\CacheableSupportsMethodInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;

class PartNormalizer implements NormalizerInterface, DenormalizerInterface, CacheableSupportsMethodInterface
{

    private NormalizerInterface $normalizer;
    private StructuralElementFromNameDenormalizer $locationDenormalizer;

    public function __construct(ObjectNormalizer $normalizer, StructuralElementFromNameDenormalizer $locationDenormalizer)
    {
        $this->normalizer = $normalizer;
        $this->locationDenormalizer = $locationDenormalizer;
    }

    public function supportsNormalization($data, string $format = null): bool
    {
        return $data instanceof Part;
    }

    public function normalize($object, string $format = null, array $context = [])
    {
        if (!$object instanceof Part) {
            throw new \InvalidArgumentException('This normalizer only supports Part objects!');
        }

        $data = $this->normalizer->normalize($object, $format, $context);

        //Remove type field for CSV export
        if ($format === 'csv') {
            unset($data['type']);
        }

        $data['total_instock'] = $object->getAmountSum();

        return $data;
    }

    public function supportsDenormalization($data, string $type, string $format = null): bool
    {
        return is_array($data) && is_a($type, Part::class, true);
    }

    public function denormalize($data, string $type, string $format = null, array $context = [])
    {
        $object = $this->normalizer->denormalize($data, $type, $format, $context);

        if (!$object instanceof Part) {
            throw new \InvalidArgumentException('This normalizer only supports Part objects!');
        }

        if (isset($data['instock']) || isset($data['storelocation'])) {
            $partLot = new PartLot();

            if (isset($data['instock']) && $data['instock'] !== "") {
                //Replace comma with dot
                $instock = (float) str_replace(',', '.', $data['instock']);

                $partLot->setAmount($instock);
            } else {
                $partLot->setInstockUnknown(true);
            }

            if (isset($data['storelocation']) && $data['storelocation'] !== "") {
                $location = $this->locationDenormalizer->denormalize($data['storelocation'], Storelocation::class, $format, $context);
                $partLot->setStorageLocation($location);
            }

            $object->addPartLot($partLot);
        }

        return $object;
    }

    public function hasCacheableSupportsMethod(): bool
    {
        //Must be false, because we rely on is_array($data) in supportsDenormalization()
        return false;
    }
}