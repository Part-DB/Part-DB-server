<?php
/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2026 Jan Böhmer (https://github.com/jbtronics)
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

use App\Services\LabelSystem\BarcodeScanner\LCSCBarcodeScanResult;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class LCSCBarcodeScanResultTest extends TestCase
{
    public function testIsLCSCBarcode(): void
    {
        $this->assertFalse(LCSCBarcodeScanResult::isLCSCBarcode('invalid'));
        $this->assertFalse(LCSCBarcodeScanResult::isLCSCBarcode('LCSC-12345'));
        $this->assertFalse(LCSCBarcodeScanResult::isLCSCBarcode(''));

        $this->assertTrue(LCSCBarcodeScanResult::isLCSCBarcode('{pbn:PB1,on:ON1,pc:C138033,pm:RC0402FR-071ML,qty:10}'));
        $this->assertTrue(LCSCBarcodeScanResult::isLCSCBarcode('{pbn:PICK2506270148,on:GB2506270877,pc:C22437266,pm:IA0509S-2W,qty:3,mc:,cc:1,pdi:164234874,hp:null,wc:ZH}'));
    }

    public function testConstruct(): void
    {
        $raw = '{pbn:PB1,on:ON1,pc:C138033,pm:RC0402FR-071ML,qty:10}';
        $fields = ['pbn' => 'PB1', 'on' => 'ON1', 'pc' => 'C138033', 'pm' => 'RC0402FR-071ML', 'qty' => '10'];
        $scan = new LCSCBarcodeScanResult($fields, $raw);
        //Splitting up should work and assign the correct values to the properties:
        $this->assertSame('RC0402FR-071ML', $scan->mpn);
        $this->assertSame('C138033', $scan->lcscCode);

        //Fields and raw input should be preserved
        $this->assertSame($fields, $scan->fields);
        $this->assertSame($raw, $scan->rawInput);
    }

    public function testLCSCParseInvalidFormatThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        LCSCBarcodeScanResult::parse('not-an-lcsc-barcode');
    }

    public function testParse(): void
    {
        $scan = LCSCBarcodeScanResult::parse('{pbn:PICK2506270148,on:GB2506270877,pc:C22437266,pm:IA0509S-2W,qty:3,mc:,cc:1,pdi:164234874,hp:null,wc:ZH}');

        $this->assertSame('IA0509S-2W', $scan->mpn);
        $this->assertSame('C22437266', $scan->lcscCode);
        $this->assertSame('PICK2506270148', $scan->pickBatchNumber);
        $this->assertSame('GB2506270877', $scan->orderNumber);
        $this->assertSame(3, $scan->quantity);
        $this->assertSame('1', $scan->countryChannel);
        $this->assertSame('164234874', $scan->pdi);
        $this->assertSame('null', $scan->hp);
        $this->assertSame('ZH', $scan->warehouseCode);
    }

    public function testLCSCParseExtractsFields(): void
    {
        $scan = LCSCBarcodeScanResult::parse('{pbn:PB1,on:ON1,pc:C138033,pm:RC0402FR-071ML,qty:10}');

        $this->assertSame('RC0402FR-071ML', $scan->mpn);
        $this->assertSame('C138033', $scan->lcscCode);

        $decoded = $scan->getDecodedForInfoMode();
        $this->assertSame('LCSC', $decoded['Barcode type']);
        $this->assertSame('RC0402FR-071ML', $decoded['MPN (pm)']);
        $this->assertSame('C138033', $decoded['LCSC code (pc)']);
    }
}
