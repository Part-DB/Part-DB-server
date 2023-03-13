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
use App\Entity\Parts\Supplier;
use App\Entity\PriceInformations\Orderdetail;
use App\Entity\PriceInformations\Pricedetail;
use Brick\Math\BigDecimal;
use Symfony\Component\Serializer\Normalizer\CacheableSupportsMethodInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;

class PartNormalizer implements NormalizerInterface, DenormalizerInterface, CacheableSupportsMethodInterface
{
    private const DENORMALIZE_KEY_MAPPING = [
        'notes' => 'comment',
        'quantity' => 'instock',
        'amount' => 'instock',
        'mpn' => 'manufacturer_product_number',
        'spn' => 'supplier_part_number',
        'supplier_product_number' => 'supplier_part_number'
    ];

    private ObjectNormalizer $normalizer;
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

    private function normalizeKeys(array &$data): array
    {
        //Rename keys based on the mapping, while leaving the data untouched
        foreach ($data as $key => $value) {
            if (isset(self::DENORMALIZE_KEY_MAPPING[$key])) {
                $data[self::DENORMALIZE_KEY_MAPPING[$key]] = $value;
                unset($data[$key]);
            }
        }

        return $data;
    }

    public function denormalize($data, string $type, string $format = null, array $context = [])
    {
        $this->normalizeKeys($data);

        //Empty IPN should be null, or we get a constraint error
        if ($data['ipn'] === '') {
            $data['ipn'] = null;
        }

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

        if (isset($data['supplier']) && $data['supplier'] !== "") {
            $supplier = $this->locationDenormalizer->denormalize($data['supplier'], Supplier::class, $format, $context);

            if ($supplier) {
                $orderdetail = new Orderdetail();
                $orderdetail->setSupplier($supplier);

                if (isset($data['supplier_part_number']) && $data['supplier_part_number'] !== "") {
                    $orderdetail->setSupplierpartnr($data['supplier_part_number']);
                }

                $object->addOrderdetail($orderdetail);

                if (isset($data['price']) && $data['price'] !== "") {
                    $pricedetail = new Pricedetail();
                    $pricedetail->setMinDiscountQuantity(1);
                    $pricedetail->setPriceRelatedQuantity(1);
                    $price = BigDecimal::of(str_replace(',', '.', $data['price']));
                    $pricedetail->setPrice($price);

                    $orderdetail->addPricedetail($pricedetail);
                }
            }
        }

        return $object;
    }

    public function hasCacheableSupportsMethod(): bool
    {
        //Must be false, because we rely on is_array($data) in supportsDenormalization()
        return false;
    }
}