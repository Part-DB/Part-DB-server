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
namespace App\Tests\Services\InfoProviderSystem;

use App\Entity\Attachments\AttachmentType;
use App\Entity\Parts\ManufacturingStatus;
use App\Services\InfoProviderSystem\DTOs\FileDTO;
use App\Services\InfoProviderSystem\DTOs\ParameterDTO;
use App\Services\InfoProviderSystem\DTOs\PartDetailDTO;
use App\Services\InfoProviderSystem\DTOs\PriceDTO;
use App\Services\InfoProviderSystem\DTOs\PurchaseInfoDTO;
use App\Services\InfoProviderSystem\DTOtoEntityConverter;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class DTOtoEntityConverterTest extends WebTestCase
{

    private ?DTOtoEntityConverter $service = null;

    public function setUp(): void
    {
        self::bootKernel();
        $this->service = self::getContainer()->get(DTOtoEntityConverter::class);
    }

    public function testConvertParameter(): void
    {
        $dto = new ParameterDTO(
            name: 'TestParameter',
            value_text: 'Text',
            value_typ: 10.0, value_min: 0.0, value_max: 100.0,
            unit: 'kg', symbol: 'TP', group: 'TestGroup'
        );

        $entity = $this->service->convertParameter($dto);

        $this->assertSame($dto->name, $entity->getName());
        $this->assertEquals($dto->value_text, $entity->getValueText());
        $this->assertEquals($dto->value_typ, $entity->getValueTypical());
        $this->assertEquals($dto->value_min, $entity->getValueMin());
        $this->assertEquals($dto->value_max, $entity->getValueMax());
        $this->assertEquals($dto->unit, $entity->getUnit());
        $this->assertEquals($dto->symbol, $entity->getSymbol());
        $this->assertEquals($dto->group, $entity->getGroup());
    }

    public function testConvertPriceOtherCurrency(): void
    {
        $dto = new PriceDTO(
            minimum_discount_amount: 5,
            price: "10.0",
            currency_iso_code: 'CNY',
            includes_tax: true,
            price_related_quantity: 10.0,
        );

        $entity = $this->service->convertPrice($dto);
        $this->assertSame($dto->minimum_discount_amount, $entity->getMinDiscountQuantity());
        $this->assertSame((float) $dto->price, (float) (string) $entity->getPrice());
        $this->assertEquals($dto->price_related_quantity, $entity->getPriceRelatedQuantity());

        //For non-base currencies, a new currency entity is created
        $currency = $entity->getCurrency();
        $this->assertEquals($dto->currency_iso_code, $currency->getIsoCode());
    }

    public function testConvertPriceBaseCurrency(): void
    {
        $dto = new PriceDTO(
            minimum_discount_amount: 5,
            price: "10.0",
            currency_iso_code: 'EUR',
            includes_tax: true,
        );

        $entity = $this->service->convertPrice($dto);

        //For base currencies, the currency field is null
        $this->assertNull($entity->getCurrency());
    }

    public function testConvertPurchaseInfo(): void
    {
        $prices = [
            new PriceDTO(1, "10.0", 'EUR'),
            new PriceDTO(5, "9.0", 'EUR'),
        ];

        $dto = new PurchaseInfoDTO(
            distributor_name: 'TestDistributor',
            order_number: 'TestOrderNumber',
            prices: $prices,
            product_url: 'https://example.com',
        );

        $entity = $this->service->convertPurchaseInfo($dto);

        $this->assertSame($dto->distributor_name, $entity->getSupplier()->getName());
        $this->assertSame($dto->order_number, $entity->getSupplierPartNr());
        $this->assertEquals($dto->product_url, $entity->getSupplierProductUrl());
    }

    public function testConvertFileWithName(): void
    {
        $dto = new FileDTO(url: 'https://invalid.com/file.pdf', name: 'TestFile');
        $type = new AttachmentType();


        $entity = $this->service->convertFile($dto, $type);

        $this->assertEquals($dto->name, $entity->getName());
        $this->assertSame($dto->url, $entity->getUrl());
        $this->assertEquals($type, $entity->getAttachmentType());
    }

    public function testConvertFileWithoutName(): void
    {
        $dto = new FileDTO(url: 'https://invalid.invalid/file.pdf');
        $type = new AttachmentType();


        $entity = $this->service->convertFile($dto, $type);

        //If no name is given, the name is derived from the url
        $this->assertSame('file.pdf', $entity->getName());
        $this->assertSame($dto->url, $entity->getUrl());
        $this->assertEquals($type, $entity->getAttachmentType());
    }

    public function testConvertPart(): void
    {
        $parameters = [new ParameterDTO('Test', 'Test')];
        $datasheets = [new FileDTO('https://invalid.invalid/file.pdf'), new FileDTO('https://invalid.invalid/file.pdf', name: 'TestFile')];
        $images = [new FileDTO('https://invalid.invalid/image.png'), new FileDTO('https://invalid.invalid/image2.png', name: 'TestImage2'), new FileDTO('https://invalid.invalid/image2.png')];
        $shopping_infos = [new  PurchaseInfoDTO('TestDistributor', 'TestOrderNumber', [new PriceDTO(1, "10.0", 'EUR')])];

        $dto = new PartDetailDTO(
            provider_key: 'test_provider', provider_id: 'test_id', provider_url: 'https://invalid.invalid/test_id',
            name: 'TestPart', description: 'TestDescription', category: 'TestCategory',
            manufacturer: 'TestManufacturer', mpn: 'TestMPN', manufacturing_status: ManufacturingStatus::EOL,
            preview_image_url:  'https://invalid.invalid/image.png',
            footprint: 'DIP8', notes: 'TestNotes', mass: 10.4,
            parameters: $parameters, datasheets: $datasheets, vendor_infos: $shopping_infos, images: $images
        );

        $entity = $this->service->convertPart($dto);

        $this->assertSame($dto->name, $entity->getName());
        $this->assertSame($dto->description, $entity->getDescription());
        $this->assertSame($dto->notes, $entity->getComment());

        $this->assertSame($dto->manufacturer, $entity->getManufacturer()->getName());
        $this->assertSame($dto->mpn, $entity->getManufacturerProductNumber());
        $this->assertSame($dto->manufacturing_status, $entity->getManufacturingStatus());

        $this->assertEquals($dto->mass, $entity->getMass());
        $this->assertEquals($dto->footprint, $entity->getFootprint());

        //We just check that the lenghts of parameters, datasheets, images and shopping infos are the same
        //The actual content is tested in the corresponding tests
        $this->assertCount(count($parameters), $entity->getParameters());
        $this->assertCount(count($shopping_infos), $entity->getOrderdetails());

        //Datasheets and images are stored as attachments and the duplicates, should be filtered out
        $this->assertCount(3, $entity->getAttachments());
        //The attachments should have the name of the named duplicate file
        $image1 = $entity->getAttachments()[0];
        $this->assertSame('Main image', $image1->getName());

        $image1 = $entity->getAttachments()[1];

        $datasheet = $entity->getAttachments()[2];
        $this->assertSame('TestFile', $datasheet->getName());
    }
}
