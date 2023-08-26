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

use App\Doctrine\Purger\ResetAutoIncrementORMPurger;
use App\Entity\Attachments\FootprintAttachment;
use App\Entity\Attachments\ManufacturerAttachment;
use App\Entity\Attachments\StorelocationAttachment;
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
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadataInfo;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

use function \count;

/**
 * This service is used to import the datastructures (categories, manufacturers, etc.) from a PartKeepr export.
 */
class PKDatastructureImporter
{

    use PKImportHelperTrait;

    public function __construct(EntityManagerInterface $em, PropertyAccessorInterface $propertyAccessor)
    {
        $this->em = $em;
        $this->propertyAccessor = $propertyAccessor;
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

        return is_countable($distributor_data) ? count($distributor_data) : 0;
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

        $this->importAttachments($data, 'manufacturericlogo', Manufacturer::class, 'manufacturer_id', ManufacturerAttachment::class);

        return is_countable($manufacturer_data) ? count($manufacturer_data) : 0;
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

        return is_countable($partunit_data) ? count($partunit_data) : 0;
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

        return is_countable($partcategory_data) ? count($partcategory_data) : 0;
    }

    /**
     * The common import functions for footprints and storeloactions
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

        return (is_countable($footprint_data) ? count($footprint_data) : 0) + (is_countable($footprintcategory_data) ? count($footprintcategory_data) : 0);
    }

    public function importFootprints(array $data): int
    {
        $count = $this->importElementsWithCategory($data, Footprint::class, 'footprint');

        //Footprints have both attachments and images
        $this->importAttachments($data, 'footprintattachment', Footprint::class, 'footprint_id', FootprintAttachment::class);
        $this->importAttachments($data, 'footprintimage', Footprint::class, 'footprint_id', FootprintAttachment::class);

        return $count;
    }

    public function importStorelocations(array $data): int
    {
        $count = $this->importElementsWithCategory($data, Storelocation::class, 'storagelocation');

        $this->importAttachments($data, 'storagelocationimage', Storelocation::class, 'storageLocation_id', StorelocationAttachment::class);

        return $count;
    }
}
