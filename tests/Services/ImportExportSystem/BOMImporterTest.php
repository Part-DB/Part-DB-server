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
namespace App\Tests\Services\ImportExportSystem;

use App\Entity\ProjectSystem\Project;
use App\Entity\ProjectSystem\ProjectBOMEntry;
use App\Services\ImportExportSystem\BOMImporter;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\File;

class BOMImporterTest extends WebTestCase
{

    /**
     * @var BOMImporter
     */
    protected $service;

    protected function setUp(): void
    {
        //Get a service instance.
        self::bootKernel();
        $this->service = self::getContainer()->get(BOMImporter::class);
    }

    public function testImportFileIntoProject(): void
    {
        $input = <<<CSV
        "ID";"Bezeichner";"Footprint";"Stückzahl";"Bezeichnung";"Anbieter und Referenz";
        1;"R19,R17";"R_0805_2012Metric_Pad1.20x1.40mm_HandSolder";2;"4.7k";Test;;
        2;"D1";"D_DO-41_SOD81_P10.16mm_Horizontal";1;"1N5059";;;
        3;"J3,J5";"JST_XH_B5B-XH-AM_1x05_P2.50mm_Vertical";2;"DISPLAY";;;
        4;"C6";"CP_Radial_D6.3mm_P2.50mm";1;"47uF";;;
        CSV;

        $file = $this->createMock(File::class);
        $file->method('getContent')->willReturn($input);

        $project = new Project();
        $this->assertCount(0, $project->getBOMEntries());

        $bom_entries = $this->service->importFileIntoProject($file, $project, ['type' => 'kicad_pcbnew']);
        $this->assertContainsOnlyInstancesOf(ProjectBOMEntry::class, $bom_entries);
        $this->assertCount(4, $bom_entries);

        //Check that the BOM entries are added to the project
        $this->assertCount(4, $project->getBOMEntries());
    }

    public function testStringToBOMEntriesKiCADPCB(): void
    {
        //Test for german input
        $input = <<<CSV
        "ID";"Bezeichner";"Footprint";"Stückzahl";"Bezeichnung";"Anbieter und Referenz";
        1;"R19,R17";"R_0805_2012Metric_Pad1.20x1.40mm_HandSolder";2;"4.7k";Test;;
        2;"D1";"D_DO-41_SOD81_P10.16mm_Horizontal";1;"1N5059";;;
        3;"J3,J5";"JST_XH_B5B-XH-AM_1x05_P2.50mm_Vertical";2;"DISPLAY";;;
        4;"C6";"CP_Radial_D6.3mm_P2.50mm";1;"47uF";;;
        CSV;

        $bom = $this->service->stringToBOMEntries($input, ['type' => 'kicad_pcbnew']);

        $this->assertContainsOnlyInstancesOf(ProjectBOMEntry::class, $bom);
        $this->assertCount(4, $bom);

        $this->assertSame('R19,R17', $bom[0]->getMountnames());
        $this->assertSame(2.0, $bom[0]->getQuantity());
        $this->assertSame('4.7k (R_0805_2012Metric_Pad1.20x1.40mm_HandSolder)', $bom[0]->getName());
        $this->assertSame('Test', $bom[0]->getComment());

        //Test for english input
        $input = <<<CSV
        "Id";"Designator";"Package";"Quantity";"Designation";"Supplier and ref";
        1;"R19,R17";"R_0805_2012Metric_Pad1.20x1.40mm_HandSolder";2;"4.7k";Test;;
        2;"D1";"D_DO-41_SOD81_P10.16mm_Horizontal";1;"1N5059";;;
        3;"J3,J5";"JST_XH_B5B-XH-AM_1x05_P2.50mm_Vertical";2;"DISPLAY";;;
        4;"C6";"CP_Radial_D6.3mm_P2.50mm";1;"47uF";;;
        CSV;

        $bom = $this->service->stringToBOMEntries($input, ['type' => 'kicad_pcbnew']);

        $this->assertContainsOnlyInstancesOf(ProjectBOMEntry::class, $bom);
        $this->assertCount(4, $bom);

        $this->assertSame('R19,R17', $bom[0]->getMountnames());
        $this->assertSame(2.0, $bom[0]->getQuantity());
        $this->assertSame('4.7k (R_0805_2012Metric_Pad1.20x1.40mm_HandSolder)', $bom[0]->getName());
        $this->assertSame('Test', $bom[0]->getComment());
    }

    public function testStringToBOMEntriesKiCADPCBError(): void
    {
        $input = <<<CSV
        "ID";"Test";
        1;"R19,R17";"R_0805_2012Metric_Pad1.20x1.40mm_HandSolder";2;"4.7k";Test;;
        CSV;

        $this->expectException(\UnexpectedValueException::class);

        $this->service->stringToBOMEntries($input, ['type' => 'kicad_pcbnew']);
    }
}
