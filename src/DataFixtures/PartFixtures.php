<?php
/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2022 Jan Böhmer (https://github.com/jbtronics)
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

/**
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 * Copyright (C) 2019 - 2022 Jan Böhmer (https://github.com/jbtronics)
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

namespace App\DataFixtures;

use App\Entity\Attachments\AttachmentType;
use App\Entity\Attachments\PartAttachment;
use App\Entity\Parts\Category;
use App\Entity\Parts\Footprint;
use App\Entity\Parts\Manufacturer;
use App\Entity\Parts\ManufacturingStatus;
use App\Entity\Parts\Part;
use App\Entity\Parts\PartLot;
use App\Entity\Parts\StorageLocation;
use App\Entity\Parts\Supplier;
use App\Entity\PriceInformations\Orderdetail;
use App\Entity\PriceInformations\Pricedetail;
use Brick\Math\BigDecimal;
use DateTime;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectManager;

class PartFixtures extends Fixture implements DependentFixtureInterface
{
    public function __construct(protected EntityManagerInterface $em)
    {
    }

    public function load(ObjectManager $manager): void
    {

        /** Simple part */
        $part = new Part();
        $part->setName('Part 1');
        $part->setCategory($manager->find(Category::class, 1));
        $this->addReference(Part::class . '_1', $part);
        $manager->persist($part);

        /** More complex part */
        $part = new Part();
        $part->setName('Part 2');
        $part->setCategory($manager->find(Category::class, 1));
        $part->setFootprint($manager->find(Footprint::class, 1));
        $part->setManufacturer($manager->find(Manufacturer::class, 1));
        $part->setTags('test, Test, Part2');
        $part->setMass(100.2);
        $part->setIpn('IPN123');
        $part->setNeedsReview(true);
        $part->setManufacturingStatus(ManufacturingStatus::ACTIVE);
        $this->addReference(Part::class . '_2', $part);
        $manager->persist($part);

        /** Part with orderdetails, storelocations and Attachments */
        $part = new Part();
        $part->setFavorite(true);
        $part->setName('Part 3');
        $part->setCategory($manager->find(Category::class, 1));
        $partLot1 = new PartLot();
        $partLot1->setAmount(1.0);
        $partLot1->setStorageLocation($manager->find(StorageLocation::class, 1));
        $part->addPartLot($partLot1);


        $partLot2 = new PartLot();
        $partLot2->setExpirationDate(new \DateTimeImmutable());
        $partLot2->setComment('Test');
        $partLot2->setNeedsRefill(true);
        $partLot2->setStorageLocation($manager->find(StorageLocation::class, 3));
        $partLot2->setUserBarcode('lot2_vendor_barcode');
        $part->addPartLot($partLot2);

        $orderdetail = new Orderdetail();
        $orderdetail->setSupplier($manager->find(Supplier::class, 1));
        $orderdetail->addPricedetail((new Pricedetail())->setPriceRelatedQuantity(1.0)->setPrice(BigDecimal::of('10.0')));
        $orderdetail->addPricedetail((new Pricedetail())->setPriceRelatedQuantity(10.0)->setPrice(BigDecimal::of('15.0')));
        $part->addOrderdetail($orderdetail);

        $orderdetail = new Orderdetail();
        $orderdetail->setSupplierpartnr('BC 547');
        $orderdetail->setObsolete(true);
        $orderdetail->setSupplier($manager->find(Supplier::class, 1));
        $orderdetail->addPricedetail((new Pricedetail())->setPriceRelatedQuantity(1.0)->setPrice(BigDecimal::of('10.0')));
        $orderdetail->addPricedetail((new Pricedetail())->setPriceRelatedQuantity(10.0)->setPrice(BigDecimal::of('15.1')));
        $part->addOrderdetail($orderdetail);

        $attachment = new PartAttachment();
        $attachment->setName('TestAttachment');
        $attachment->setURL('www.foo.bar');
        $attachment->setAttachmentType($manager->find(AttachmentType::class, 1));
        $part->addAttachment($attachment);

        $attachment = new PartAttachment();
        $attachment->setName('Test2');
        $attachment->setInternalPath('invalid');
        $attachment->setShowInTable(true);
        $attachment->setAttachmentType($manager->find(AttachmentType::class, 1));
        $part->addAttachment($attachment);

        $this->addReference(Part::class . '_3', $part);

        $manager->persist($part);
        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            DataStructureFixtures::class
        ];
    }
}
