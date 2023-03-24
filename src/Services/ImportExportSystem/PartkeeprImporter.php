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

namespace App\Services\ImportExportSystem;

use App\Doctrine\Purger\ResetAutoIncrementORMPurger;
use App\Doctrine\Purger\ResetAutoIncrementPurgerFactory;
use App\Entity\Base\AbstractDBElement;
use App\Entity\Base\AbstractStructuralDBElement;
use App\Entity\Contracts\TimeStampableInterface;
use App\Entity\Parameters\PartParameter;
use App\Entity\Parts\Category;
use App\Entity\Parts\Footprint;
use App\Entity\Parts\Manufacturer;
use App\Entity\Parts\MeasurementUnit;
use App\Entity\Parts\Part;
use App\Entity\Parts\PartLot;
use App\Entity\Parts\Storelocation;
use App\Entity\Parts\Supplier;
use Doctrine\Bundle\FixturesBundle\Purger\ORMPurgerFactory;
use Doctrine\Bundle\FixturesBundle\Purger\PurgerFactory;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadataInfo;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

class PartkeeprImporter
{

    protected EntityManagerInterface $em;
    protected PropertyAccessorInterface $propertyAccessor;

    public function __construct(EntityManagerInterface $em, PropertyAccessorInterface $propertyAccessor)
    {
        $this->em = $em;
        $this->propertyAccessor = $propertyAccessor;
    }

    public function purgeDatabaseForImport(): void
    {
        //Versions with "" are needed !!
        $purger = new ResetAutoIncrementORMPurger($this->em, ['users', '"users"', 'groups', '"groups"', 'u2f_keys', 'internal', 'migration_versions']);
        $purger->purge();
    }

    /**
     * Imports the distributors from the given data.
     * @param array $data The data to import (associated array, containing a 'distributor' key
     * @return int The number of imported distributors
     */
    public function importDistributors(array $data): int
    {
        if (!isset($data['distributor'])) {
            throw new \RuntimeException('$data must contain a "distributor" key!');
        }

        $distributor_data = $data['distributor'];

        foreach ($distributor_data as $distributor) {
            $supplier = new Supplier();
            $supplier->setName($distributor['name']);
            $supplier->setWebsite($distributor['url'] ?? '');
            $supplier->setAddress($distributor['address'] ?? '');
            $supplier->setPhoneNumber($distributor['phone'] ?? '');
            $supplier->setFaxNumber($distributor['fax'] ?? '');
            $supplier->setEmailAddress($distributor['email'] ?? '');
            $supplier->setComment($distributor['comment']);
            $supplier->setAutoProductUrl($distributor['skuurl'] ?? '');

            $this->setIDOfEntity($supplier, $distributor['id']);
            $this->em->persist($supplier);
        }

        $this->em->flush();

        return count($distributor_data);
    }

    public function importManufacturers(array $data): int
    {
        if (!isset($data['manufacturer'])) {
            throw new \RuntimeException('$data must contain a "manufacturer" key!');
        }

        $manufacturer_data = $data['manufacturer'];

        $max_id = 0;

        //Assign a parent manufacturer to all manufacturers, as partkeepr has a lot of manufacturers by default
        $parent_manufacturer = new Manufacturer();
        $parent_manufacturer->setName('PartKeepr');
        $parent_manufacturer->setNotSelectable(true);

        foreach ($manufacturer_data as $manufacturer) {
            $entity = new Manufacturer();
            $entity->setName($manufacturer['name']);
            $entity->setWebsite($manufacturer['url'] ?? '');
            $entity->setAddress($manufacturer['address'] ?? '');
            $entity->setPhoneNumber($manufacturer['phone'] ?? '');
            $entity->setFaxNumber($manufacturer['fax'] ?? '');
            $entity->setEmailAddress($manufacturer['email'] ?? '');
            $entity->setComment($manufacturer['comment']);
            $entity->setParent($parent_manufacturer);

            $this->setIDOfEntity($entity, $manufacturer['id']);
            $this->em->persist($entity);

            $max_id = max($max_id, $manufacturer['id']);
        }

        //Set the ID of the parent manufacturer to the max ID + 1, to avoid trouble with the auto increment
        $this->setIDOfEntity($parent_manufacturer, $max_id + 1);
        $this->em->persist($parent_manufacturer);

        $this->em->flush();

        return count($manufacturer_data);
    }

