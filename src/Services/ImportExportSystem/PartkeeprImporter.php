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
use App\Entity\Parts\Manufacturer;
use App\Entity\Parts\MeasurementUnit;
use App\Entity\Parts\Part;
use App\Entity\Parts\Supplier;
use Doctrine\Bundle\FixturesBundle\Purger\ORMPurgerFactory;
use Doctrine\Bundle\FixturesBundle\Purger\PurgerFactory;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadataInfo;

class PartkeeprImporter
{

    protected EntityManagerInterface $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
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

    public function setIDOfEntity(AbstractDBElement $element, int $id): void
    {
        $metadata = $this->em->getClassMetadata(get_class($element));
        $metadata->setIdGeneratorType(ClassMetadataInfo::GENERATOR_TYPE_NONE);
        $metadata->setIdGenerator(new \Doctrine\ORM\Id\AssignedGenerator());
        $metadata->setIdentifierValues($element, ['id' => $id]);
    }
}