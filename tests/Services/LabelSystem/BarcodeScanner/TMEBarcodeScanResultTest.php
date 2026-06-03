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

namespace App\Tests\Services\LabelSystem\BarcodeScanner;

use App\Services\LabelSystem\BarcodeScanner\BarcodeSourceType;
use App\Services\LabelSystem\BarcodeScanner\TMEBarcodeScanResult;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class TMEBarcodeScanResultTest extends TestCase
{
    private const EXAMPLE1 = 'QTY:1000 PN:SMD0603-5K1-1% PO:32723349/7 MFR:ROYALOHM MPN:0603SAF5101T5E CoO:TH RoHS https://www.tme.eu/details/SMD0603-5K1-1%25';
    private const EXAMPLE2 = 'QTY:5 PN:ETQP3M6R8KVP PO:31199729/3 MFR:PANASONIC MPN:ETQP3M6R8KVP RoHS https://www.tme.eu/details/ETQP3M6R8KVP';

    public function testIsTMEBarcode(): void
    {
        $this->assertFalse(TMEBarcodeScanResult::isTMEBarcode('invalid'));
        $this->assertFalse(TMEBarcodeScanResult::isTMEBarcode('QTY:5 PN:ABC MPN:XYZ'));
        $this->assertFalse(TMEBarcodeScanResult::isTMEBarcode(''));

        $this->assertTrue(TMEBarcodeScanResult::isTMEBarcode(self::EXAMPLE1));
        $this->assertTrue(TMEBarcodeScanResult::isTMEBarcode(self::EXAMPLE2));
    }

    public function testParseInvalidThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        TMEBarcodeScanResult::parse('not-a-tme-barcode');
    }

    public function testParseExample1(): void
    {
        $scan = TMEBarcodeScanResult::parse(self::EXAMPLE1);

        $this->assertSame(1000, $scan->quantity);
        $this->assertSame('SMD0603-5K1-1%', $scan->tmePartNumber);
        $this->assertSame('32723349/7', $scan->purchaseOrder);
        $this->assertSame('ROYALOHM', $scan->manufacturer);
        $this->assertSame('0603SAF5101T5E', $scan->mpn);
        $this->assertSame('TH', $scan->countryOfOrigin);
        $this->assertTrue($scan->rohs);
        $this->assertSame('https://www.tme.eu/details/SMD0603-5K1-1%25', $scan->productUrl);
        $this->assertSame(self::EXAMPLE1, $scan->rawInput);
    }

    public function testParseExample2(): void
    {
        $scan = TMEBarcodeScanResult::parse(self::EXAMPLE2);

        $this->assertSame(5, $scan->quantity);
        $this->assertSame('ETQP3M6R8KVP', $scan->tmePartNumber);
        $this->assertSame('31199729/3', $scan->purchaseOrder);
        $this->assertSame('PANASONIC', $scan->manufacturer);
        $this->assertSame('ETQP3M6R8KVP', $scan->mpn);
        $this->assertNull($scan->countryOfOrigin);
        $this->assertTrue($scan->rohs);
        $this->assertSame('https://www.tme.eu/details/ETQP3M6R8KVP', $scan->productUrl);
    }

    public function testGetSourceType(): void
    {
        $scan = TMEBarcodeScanResult::parse(self::EXAMPLE2);
        $this->assertSame(BarcodeSourceType::TME, $scan->getSourceType());
    }

    public function testParseUppercaseUrl(): void
    {
        $input = 'QTY:500 PN:M0.6W-10K MFR:ROYAL.OHM MPN:MF006FF1002A50 PO:7792659/8 HTTPS://WWW.TME.EU/DETAILS/M0.6W-10K';
        $this->assertTrue(TMEBarcodeScanResult::isTMEBarcode($input));

        $scan = TMEBarcodeScanResult::parse($input);
        $this->assertSame(500, $scan->quantity);
        $this->assertSame('M0.6W-10K', $scan->tmePartNumber);
        $this->assertSame('ROYAL.OHM', $scan->manufacturer);
        $this->assertSame('MF006FF1002A50', $scan->mpn);
        $this->assertSame('7792659/8', $scan->purchaseOrder);
        $this->assertSame('HTTPS://WWW.TME.EU/DETAILS/M0.6W-10K', $scan->productUrl);
    }

    public function testGetDecodedForInfoMode(): void
    {
        $scan = TMEBarcodeScanResult::parse(self::EXAMPLE1);
        $decoded = $scan->getDecodedForInfoMode();

        $this->assertSame('TME', $decoded['Barcode type']);
        $this->assertSame('SMD0603-5K1-1%', $decoded['TME Part No. (PN)']);
        $this->assertSame('0603SAF5101T5E', $decoded['MPN']);
        $this->assertSame('ROYALOHM', $decoded['Manufacturer (MFR)']);
        $this->assertSame('1000', $decoded['Qty']);
        $this->assertSame('Yes', $decoded['RoHS']);
    }
}