    public function importPartUnits(array $data): int
    {
        if (!isset($data['partunit'])) {
            throw new \RuntimeException('$data must contain a "partunit" key!');
        }

        $partunit_data = $data['partunit'];
        foreach ($partunit_data as $partunit) {
            $unit = new MeasurementUnit();
            $unit->setName($partunit['name']);
            $unit->setUnit($partunit['shortName'] ?? null);

            $this->setIDOfEntity($unit, $partunit['id']);
            $this->em->persist($unit);
        }

        $this->em->flush();

        return count($partunit_data);
    }

    public function importCategories(array $data): int
    {
        if (!isset($data['partcategory'])) {
            throw new \RuntimeException('$data must contain a "partcategory" key!');
        }

        $partcategory_data = $data['partcategory'];

        //In a first step, create all categories like they were a flat structure (so ignore the parent)
        foreach ($partcategory_data as $partcategory) {
            $category = new Category();
            $category->setName($partcategory['name']);
            $category->setComment($partcategory['description']);

            $this->setIDOfEntity($category, $partcategory['id']);
            $this->em->persist($category);
        }

        $this->em->flush();

        //In a second step, set the correct parent element
        foreach ($partcategory_data as $partcategory) {
            $this->setParent(Category::class, $partcategory['id'], $partcategory['parent_id']);
        }
        $this->em->flush();

        return count($partcategory_data);
    }

    /**
     * The common import functions for footprints and storeloactions
     * @param  array  $data
     * @param  string  $target_class
     * @param  string  $data_prefix
     * @return int
     */
    private function importElementsWithCategory(array $data, string $target_class, string $data_prefix): int
    {
        $key = $data_prefix;
        $category_key = $data_prefix.'category';

        if (!isset($data[$key])) {
            throw new \RuntimeException('$data must contain a "'. $key .'" key!');
        }
        if (!isset($data[$category_key])) {
            throw new \RuntimeException('$data must contain a "'. $category_key .'" key!');
        }

        //We import the footprints first, as we need the IDs of the footprints be our real DBs later (as we match the part import by ID)
        //As the footprints category is not existing yet, we just skip the parent field for now
        $footprint_data = $data[$key];
        $max_footprint_id = 0;
        foreach ($footprint_data as $footprint) {
            $entity = new $target_class();
            $entity->setName($footprint['name']);
            $entity->setComment($footprint['description'] ?? '');

            $this->setIDOfEntity($entity, $footprint['id']);
            $this->em->persist($entity);
            $max_footprint_id = max($max_footprint_id, (int) $footprint['id']);
        }

        //Import the footprint categories ignoring the parents for now
        //Their IDs are $max_footprint_id + $ID
        $footprintcategory_data = $data[$category_key];
        foreach ($footprintcategory_data as $footprintcategory) {
            $entity = new $target_class();
            $entity->setName($footprintcategory['name']);
            $entity->setComment($footprintcategory['description']);
            //Categories are not assignable to parts, so we set them to not selectable
            $entity->setNotSelectable(true);

            $this->setIDOfEntity($entity, $max_footprint_id + (int) $footprintcategory['id']);
            $this->em->persist($entity);
        }

        $this->em->flush();

        //Now we can correct the parents and category IDs of the parts
        foreach ($footprintcategory_data as $footprintcategory) {
            //We have to use the mapped IDs here, as the imported ID is not the effective ID
            if ($footprintcategory['parent_id']) {
                $this->setParent($target_class, $max_footprint_id + (int)$footprintcategory['id'],
                    $max_footprint_id + (int)$footprintcategory['parent_id']);
            }
        }
        foreach ($footprint_data as $footprint) {
            if ($footprint['category_id']) {
                $this->setParent($target_class, $footprint['id'],
                    $max_footprint_id + (int)$footprint['category_id']);
            }
        }

        $this->em->flush();

        return count($footprint_data) + count($footprintcategory_data);
    }

