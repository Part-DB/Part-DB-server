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
namespace App\Services\ImportExportSystem\PartKeeprImporter;

use App\Entity\Attachments\PartAttachment;
use App\Entity\Parameters\PartParameter;
use App\Entity\Parts\Category;
use App\Entity\Parts\Footprint;
use App\Entity\Parts\Manufacturer;
use App\Entity\Parts\MeasurementUnit;
use App\Entity\Parts\Part;
use App\Entity\Parts\PartLot;
use App\Entity\Parts\Storelocation;
use App\Entity\Parts\Supplier;
use App\Entity\PriceInformations\Currency;
use App\Entity\PriceInformations\Orderdetail;
use App\Entity\PriceInformations\Pricedetail;
use Brick\Math\BigDecimal;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Intl\Currencies;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

/**
 * This service is used to import parts from a PartKeepr export. You have to import the datastructures first!
 */
class PKPartImporter
{
    use PKImportHelperTrait;

    public function __construct(EntityManagerInterface $em, PropertyAccessorInterface $propertyAccessor, private readonly string $base_currency)
    {
        $this->em = $em;
        $this->propertyAccessor = $propertyAccessor;
    }

    public function importParts(array $data): int
    {
        if (!isset($data['part'])) {
            throw new \RuntimeException('$data must contain a "part" key!');
        }


        $part_data = $data['part'];
        foreach ($part_data as $part) {
            $entity = new Part();
            $entity->setName($part['name']);
            $entity->setDescription($part['description'] ?? '');
            //All parts get a tag, that they were imported from PartKeepr
            $entity->setTags('partkeepr-imported');
            $this->setAssociationField($entity, 'category', Category::class, $part['category_id']);

            //If the part is a metapart, write that in the description, and we can skip the rest
            if ($part['metaPart'] === '1') {
                $entity->setDescription('Metapart (Not supported in Part-DB)');
                $entity->setComment('This part represents a former metapart in PartKeepr. It is not supported in Part-DB yet. And you can most likely delete it.');
                $entity->setTags('partkeepr-imported,partkeepr-metapart');
            } else {
                $entity->setMinAmount($part['minStockLevel'] ?? 0);
                if (!empty($part['internalPartNumber'])) {
                    $entity->setIpn($part['internalPartNumber']);
                }
                $entity->setComment($part['comment'] ?? '');
                $entity->setNeedsReview($part['needsReview'] === '1');
                $this->setCreationDate($entity, $part['createDate']);

                $this->setAssociationField($entity, 'footprint', Footprint::class, $part['footprint_id']);

                //Set partUnit (when it is not ID=1, which is Pieces in Partkeepr)
                if ($part['partUnit_id'] !== '1') {
                    $this->setAssociationField($entity, 'partUnit', MeasurementUnit::class, $part['partUnit_id']);
                }

                //Create a part lot to store the stock level and location
                $lot = new PartLot();
                $lot->setAmount($part['stockLevel'] ?? 0);
                $this->setAssociationField($lot, 'storage_location', Storelocation::class, $part['storageLocation_id']);
                $entity->addPartLot($lot);

                //For partCondition, productionsRemarks and Status, create a custom parameter
                if ($part['partCondition']) {
                    $partCondition = (new PartParameter())->setName('Part Condition')->setGroup('PartKeepr')
                        ->setValueText($part['partCondition']);
                    $entity->addParameter($partCondition);
                }
                if ($part['productionRemarks']) {
                    $partCondition = (new PartParameter())->setName('Production Remarks')->setGroup('PartKeepr')
                        ->setValueText($part['productionRemarks']);
                    $entity->addParameter($partCondition);
                }
                if ($part['status']) {
                    $partCondition = (new PartParameter())->setName('Status')->setGroup('PartKeepr')
                        ->setValueText($part['status']);
                    $entity->addParameter($partCondition);
                }
            }

            $this->setIDOfEntity($entity, $part['id']);
            $this->em->persist($entity);
        }

        $this->em->flush();

        $this->importPartManufacturers($data);
        $this->importPartParameters($data);
        $this->importOrderdetails($data);

        //Import attachments
        $this->importAttachments($data, 'partattachment', Part::class, 'part_id', PartAttachment::class);

        return is_countable($part_data) ? count($part_data) : 0;
    }

    protected function importPartManufacturers(array $data): void
    {
        if (!isset($data['partmanufacturer'])) {
            throw new \RuntimeException('$data must contain a "partmanufacturer" key!');
        }

        //Part-DB only supports one manufacturer per part, only the last one is imported
        $partmanufacturer_data = $data['partmanufacturer'];
        foreach ($partmanufacturer_data as $partmanufacturer) {
            /** @var Part $part */
            $part = $this->em->find(Part::class, (int) $partmanufacturer['part_id']);
            if (!$part) {
                throw new \RuntimeException(sprintf('Could not find part with ID %s', $partmanufacturer['part_id']));
            }
            $manufacturer = $this->em->find(Manufacturer::class, (int) $partmanufacturer['manufacturer_id']);
            if (!$manufacturer instanceof Manufacturer) {
                throw new \RuntimeException(sprintf('Could not find manufacturer with ID %s', $partmanufacturer['manufacturer_id']));
            }
            $part->setManufacturer($manufacturer);
            $part->setManufacturerProductNumber($partmanufacturer['partNumber']);
        }

        $this->em->flush();
    }

