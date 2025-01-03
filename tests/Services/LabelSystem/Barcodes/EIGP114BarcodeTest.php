<?php
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

namespace App\Tests\Services\LabelSystem\Barcodes;

use App\Services\LabelSystem\Barcodes\EIGP114Barcode;
use PHPUnit\Framework\TestCase;

class EIGP114BarcodeTest extends TestCase
{

    public function testGuessBarcodeVendor(): void
    {
        //Generic barcode:

        $barcode = new EIGP114Barcode([
            'P' => '596-777A1-ND',
            '1P' => 'XAF4444',
            'Q' => '3',
            '10D' => '1452',
            '1T' => 'BF1103',
            '4L' => 'US',
        ]);

        $this->assertNull($barcode->guessBarcodeVendor());

        //Digikey barcode:
        $barcode = new EIGP114Barcode([
            'P' => '596-777A1-ND',
            '1P' => 'XAF4444',
            'Q' => '3',
            '10D' => '1452',
            '1T' => 'BF1103',
            '4L' => 'US',
            '13Z' => 'Digi-Key',
        ]);
        $this->assertEquals('digikey', $barcode->guessBarcodeVendor());

        //Mouser barcode:
        $barcode = new EIGP114Barcode([
            'P' => '596-777A1-ND',
            '1P' => 'XAF4444',
            'Q' => '3',
            '10D' => '1452',
            '1T' => 'BF1103',
            '4L' => 'US',
            '1V' => 'Mouser',
        ]);

        $this->assertEquals('mouser', $barcode->guessBarcodeVendor());

        //Farnell barcode:
        $barcode = new EIGP114Barcode([
            'P' => '596-777A1-ND',
            '1P' => 'XAF4444',
            'Q' => '3',
            '10D' => '1452',
            '1T' => 'BF1103',
            '4L' => 'US',
            '3P' => 'Farnell',
        ]);

        $this->assertEquals('element14', $barcode->guessBarcodeVendor());
    }

    public function testIsFormat06Code(): void
    {
        $this->assertFalse(EIGP114Barcode::isFormat06Code(''));
        $this->assertFalse(EIGP114Barcode::isFormat06Code('test'));
        $this->assertFalse(EIGP114Barcode::isFormat06Code('12232435ew4rf'));
        //Missing trailer
        $this->assertFalse(EIGP114Barcode::isFormat06Code("[)>\x1E06\x1DP596-777A1-ND\x1D1PXAF4444\x1DQ3\x1D10D1452\x1D1TBF1103\x1D4LUS"));

        //Valid code
        $this->assertTrue(EIGP114Barcode::isFormat06Code("[)>\x1E06\x1DP596-777A1-ND\x1D1PXAF4444\x1DQ3\x1D10D1452\x1D1TBF1103\x1D4LUS\x1E\x04"));
    }

    public function testParseFormat06CodeInvalid(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        EIGP114Barcode::parseFormat06Code('');
    }

    public function testParseFormat06Code(): void
    {
        $barcode = EIGP114Barcode::parseFormat06Code("[)>\x1E06\x1DP596-777A1-ND\x1D1PXAF4444\x1DQ3\x1D10D1452\x1D1TBF1103\x1D4LUS\x1E\x04");
        $this->assertEquals([
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
        $barcode = new EIGP114Barcode([
            'P' => '596-777A1-ND',
            '1P' => 'XAF4444',
            'Q' => '3',
            '10D' => '1452',
            '1T' => 'BF1103',
            '4L' => 'US',
        ]);

        $this->assertEquals('596-777A1-ND', $barcode->customerPartNumber);
        $this->assertEquals('XAF4444', $barcode->supplierPartNumber);
        $this->assertEquals(3, $barcode->quantity);
        $this->assertEquals('1452', $barcode->alternativeDateCode);
        $this->assertEquals('BF1103', $barcode->lotCode);
        $this->assertEquals('US', $barcode->countryOfOrigin);
    }
}
