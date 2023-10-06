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

use App\Entity\Parts\Part;
use App\Entity\Parts\PartLot;
use App\Entity\Parts\StorageLocation;
use App\Entity\Parts\Supplier;
use App\Entity\PriceInformations\Orderdetail;
use App\Entity\PriceInformations\Pricedetail;
use Brick\Math\BigDecimal;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Serializer\Normalizer\CacheableSupportsMethodInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;

/**
 * @see \App\Tests\Serializer\PartNormalizerTest
 */
class PartNormalizer implements NormalizerInterface, DenormalizerInterface
{

    private const DENORMALIZE_KEY_MAPPING = [
        'notes' => 'comment',
        'quantity' => 'instock',
        'amount' => 'instock',
        'mpn' => 'manufacturer_product_number',
        'spn' => 'supplier_part_number',
        'supplier_product_number' => 'supplier_part_number',
        'storage_location' => 'storelocation',
    ];

    public function __construct(
        private readonly StructuralElementFromNameDenormalizer $locationDenormalizer,
        #[Autowire(service: ObjectNormalizer::class)]
        private readonly NormalizerInterface $normalizer,
        #[Autowire(service: ObjectNormalizer::class)]
        private readonly DenormalizerInterface $denormalizer,
    )
    {
    }

    public function supportsNormalization($data, string $format = null, array $context = []): bool
    {
        return $data instanceof Part;
    }

    /**
     * @return (float|mixed)[]|\ArrayObject|null|scalar
     *
     * @psalm-return \ArrayObject|array{total_instock: float|mixed,...}|null|scalar
     */
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

    public function supportsDenormalization($data, string $type, string $format = null, array $context = []): bool
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

    public function denormalize($data, string $type, string $format = null, array $context = []): ?Part
    {
        $this->normalizeKeys($data);

        //Empty IPN should be null, or we get a constraint error
        if (isset($data['ipn']) && $data['ipn'] === '') {
            $data['ipn'] = null;
        }

        //Fill empty needs_review and needs_review_comment fields with false
        if (empty($data['needs_review'])) {
            $data['needs_review'] = false;
        }
        if (empty($data['favorite'])) {
            $data['favorite'] = false;
        }
        if (empty($data['minamount'])) {
            $data['minamount'] = 0.0;
        }

        $object = $this->denormalizer->denormalize($data, $type, $format, $context);

        if (!$object instanceof Part) {
            throw new \InvalidArgumentException('This normalizer only supports Part objects!');
        }

        if ((isset($data['instock']) && trim((string) $data['instock']) !== "") || (isset($data['storelocation']) && trim((string) $data['storelocation']) !== "")) {
            $partLot = new PartLot();

            if (isset($data['instock']) && $data['instock'] !== "") {
                //Replace comma with dot
                $instock = (float) str_replace(',', '.', (string) $data['instock']);

                $partLot->setAmount($instock);
            } else {
                $partLot->setInstockUnknown(true);
            }

            if (isset($data['storelocation']) && $data['storelocation'] !== "") {
                $location = $this->locationDenormalizer->denormalize($data['storelocation'], StorageLocation::class, $format, $context);
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
                    $price = BigDecimal::of(str_replace(',', '.', (string) $data['price']));
                    $pricedetail->setPrice($price);

                    $orderdetail->addPricedetail($pricedetail);
                }
            }
        }

        return $object;
    }

    /**
     * @return bool[]
     */
    public function getSupportedTypes(?string $format): array
    {
        //Must be false, because we rely on is_array($data) in supportsDenormalization()
        return [
            Part::class => false,
        ];
    }
}