    public function importFootprints(array $data): int
    {
        return $this->importElementsWithCategory($data, Footprint::class, 'footprint');
    }

    public function importStorelocations(array $data): int
    {
        return $this->importElementsWithCategory($data, Storelocation::class, 'storagelocation');
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

        return count($part_data);
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
            if (!$manufacturer) {
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
            $entity->setUnit($this->getUnitSymbol($data, (int) $partparameter['unit_id']));

            $entity->setValueMin($partparameter['normalizedMinValue'] ?? null);
            $entity->setValueTypical($partparameter['normalizedValue'] ?? null);
            $entity->setValueMax($partparameter['normalizedMaxValue'] ?? null);

            $part = $this->em->find(Part::class, (int) $partparameter['part_id']);
            if (!$part) {
                throw new \RuntimeException(sprintf('Could not find part with ID %s', $partparameter['part_id']));
            }

            $part->addParameter($entity);
            $this->em->persist($entity);
        }
        $this->em->flush();
    }



    /**
     * Returns the (parameter) unit symbol for the given ID.
     * @param  array  $data
     * @param  int  $id
     * @return string
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

    /**
     * Assigns the parent to the given entity, using the numerical IDs from the imported data.
     * @param  string  $class
     * @param int|string $element_id
     * @param int|string $parent_id
     * @return AbstractStructuralDBElement The structural element that was modified (with $element_id)
     */
    protected function setParent(string $class, $element_id, $parent_id): AbstractStructuralDBElement
    {
        $element = $this->em->find($class, (int) $element_id);
        if (!$element) {
            throw new \RuntimeException(sprintf('Could not find element with ID %s', $element_id));
        }

        //If the parent is null, we're done
        if (!$parent_id) {
            return $element;
        }

        $parent = $this->em->find($class, (int) $parent_id);
        if (!$parent) {
            throw new \RuntimeException(sprintf('Could not find parent with ID %s', $parent_id));
        }

        $element->setParent($parent);
        return $element;
    }

    /**
     * Sets the given field of the given entity to the entity with the given ID.
     * @return AbstractDBElement
     */
    protected function setAssociationField(AbstractDBElement $element, string $field, string $other_class, $other_id): AbstractDBElement
    {
        //If the parent is null, set the field to null and we're done
        if (!$other_id) {
            $this->propertyAccessor->setValue($element, $field, null);
            return $element;
        }

        $parent = $this->em->find($other_class, (int) $other_id);
        if (!$parent) {
            throw new \RuntimeException(sprintf('Could not find other_class with ID %s', $other_id));
        }

        $this->propertyAccessor->setValue($element, $field, $parent);
        return $element;
    }

    /**
     * Set the ID of an entity to a specific value. Must be called before persisting the entity, but before flushing.
     * @param  AbstractDBElement  $element
     * @param  int|string $id
     * @return void
     */
    protected function setIDOfEntity(AbstractDBElement $element, $id): void
    {
        if (!is_int($id) && !is_string($id)) {
            throw new \InvalidArgumentException('ID must be an integer or string');
        }

        $id = (int) $id;

        $metadata = $this->em->getClassMetadata(get_class($element));
        $metadata->setIdGeneratorType(ClassMetadataInfo::GENERATOR_TYPE_NONE);
        $metadata->setIdGenerator(new \Doctrine\ORM\Id\AssignedGenerator());
        $metadata->setIdentifierValues($element, ['id' => $id]);
    }

    /**
     * Sets the creation date of an entity to a specific value.
     * @return void
     * @throws \Exception
     */
    protected function setCreationDate(TimeStampableInterface $entity, ?string $datetime_str)
    {
        if ($datetime_str) {
            $date = new \DateTime($datetime_str);
        } else {
            $date = null; //Null means "now" at persist time
        }

        $reflectionClass = new \ReflectionClass($entity);
        $property = $reflectionClass->getProperty('addedDate');
        $property->setAccessible(true);
        $property->setValue($entity, $date);
    }
}