    protected function importPartParameters(array $data): void
    {
        if (!isset($data['partparameter'])) {
            throw new \RuntimeException('$data must contain a "partparameter" key!');
        }

        foreach ($data['partparameter'] as $partparameter) {
            $entity = new PartParameter();

            //Name format: Name (Description)
            $name = $partparameter['name'];
            if (!empty($partparameter['description'])) {
                $name .= ' ('.$partparameter['description'].')';
            }
            $entity->setName($name);

            $entity->setValueText($partparameter['stringValue'] ?? '');
            if ($partparameter['unit_id'] === null) {
                $entity->setUnit($this->getUnitSymbol($data, (int)$partparameter['unit_id']));
            } else {
                $entity->setUnit("");
            }

            if ($partparameter['normalizedMinValue'] !== null) {
                $entity->setValueMin((float) $partparameter['normalizedMinValue']);
            }
            if ($partparameter['normalizedValue'] !== null) {
                $entity->setValueTypical((float) $partparameter['normalizedValue']);
            }
            if ($partparameter['normalizedMaxValue'] !== null) {
                $entity->setValueMax((float) $partparameter['normalizedMaxValue']);
            }

            $part = $this->em->find(Part::class, (int) $partparameter['part_id']);
            if (!$part instanceof Part) {
                throw new \RuntimeException(sprintf('Could not find part with ID %s', $partparameter['part_id']));
            }

            $part->addParameter($entity);
            $this->em->persist($entity);
        }
        $this->em->flush();
    }

    /**
     * Returns the currency for the given ISO code. If the currency does not exist, it is created.
     * This function returns null if the ISO code is the base currency.
     */
    protected function getOrCreateCurrency(string $currency_iso_code): ?Currency
    {
        //Normalize ISO code
        $currency_iso_code = strtoupper($currency_iso_code);

        //We do not have a currency for the base currency to be consistent with prices without currencies
        if ($currency_iso_code === $this->base_currency) {
            return null;
        }

        $currency = $this->em->getRepository(Currency::class)->findOneBy([
            'iso_code' => $currency_iso_code,
        ]);

        if (!$currency) {
            $currency = new Currency();
            $currency->setIsoCode($currency_iso_code);
            $currency->setName(Currencies::getName($currency_iso_code));
            $this->em->persist($currency);
            $this->em->flush();
        }

        return $currency;
    }

    protected function importOrderdetails(array $data): void
    {
        if (!isset($data['partdistributor'])) {
            throw new \RuntimeException('$data must contain a "partdistributor" key!');
        }

        foreach ($data['partdistributor'] as $partdistributor) {
            //Retrieve the part
            $part = $this->em->find(Part::class, (int) $partdistributor['part_id']);
            if (!$part instanceof Part) {
                throw new \RuntimeException(sprintf('Could not find part with ID %s', $partdistributor['part_id']));
            }
            //Retrieve the distributor
            $supplier = $this->em->find(Supplier::class, (int) $partdistributor['distributor_id']);
            if (!$supplier instanceof Supplier) {
                throw new \RuntimeException(sprintf('Could not find supplier with ID %s', $partdistributor['distributor_id']));
            }

            //Check if the part already has an orderdetail for this supplier and ordernumber
            if (empty($partdistributor['orderNumber']) && !empty($partdistributor['sku'])) {
                $spn = $partdistributor['sku'];
            } elseif (!empty($partdistributor['orderNumber']) && empty($partdistributor['sku'])) {
                $spn = $partdistributor['orderNumber'];
            } elseif (!empty($partdistributor['orderNumber']) && !empty($partdistributor['sku'])) {
                $spn = $partdistributor['orderNumber'] . ' (' . $partdistributor['sku'] . ')';
            } else {
                $spn = 'PartKeepr Import';
            }

            $orderdetail = $this->em->getRepository(Orderdetail::class)->findOneBy([
                'part' => $part,
                'supplier' => $supplier,
                'supplierpartnr' => $spn,
            ]);

            //When no orderdetail exists, create one
            if (!$orderdetail) {
                $orderdetail = new Orderdetail();
                $orderdetail->setSupplier($supplier);
                $orderdetail->setSupplierpartnr($spn);
                $part->addOrderdetail($orderdetail);
                $this->em->persist($orderdetail);
            }

            //Add the price information to the orderdetail
            if (!empty($partdistributor['price'])) {
                $pricedetail = new Pricedetail();
                $orderdetail->addPricedetail($pricedetail);
                //Partkeepr stores the price per item, we need to convert it to the price per packaging unit
                $price_per_item = BigDecimal::of($partdistributor['price']);
                $packaging_unit = $partdistributor['packagingUnit'] ?? 1;
                $pricedetail->setPrice($price_per_item->multipliedBy($packaging_unit));
                $pricedetail->setPriceRelatedQuantity($packaging_unit);
                //We have to set the minimum discount quantity to the packaging unit (PartKeepr does not know this concept)
                //But in Part-DB the minimum discount qty have to be unique across a orderdetail
                $pricedetail->setMinDiscountQuantity($packaging_unit);

                //Set the currency of the price
                if (!empty($partdistributor['currency'])) {
                    $currency = $this->getOrCreateCurrency($partdistributor['currency']);
                    $pricedetail->setCurrency($currency);
                }

                $this->em->persist($pricedetail);
            }

            $this->em->flush();
            //Clear the entity manager to improve performance
            $this->em->clear();
        }
    }

    /**
     * Returns the (parameter) unit symbol for the given ID.
     */
    protected function getUnitSymbol(array $data, int $id): string
    {
        foreach ($data['unit'] as $unit) {
            if ((int) $unit['id'] === $id) {
                return $unit['symbol'];
            }
        }

        throw new \RuntimeException(sprintf('Could not find unit with ID %s', $id));
    }
}
