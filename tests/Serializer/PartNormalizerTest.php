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
namespace App\Tests\Serializer;

use App\Entity\Parts\Part;
use App\Entity\Parts\PartLot;
use App\Entity\PriceInformations\Orderdetail;
use App\Entity\PriceInformations\Pricedetail;
use App\Serializer\PartNormalizer;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class PartNormalizerTest extends WebTestCase
{
    /** @var PartNormalizer */
    protected DenormalizerInterface&NormalizerInterface $service;

    protected function setUp(): void
    {
        //Get a service instance.
        self::bootKernel();
        $this->service = self::getContainer()->get(PartNormalizer::class);

        //We need to inject the serializer into the normalizer, as we use it directly
        $serializer = self::getContainer()->get('serializer');
        $this->service->setNormalizer($serializer);
        $this->service->setDenormalizer($serializer);
    }

    public function testSupportsNormalization(): void
    {
        //Normalizer must only support Part objects (and child classes)
        $this->assertFalse($this->service->supportsNormalization(new \stdClass()));
        //Part serialization should only work with csv
        $this->assertFalse($this->service->supportsNormalization(new Part()));
        $this->assertTrue($this->service->supportsNormalization(new Part(), 'csv'));
    }

    public function testNormalize(): void
    {
        $part = new Part();
        $part->setName('Test Part');
        $partLot1 = new PartLot();
        $partLot1->setAmount(1);
        $partLot2 = new PartLot();
        $partLot2->setAmount(5);
        $part->addPartLot($partLot1);
        $part->addPartLot($partLot2);

        //Check that type field is not present in CSV export
        $data = $this->service->normalize($part, 'csv', ['groups' => ['simple']]);
        $this->assertSame('Test Part', $data['name']);
        $this->assertArrayNotHasKey('type', $data);
    }

    public function testSupportsDenormalization(): void
    {
        //Normalizer must only support Part type with array as input
        $this->assertFalse($this->service->supportsDenormalization(new \stdClass(), Part::class));
        $this->assertFalse($this->service->supportsDenormalization('string', Part::class));
        $this->assertFalse($this->service->supportsDenormalization(['a' => 'b'], \stdClass::class));
        $this->assertTrue($this->service->supportsDenormalization(['a' => 'b'], Part::class));
    }

    public function testDenormalize(): void
    {
        $input = [
            'name' => 'Test Part',
            'description' => 'Test Description',
            'notes' => 'Test Note', //Test key normalization
            'ipn' => 'Test IPN',
            'mpn' => 'Test MPN',
            'instock' => '5',
            'storage_location' => 'Test Storage Location',
            'supplier' => 'Test Supplier',
            'price' => '5.5',
            'supplier_part_number' => 'TEST123'
        ];

        $part = $this->service->denormalize($input, Part::class, 'json', ['groups' => ['import'], 'create_unknown_datastructures' => true]);
        $this->assertInstanceOf(Part::class, $part);
        $this->assertSame('Test Part', $part->getName());
        $this->assertSame('Test Description', $part->getDescription());
        $this->assertSame('Test Note', $part->getComment());
        $this->assertSame('Test IPN', $part->getIpn());
        $this->assertSame('Test MPN', $part->getManufacturerProductNumber());

        //Check that a new PartLot was created
        $this->assertCount(1, $part->getPartLots());
        /** @var PartLot $partLot */
        $partLot = $part->getPartLots()->first();
        $this->assertSame(5.0, $partLot->getAmount());
        $this->assertNotNull($partLot->getStorageLocation());
        $this->assertSame('Test Storage Location', $partLot->getStorageLocation()->getName());

        //Check that a new orderdetail was created
        $this->assertCount(1, $part->getOrderdetails());
        /** @var Orderdetail $orderDetail */
        $orderDetail = $part->getOrderdetails()->first();
        $this->assertNotNull($orderDetail->getSupplier());
        $this->assertSame('Test Supplier', $orderDetail->getSupplier()->getName());
        $this->assertSame('TEST123', $orderDetail->getSupplierPartNr());

        //Check that a pricedetail was created
        $this->assertCount(1, $orderDetail->getPricedetails());
        /** @var Pricedetail $priceDetail */
        $priceDetail = $orderDetail->getPricedetails()->first();
        $this->assertSame("5.50000", (string) $priceDetail->getPrice());
        //Must be in base currency
        $this->assertNull($priceDetail->getCurrency());
        //Must be for 1 part and 1 minimum order quantity
        $this->assertSame(1.0, $priceDetail->getPriceRelatedQuantity());
        $this->assertSame(1.0, $priceDetail->getMinDiscountQuantity());
    }
}
