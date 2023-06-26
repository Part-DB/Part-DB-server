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
namespace App\Tests\Services\Misc;

use App\Services\ImportExportSystem\PartKeeprImporter\MySQLDumpXMLConverter;
use PHPUnit\Framework\TestCase;

class MySQLDumpXMLConverterTest extends TestCase
{

    public function testConvertMySQLDumpXMLDataToArrayStructure(): void
    {
        $service = new MySQLDumpXMLConverter();

        //Load the test XML file
        $xml_string = file_get_contents(__DIR__.'/../../assets/partkeepr_import_test.xml');

        $result = $service->convertMySQLDumpXMLDataToArrayStructure($xml_string);

        //Check that the result is an array
        $this->assertIsArray($result);

        //Must contain 36 tables
        $this->assertCount(50, $result);

        //Must have a table called "footprints"
        $this->assertArrayHasKey('footprint', $result);

        //Must have 36 entry in the "footprints" table
        $this->assertCount(36, $result['footprint']);

        $this->assertSame('1', $result['footprint'][0]['id']);
        $this->assertSame('CBGA-32', $result['footprint'][0]['name']);

    }
}
