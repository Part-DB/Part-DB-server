<?php

declare(strict_types=1);

/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2025 Jan Böhmer (https://github.com/jbtronics)
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

use App\Services\LabelSystem\BarcodeScanner\EIGP114BarcodeScanResult;
use PHPUnit\Framework\TestCase;

class EIGP114BarcodeScanResultTest extends TestCase
{

    public function testGuessBarcodeVendor(): void
    {
        //Generic barcode:

        $barcode = new EIGP114BarcodeScanResult([
            'P' => '596-777A1-ND',
            '1P' => 'XAF4444',
            'Q' => '3',
            '10D' => '1452',
            '1T' => 'BF1103',
            '4L' => 'US',
        ]);

        $this->assertNull($barcode->guessBarcodeVendor());

        //Digikey barcode:
        $barcode = new EIGP114BarcodeScanResult([
            'P' => '596-777A1-ND',
            '1P' => 'XAF4444',
            'Q' => '3',
            '10D' => '1452',
            '1T' => 'BF1103',
            '4L' => 'US',
            '13Z' => 'Digi-Key',
        ]);
        $this->assertSame('digikey', $barcode->guessBarcodeVendor());

        //Mouser barcode:
        $barcode = new EIGP114BarcodeScanResult([
            'P' => '596-777A1-ND',
            '1P' => 'XAF4444',
            'Q' => '3',
            '10D' => '1452',
            '1T' => 'BF1103',
            '4L' => 'US',
            '1V' => 'Mouser',
        ]);

        $this->assertSame('mouser', $barcode->guessBarcodeVendor());

        //Farnell barcode:
        $barcode = new EIGP114BarcodeScanResult([
            'P' => '596-777A1-ND',
            '1P' => 'XAF4444',
            'Q' => '3',
            '10D' => '1452',
            '1T' => 'BF1103',
            '4L' => 'US',
            '3P' => 'Farnell',
        ]);

        $this->assertSame('element14', $barcode->guessBarcodeVendor());
    }

    public function testIsFormat06Code(): void
    {
        $this->assertFalse(EIGP114BarcodeScanResult::isFormat06Code(''));
        $this->assertFalse(EIGP114BarcodeScanResult::isFormat06Code('test'));
        $this->assertFalse(EIGP114BarcodeScanResult::isFormat06Code('12232435ew4rf'));

        //Valid code (with trailer)
        $this->assertTrue(EIGP114BarcodeScanResult::isFormat06Code("[)>\x1E06\x1DP596-777A1-ND\x1D1PXAF4444\x1DQ3\x1D10D1452\x1D1TBF1103\x1D4LUS\x1E\x04"));

        //Valid code (digikey, without trailer)
        $this->assertTrue(EIGP114BarcodeScanResult::isFormat06Code("[)>\x1e06\x1dPQ1045-ND\x1d1P364019-01\x1d30PQ1045-ND\x1dK12432 TRAVIS FOSS P\x1d1K85732873\x1d10K103332956\x1d9D231013\x1d1TQJ13P\x1d11K1\x1d4LTW\x1dQ3\x1d11ZPICK\x1d12Z7360988\x1d13Z999999\x1d20Z0000000000000000000000000000000000000000000000000000000000000000000000000000000000000"));
    }

    public function testParseFormat06CodeInvalid(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        EIGP114BarcodeScanResult::parseFormat06Code('');
    }

    public function testParseFormat06Code(): void
    {
        $barcode = EIGP114BarcodeScanResult::parseFormat06Code("[)>\x1E06\x1DP596-777A1-ND\x1D1PXAF4444\x1DQ3\x1D10D1452\x1D1TBF1103\x1D4LUS\x1E\x04");
        $this->assertSame([
            'P' => '596-777A1-ND',
            '1P' => 'XAF4444',
            'Q' => '3',
            '10D' => '1452',
            '1T' => 'BF1103',
            '4L' => 'US',
        ], $barcode->data);
    }

    public function testDataParsing(): void
    {
        $barcode = new EIGP114BarcodeScanResult([
            'P' => '596-777A1-ND',
            '1P' => 'XAF4444',
            'Q' => '3',
            '10D' => '1452',
            '1T' => 'BF1103',
            '4L' => 'US',
        ]);

        $this->assertSame('596-777A1-ND', $barcode->customerPartNumber);
        $this->assertSame('XAF4444', $barcode->supplierPartNumber);
        $this->assertSame(3, $barcode->quantity);
        $this->assertSame('1452', $barcode->alternativeDateCode);
        $this->assertSame('BF1103', $barcode->lotCode);
        $this->assertSame('US', $barcode->countryOfOrigin);
    }

    public function testDigikeyParsing(): void
    {
        $barcode = EIGP114BarcodeScanResult::parseFormat06Code("[)>\x1e06\x1dPQ1045-ND\x1d1P364019-01\x1d30PQ1045-ND\x1dK12432 TRAVIS FOSS P\x1d1K85732873\x1d10K103332956\x1d9D231013\x1d1TQJ13P\x1d11K1\x1d4LTW\x1dQ3\x1d11ZPICK\x1d12Z7360988\x1d13Z999999\x1d20Z0000000000000000000000000000000000000000000000000000000000000000000000000000000000000");

        $this->assertSame('digikey', $barcode->guessBarcodeVendor());

        $this->assertSame('Q1045-ND', $barcode->customerPartNumber);
        $this->assertSame('364019-01', $barcode->supplierPartNumber);
        $this->assertSame(3, $barcode->quantity);
        $this->assertSame('231013', $barcode->dateCode);
        $this->assertSame('QJ13P', $barcode->lotCode);
        $this->assertSame('TW', $barcode->countryOfOrigin);
        $this->assertSame('Q1045-ND', $barcode->digikeyPartNumber);
        $this->assertSame('85732873', $barcode->digikeySalesOrderNumber);
        $this->assertSame('103332956', $barcode->digikeyInvoiceNumber);
        $this->assertSame('PICK', $barcode->digikeyLabelType);
        $this->assertSame('7360988', $barcode->digikeyPartID);
        $this->assertSame('999999', $barcode->digikeyNA);
        $this->assertSame('0000000000000000000000000000000000000000000000000000000000000000000000000000000000000', $barcode->digikeyPadding);
    }
}
