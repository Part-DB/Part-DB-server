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
 * Copyright (C) 2019 - 2020 Jan Böhmer (https://github.com/jbtronics)
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

namespace App\Tests\Services\LabelSystem\BarcodeScanner;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use App\Entity\LabelSystem\LabelSupportedElement;
use App\Services\LabelSystem\BarcodeScanner\BarcodeRedirector;
use App\Services\LabelSystem\BarcodeScanner\BarcodeSourceType;
use App\Services\LabelSystem\BarcodeScanner\LocalBarcodeScanResult;
use Doctrine\ORM\EntityNotFoundException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use App\Services\LabelSystem\BarcodeScanner\EIGP114BarcodeScanResult;
use App\Services\LabelSystem\BarcodeScanner\LCSCBarcodeScanResult;
use App\Services\LabelSystem\BarcodeScanner\BarcodeScanResultInterface;
use InvalidArgumentException;


final class BarcodeRedirectorTest extends KernelTestCase
{
    private ?BarcodeRedirector $service = null;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->service = self::getContainer()->get(BarcodeRedirector::class);
    }

    public static function urlDataProvider(): \Iterator
    {
        yield [new LocalBarcodeScanResult(LabelSupportedElement::PART, 1, BarcodeSourceType::INTERNAL), '/en/part/1'];
        //Part lot redirects to Part info page (Part lot 1 is associated with part 3)
        yield [new LocalBarcodeScanResult(LabelSupportedElement::PART_LOT, 1, BarcodeSourceType::INTERNAL), '/en/part/3?highlightLot=1'];
        yield [new LocalBarcodeScanResult(LabelSupportedElement::STORELOCATION, 1, BarcodeSourceType::INTERNAL), '/en/store_location/1/parts'];
    }

    #[DataProvider('urlDataProvider')]
    #[Group('DB')]
    public function testGetRedirectURL(LocalBarcodeScanResult $scanResult, string $url): void
    {
        $this->assertSame($url, $this->service->getRedirectURL($scanResult));
    }

    public function testGetRedirectEntityNotFount(): void
    {
        $this->expectException(EntityNotFoundException::class);
        //If we encounter an invalid lot, we must throw an exception
        $this->service->getRedirectURL(new LocalBarcodeScanResult(LabelSupportedElement::PART_LOT,
            12_345_678, BarcodeSourceType::INTERNAL));
    }

    public function testGetRedirectURLThrowsOnUnknownScanType(): void
    {
        $unknown = new class implements BarcodeScanResultInterface {
            public function getDecodedForInfoMode(): array
            {
                return [];
            }
        };

        $this->expectException(InvalidArgumentException::class);
        $this->service->getRedirectURL($unknown);
    }

    public function testEIGPBarcodeWithoutSupplierPartNumberThrowsEntityNotFound(): void
    {
        $scan = new EIGP114BarcodeScanResult([]);

        $this->expectException(EntityNotFoundException::class);
        $this->service->getRedirectURL($scan);
    }

    public function testEIGPBarcodeResolvePartOrNullReturnsNullWhenNotFound(): void
    {
        $scan = new EIGP114BarcodeScanResult([]);

        $this->assertNull($this->service->resolvePartOrNull($scan));
    }

    public function testLCSCBarcodeResolvePartOrNullReturnsNullWhenNotFound(): void
    {
        $scan = new LCSCBarcodeScanResult(
            fields: ['pc' => 'C0000000', 'pm' => ''],
            rawInput: '{pc:C0000000,pm:}'
        );

        $this->assertNull($this->service->resolvePartOrNull($scan));
    }


    public function testLCSCBarcodeMissingPmThrowsEntityNotFound(): void
    {
        // pc present but no pm => getPartFromLCSC() will throw EntityNotFoundException
        // because it falls back to PM when PC doesn't match anything.
        $scan = new LCSCBarcodeScanResult(
            fields: ['pc' => 'C0000000', 'pm' => ''], // pm becomes null via getPM()
            rawInput: '{pc:C0000000,pm:}'
        );

        $this->expectException(EntityNotFoundException::class);
        $this->service->getRedirectURL($scan);
    }
}
