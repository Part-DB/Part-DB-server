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

use App\Entity\PriceInformations\Orderdetail;
use App\Entity\Parts\Category;
use App\Entity\Parts\Part;
use App\Entity\Parts\Supplier;
use App\Entity\ProjectSystem\Project;
use App\Entity\ProjectSystem\ProjectBOMEntry;
use App\Services\ImportExportSystem\BOMImporter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class BOMImporterTest extends WebTestCase
{

    /**
     * @var BOMImporter
     */
    protected $service;

    /**
     * @var EntityManagerInterface
     */
    protected $entityManager;

    protected function setUp(): void
    {
        //Get a service instance.
        self::bootKernel();
        $this->service = self::getContainer()->get(BOMImporter::class);
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
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

        $file = $this->createMock(UploadedFile::class);
        $file->method('getContent')->willReturn($input);
        $file->method('getClientOriginalName')->willReturn('import.kicad_pcb');
        $file->method('getClientOriginalExtension')->willReturn('kicad_pcb');

        $project = new Project();
        $this->assertCount(0, $project->getBOMEntries());

        $importerResult = $this->service->importFileIntoProject($file, $project, ['type' => 'kicad_pcbnew']);
        $this->assertContainsOnlyInstancesOf(ProjectBOMEntry::class, $importerResult->getBomEntries());
        $this->assertCount(4, $importerResult->getBomEntries());

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

        $project = new Project();
        $bom = $this->service->stringToBOMEntries($project, $input, ['type' => 'kicad_pcbnew']);

        $this->assertContainsOnlyInstancesOf(ProjectBOMEntry::class, $bom);
        $this->assertCount(4, $bom);

        $this->assertSame('R19,R17', $bom[0]->getMountnames());
        $this->assertEqualsWithDelta(2.0, $bom[0]->getQuantity(), PHP_FLOAT_EPSILON);
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

        $project = new Project();
        $bom = $this->service->stringToBOMEntries($project, $input, ['type' => 'kicad_pcbnew']);

        $this->assertContainsOnlyInstancesOf(ProjectBOMEntry::class, $bom);
        $this->assertCount(4, $bom);

        $this->assertSame('R19,R17', $bom[0]->getMountnames());
        $this->assertEqualsWithDelta(2.0, $bom[0]->getQuantity(), PHP_FLOAT_EPSILON);
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

        $project = new Project();
        $this->service->stringToBOMEntries($project, $input, ['type' => 'kicad_pcbnew']);
    }

    public function testDetectFields(): void
    {
        $input = <<<CSV
        "Reference","Value","Footprint","Quantity","MPN","Manufacturer","LCSC SPN","Mouser SPN"
        CSV;

        $fields = $this->service->detectFields($input);

        $this->assertIsArray($fields);
        $this->assertCount(8, $fields);
        $this->assertContains('Reference', $fields);
        $this->assertContains('Value', $fields);
        $this->assertContains('Footprint', $fields);
        $this->assertContains('Quantity', $fields);
        $this->assertContains('MPN', $fields);
        $this->assertContains('Manufacturer', $fields);
        $this->assertContains('LCSC SPN', $fields);
        $this->assertContains('Mouser SPN', $fields);
    }

    public function testDetectFieldsWithQuotes(): void
    {
        $input = <<<CSV
        "Reference","Value","Footprint","Quantity","MPN","Manufacturer","LCSC SPN","Mouser SPN"
        CSV;

        $fields = $this->service->detectFields($input);

        $this->assertIsArray($fields);
        $this->assertCount(8, $fields);
        $this->assertEquals('Reference', $fields[0]);
        $this->assertEquals('Value', $fields[1]);
    }

    public function testDetectFieldsWithSemicolon(): void
    {
        $input = <<<CSV
        "Reference";"Value";"Footprint";"Quantity";"MPN";"Manufacturer";"LCSC SPN";"Mouser SPN"
        CSV;

        $fields = $this->service->detectFields($input, ';');

        $this->assertIsArray($fields);
        $this->assertCount(8, $fields);
        $this->assertEquals('Reference', $fields[0]);
        $this->assertEquals('Value', $fields[1]);
    }

    public function testGetAvailableFieldTargets(): void
    {
        $targets = $this->service->getAvailableFieldTargets();

        $this->assertIsArray($targets);
        $this->assertArrayHasKey('Designator', $targets);
        $this->assertArrayHasKey('Quantity', $targets);
        $this->assertArrayHasKey('Value', $targets);
        $this->assertArrayHasKey('Package', $targets);
        $this->assertArrayHasKey('MPN', $targets);
        $this->assertArrayHasKey('Manufacturer', $targets);
        $this->assertArrayHasKey('Part-DB ID', $targets);
        $this->assertArrayHasKey('Comment', $targets);

        // Check structure of a target
        $this->assertArrayHasKey('label', $targets['Designator']);
        $this->assertArrayHasKey('description', $targets['Designator']);
        $this->assertArrayHasKey('required', $targets['Designator']);
        $this->assertArrayHasKey('multiple', $targets['Designator']);

        $this->assertTrue($targets['Designator']['required']);
        $this->assertTrue($targets['Quantity']['required']);
        $this->assertFalse($targets['Value']['required']);
    }

    public function testGetAvailableFieldTargetsWithSuppliers(): void
    {
        // Create test suppliers
        $supplier1 = new Supplier();
        $supplier1->setName('LCSC');
        $supplier2 = new Supplier();
        $supplier2->setName('Mouser');

        $this->entityManager->persist($supplier1);
        $this->entityManager->persist($supplier2);
        $this->entityManager->flush();

        $targets = $this->service->getAvailableFieldTargets();

        $this->assertArrayHasKey('LCSC SPN', $targets);
        $this->assertArrayHasKey('Mouser SPN', $targets);

        $this->assertEquals('LCSC SPN', $targets['LCSC SPN']['label']);
        $this->assertEquals('Mouser SPN', $targets['Mouser SPN']['label']);
        $this->assertFalse($targets['LCSC SPN']['required']);
        $this->assertTrue($targets['LCSC SPN']['multiple']);

        // Clean up
        $this->entityManager->remove($supplier1);
        $this->entityManager->remove($supplier2);
        $this->entityManager->flush();
    }

    public function testGetSuggestedFieldMapping(): void
    {
        $detected_fields = [
            'Reference',
            'Value',
            'Footprint',
            'Quantity',
            'MPN',
            'Manufacturer',
            'LCSC',
            'Mouser',
            'Part-DB ID',
            'Comment'
        ];

        $suggestions = $this->service->getSuggestedFieldMapping($detected_fields);

        $this->assertIsArray($suggestions);
        $this->assertEquals('Designator', $suggestions['Reference']);
        $this->assertEquals('Value', $suggestions['Value']);
        $this->assertEquals('Package', $suggestions['Footprint']);
        $this->assertEquals('Quantity', $suggestions['Quantity']);
        $this->assertEquals('MPN', $suggestions['MPN']);
        $this->assertEquals('Manufacturer', $suggestions['Manufacturer']);
        $this->assertEquals('Part-DB ID', $suggestions['Part-DB ID']);
        $this->assertEquals('Comment', $suggestions['Comment']);
    }

    public function testGetSuggestedFieldMappingWithSuppliers(): void
    {
        // Create test suppliers
        $supplier1 = new Supplier();
        $supplier1->setName('LCSC');
        $supplier2 = new Supplier();
        $supplier2->setName('Mouser');

        $this->entityManager->persist($supplier1);
        $this->entityManager->persist($supplier2);
        $this->entityManager->flush();

        $detected_fields = [
            'Reference',
            'LCSC',
            'Mouser',
            'lcsc_part',
            'mouser_spn'
        ];

        $suggestions = $this->service->getSuggestedFieldMapping($detected_fields);

        $this->assertIsArray($suggestions);
        $this->assertEquals('Designator', $suggestions['Reference']);
        // Note: The exact mapping depends on the pattern matching logic
        // We just check that supplier fields are mapped to something
        $this->assertArrayHasKey('LCSC', $suggestions);
        $this->assertArrayHasKey('Mouser', $suggestions);
        $this->assertArrayHasKey('lcsc_part', $suggestions);
        $this->assertArrayHasKey('mouser_spn', $suggestions);

        // Clean up
        $this->entityManager->remove($supplier1);
        $this->entityManager->remove($supplier2);
        $this->entityManager->flush();
    }

    public function testValidateFieldMappingValid(): void
    {
        $field_mapping = [
            'Reference' => 'Designator',
            'Quantity' => 'Quantity',
            'Value' => 'Value'
        ];

        $detected_fields = ['Reference', 'Quantity', 'Value', 'MPN'];

        $result = $this->service->validateFieldMapping($field_mapping, $detected_fields);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('errors', $result);
        $this->assertArrayHasKey('warnings', $result);
        $this->assertArrayHasKey('is_valid', $result);

        $this->assertTrue($result['is_valid']);
        $this->assertEmpty($result['errors']);
        $this->assertNotEmpty($result['warnings']); // Should warn about unmapped MPN
    }

    public function testValidateFieldMappingMissingRequired(): void
    {
        $field_mapping = [
            'Value' => 'Value',
            'MPN' => 'MPN'
        ];

        $detected_fields = ['Value', 'MPN'];

        $result = $this->service->validateFieldMapping($field_mapping, $detected_fields);

        $this->assertFalse($result['is_valid']);
        $this->assertNotEmpty($result['errors']);
        $this->assertContains("Required field 'Designator' is not mapped from any CSV column.", $result['errors']);
        $this->assertContains("Required field 'Quantity' is not mapped from any CSV column.", $result['errors']);
    }

    public function testValidateFieldMappingInvalidTarget(): void
    {
        $field_mapping = [
            'Reference' => 'Designator',
            'Quantity' => 'Quantity',
            'Value' => 'InvalidTarget'
        ];

        $detected_fields = ['Reference', 'Quantity', 'Value'];

        $result = $this->service->validateFieldMapping($field_mapping, $detected_fields);

        $this->assertFalse($result['is_valid']);
        $this->assertNotEmpty($result['errors']);
        $this->assertContains("Invalid target field 'InvalidTarget' for CSV field 'Value'.", $result['errors']);
    }

    public function testStringToBOMEntriesKiCADSchematic(): void
    {
        // Create test suppliers for this test
        $lcscSupplier = new Supplier();
        $lcscSupplier->setName('LCSC');
        $mouserSupplier = new Supplier();
        $mouserSupplier->setName('Mouser');

        $this->entityManager->persist($lcscSupplier);
        $this->entityManager->persist($mouserSupplier);
        $this->entityManager->flush();

        $input = <<<CSV
        "Reference","Value","Footprint","Quantity","MPN","Manufacturer","LCSC SPN","Mouser SPN"
        "R1,R2","10k","R_0805_2012Metric",2,"CRCW080510K0FKEA","Vishay","C123456","123-M10K"
        "C1","100nF","C_0805_2012Metric",1,"CL21A104KOCLRNC","Samsung","C789012","80-CL21A104KOCLRNC"
        CSV;

        $field_mapping = [
            'Reference' => 'Designator',
            'Value' => 'Value',
            'Footprint' => 'Package',
            'Quantity' => 'Quantity',
            'MPN' => 'MPN',
            'Manufacturer' => 'Manufacturer',
            'LCSC SPN' => 'LCSC SPN',
            'Mouser SPN' => 'Mouser SPN'
        ];

        $project = new Project();
        $bom_entries = $this->service->stringToBOMEntries($project, $input, [
            'type' => 'kicad_schematic',
            'field_mapping' => $field_mapping,
            'delimiter' => ','
        ]);

        $this->assertContainsOnlyInstancesOf(ProjectBOMEntry::class, $bom_entries);
        $this->assertCount(2, $bom_entries);

        // Check first entry
        $this->assertEquals('R1,R2', $bom_entries[0]->getMountnames());
        $this->assertEqualsWithDelta(2.0, $bom_entries[0]->getQuantity(), PHP_FLOAT_EPSILON);
        $this->assertEquals('CRCW080510K0FKEA (R_0805_2012Metric)', $bom_entries[0]->getName());
        $this->assertStringContainsString('Value: 10k', $bom_entries[0]->getComment());
        $this->assertStringContainsString('MPN: CRCW080510K0FKEA', $bom_entries[0]->getComment());
        $this->assertStringContainsString('Manf: Vishay', $bom_entries[0]->getComment());
        $this->assertStringContainsString('LCSC SPN: C123456', $bom_entries[0]->getComment());
        $this->assertStringContainsString('Mouser SPN: 123-M10K', $bom_entries[0]->getComment());


        // Check second entry
        $this->assertEquals('C1', $bom_entries[1]->getMountnames());
        $this->assertEqualsWithDelta(1.0, $bom_entries[1]->getQuantity(), PHP_FLOAT_EPSILON);
        $this->assertStringContainsString('LCSC SPN: C789012', $bom_entries[1]->getComment());
        $this->assertStringContainsString('Mouser SPN: 80-CL21A104KOCLRNC', $bom_entries[1]->getComment());

        // Clean up
        $this->entityManager->remove($lcscSupplier);
        $this->entityManager->remove($mouserSupplier);
        $this->entityManager->flush();
    }

    public function testStringToBOMEntriesKiCADSchematicWithPriority(): void
    {
        $input = <<<CSV
        "Reference","Value","MPN1","MPN2","Quantity"
        "R1,R2","10k","CRCW080510K0FKEA","","2"
        "C1","100nF","","CL21A104KOCLRNC","1"
        CSV;

        $field_mapping = [
            'Reference' => 'Designator',
            'Value' => 'Value',
            'MPN1' => 'MPN',
            'MPN2' => 'MPN',
            'Quantity' => 'Quantity'
        ];

        $field_priorities = [
            'MPN1' => 1,
            'MPN2' => 2
        ];

        $project = new Project();
        $bom_entries = $this->service->stringToBOMEntries($project, $input, [
            'type' => 'kicad_schematic',
            'field_mapping' => $field_mapping,
            'field_priorities' => $field_priorities,
            'delimiter' => ','
        ]);

        $this->assertContainsOnlyInstancesOf(ProjectBOMEntry::class, $bom_entries);
        $this->assertCount(2, $bom_entries);

        // First entry should use MPN1 (higher priority)
        $this->assertEquals('CRCW080510K0FKEA', $bom_entries[0]->getName());

        // Second entry should use MPN2 (MPN1 is empty)
        $this->assertEquals('CL21A104KOCLRNC', $bom_entries[1]->getName());
    }

    public function testStringToBOMEntriesKiCADSchematicWithPartDBID(): void
    {
        // Create a test part with required fields
        $part = new Part();
        $part->setName('Test Part');
        $part->setCategory($this->getDefaultCategory($this->entityManager));
        $this->entityManager->persist($part);
        $this->entityManager->flush();

        $input = <<<CSV
        "Reference","Value","Part-DB ID","Quantity"
        "R1,R2","10k","{$part->getID()}","2"
        CSV;

        $field_mapping = [
            'Reference' => 'Designator',
            'Value' => 'Value',
            'Part-DB ID' => 'Part-DB ID',
            'Quantity' => 'Quantity'
        ];

        $project = new Project();
        $bom_entries = $this->service->stringToBOMEntries($project, $input, [
            'type' => 'kicad_schematic',
            'field_mapping' => $field_mapping,
            'delimiter' => ','
        ]);

        $this->assertContainsOnlyInstancesOf(ProjectBOMEntry::class, $bom_entries);
        $this->assertCount(1, $bom_entries);

        $this->assertEquals('Test Part', $bom_entries[0]->getName());
        $this->assertSame($part, $bom_entries[0]->getPart());
        $this->assertStringContainsString("Part-DB ID: {$part->getID()}", $bom_entries[0]->getComment());

        // Clean up
        $this->entityManager->remove($part);
        $this->entityManager->flush();
    }

    public function testStringToBOMEntriesKiCADSchematicWithInvalidPartDBID(): void
    {
        $input = <<<CSV
        "Reference","Value","Part-DB ID","Quantity"
        "R1,R2","10k","99999","2"
        CSV;

        $field_mapping = [
            'Reference' => 'Designator',
            'Value' => 'Value',
            'Part-DB ID' => 'Part-DB ID',
            'Quantity' => 'Quantity'
        ];

        $project = new Project();
        $bom_entries = $this->service->stringToBOMEntries($project, $input, [
            'type' => 'kicad_schematic',
            'field_mapping' => $field_mapping,
            'delimiter' => ','
        ]);

        $this->assertContainsOnlyInstancesOf(ProjectBOMEntry::class, $bom_entries);
        $this->assertCount(1, $bom_entries);

        $this->assertEquals('10k', $bom_entries[0]->getName()); // Should use Value as name
        $this->assertNull($bom_entries[0]->getPart()); // Should not link to part
        $this->assertStringContainsString("Part-DB ID: 99999 (NOT FOUND)", $bom_entries[0]->getComment());
    }

    public function testStringToBOMEntriesKiCADSchematicMergeDuplicates(): void
    {
        $input = <<<CSV
        "Reference","Value","MPN","Quantity"
        "R1","10k","CRCW080510K0FKEA","1"
        "R2","10k","CRCW080510K0FKEA","1"
        CSV;

        $field_mapping = [
            'Reference' => 'Designator',
            'Value' => 'Value',
            'MPN' => 'MPN',
            'Quantity' => 'Quantity'
        ];

        $project = new Project();
        $bom_entries = $this->service->stringToBOMEntries($project, $input, [
            'type' => 'kicad_schematic',
            'field_mapping' => $field_mapping,
            'delimiter' => ','
        ]);

        $this->assertContainsOnlyInstancesOf(ProjectBOMEntry::class, $bom_entries);
        $this->assertCount(1, $bom_entries); // Should merge into one entry

        $this->assertEquals('R1,R2', $bom_entries[0]->getMountnames());
        $this->assertEqualsWithDelta(2.0, $bom_entries[0]->getQuantity(), PHP_FLOAT_EPSILON);
        $this->assertEquals('CRCW080510K0FKEA', $bom_entries[0]->getName());
    }

    public function testStringToBOMEntriesKiCADSchematicMissingRequired(): void
    {
        $input = <<<CSV
        "Value","MPN"
        "10k","CRCW080510K0FKEA"
        CSV;

        $field_mapping = [
            'Value' => 'Value',
            'MPN' => 'MPN'
        ];

        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage('Required field "Designator" is missing or empty');

        $project = new Project();
        $this->service->stringToBOMEntries($project, $input, [
            'type' => 'kicad_schematic',
            'field_mapping' => $field_mapping,
            'delimiter' => ','
        ]);
    }

    public function testStringToBOMEntriesKiCADSchematicQuantityMismatch(): void
    {
        $input = <<<CSV
        "Reference","Value","Quantity"
        "R1,R2,R3","10k","2"
        CSV;

        $field_mapping = [
            'Reference' => 'Designator',
            'Value' => 'Value',
            'Quantity' => 'Quantity'
        ];

        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage('Mismatch between quantity and component references');

        $project = new Project();
        $this->service->stringToBOMEntries($project, $input, [
            'type' => 'kicad_schematic',
            'field_mapping' => $field_mapping,
            'delimiter' => ','
        ]);
    }

    public function testStringToBOMEntriesKiCADSchematicWithBOM(): void
    {
        // Test with BOM (Byte Order Mark)
        $input = "\xEF\xBB\xBF" . <<<CSV
        "Reference","Value","Quantity"
        "R1,R2","10k","2"
        CSV;

        $field_mapping = [
            'Reference' => 'Designator',
            'Value' => 'Value',
            'Quantity' => 'Quantity'
        ];

        $project = new Project();
        $bom_entries = $this->service->stringToBOMEntries($project, $input, [
            'type' => 'kicad_schematic',
            'field_mapping' => $field_mapping,
            'delimiter' => ','
        ]);

        $this->assertContainsOnlyInstancesOf(ProjectBOMEntry::class, $bom_entries);
        $this->assertCount(1, $bom_entries);
        $this->assertEquals('R1,R2', $bom_entries[0]->getMountnames());
    }

    public function testStringToBOMEntriesKiCADSchematicWithSupplierSPN(): void
    {
        // Create test supplier
        $lcscSupplier = new Supplier();
        $lcscSupplier->setName('LCSC');
        $this->entityManager->persist($lcscSupplier);

        // Create a test part with required fields
        $part = new Part();
        $part->setName('Test Resistor 10k 0805');
        $part->setCategory($this->getDefaultCategory($this->entityManager));
        $this->entityManager->persist($part);

        // Create orderdetail linking the part to a supplier SPN
        $orderdetail = new Orderdetail();
        $orderdetail->setPart($part);
        $orderdetail->setSupplier($lcscSupplier);
        $orderdetail->setSupplierpartnr('C123456');
        $this->entityManager->persist($orderdetail);

        $this->entityManager->flush();

        // Import CSV with LCSC SPN matching the orderdetail
        $input = <<<CSV
        "Reference","Value","LCSC SPN","Quantity"
        "R1,R2","10k","C123456","2"
        CSV;

        $field_mapping = [
            'Reference' => 'Designator',
            'Value' => 'Value',
            'LCSC SPN' => 'LCSC SPN',
            'Quantity' => 'Quantity'
        ];

        $bom_entries = $this->service->stringToBOMEntries($input, [
            'type' => 'kicad_schematic',
            'field_mapping' => $field_mapping,
            'delimiter' => ','
        ]);

        $this->assertContainsOnlyInstancesOf(ProjectBOMEntry::class, $bom_entries);
        $this->assertCount(1, $bom_entries);

        // Verify that the BOM entry is linked to the correct part via supplier SPN
        $this->assertSame($part, $bom_entries[0]->getPart());
        $this->assertEquals('Test Resistor 10k 0805', $bom_entries[0]->getName());
        $this->assertEquals('R1,R2', $bom_entries[0]->getMountnames());
        $this->assertEqualsWithDelta(2.0, $bom_entries[0]->getQuantity(), PHP_FLOAT_EPSILON);
        $this->assertStringContainsString('LCSC SPN: C123456', $bom_entries[0]->getComment());
        $this->assertStringContainsString('Part-DB ID: ' . $part->getID(), $bom_entries[0]->getComment());

        // Clean up
        $this->entityManager->remove($orderdetail);
        $this->entityManager->remove($part);
        $this->entityManager->remove($lcscSupplier);
        $this->entityManager->flush();
    }

    public function testStringToBOMEntriesKiCADSchematicWithMultipleSupplierSPNs(): void
    {
        // Create test suppliers
        $lcscSupplier = new Supplier();
        $lcscSupplier->setName('LCSC');
        $mouserSupplier = new Supplier();
        $mouserSupplier->setName('Mouser');
        $this->entityManager->persist($lcscSupplier);
        $this->entityManager->persist($mouserSupplier);

        // Create first part linked via LCSC SPN
        $part1 = new Part();
        $part1->setName('Resistor 10k');
        $part1->setCategory($this->getDefaultCategory($this->entityManager));
        $this->entityManager->persist($part1);

        $orderdetail1 = new Orderdetail();
        $orderdetail1->setPart($part1);
        $orderdetail1->setSupplier($lcscSupplier);
        $orderdetail1->setSupplierpartnr('C123456');
        $this->entityManager->persist($orderdetail1);

        // Create second part linked via Mouser SPN
        $part2 = new Part();
        $part2->setName('Capacitor 100nF');
        $part2->setCategory($this->getDefaultCategory($this->entityManager));
        $this->entityManager->persist($part2);

        $orderdetail2 = new Orderdetail();
        $orderdetail2->setPart($part2);
        $orderdetail2->setSupplier($mouserSupplier);
        $orderdetail2->setSupplierpartnr('789-CAP100NF');
        $this->entityManager->persist($orderdetail2);

        $this->entityManager->flush();

        // Import CSV with both LCSC and Mouser SPNs
        $input = <<<CSV
        "Reference","Value","LCSC SPN","Mouser SPN","Quantity"
        "R1","10k","C123456","","1"
        "C1","100nF","","789-CAP100NF","1"
        CSV;

        $field_mapping = [
            'Reference' => 'Designator',
            'Value' => 'Value',
            'LCSC SPN' => 'LCSC SPN',
            'Mouser SPN' => 'Mouser SPN',
            'Quantity' => 'Quantity'
        ];

        $bom_entries = $this->service->stringToBOMEntries($input, [
            'type' => 'kicad_schematic',
            'field_mapping' => $field_mapping,
            'delimiter' => ','
        ]);

        $this->assertCount(2, $bom_entries);

        // Verify first entry linked via LCSC SPN
        $this->assertSame($part1, $bom_entries[0]->getPart());
        $this->assertEquals('Resistor 10k', $bom_entries[0]->getName());

        // Verify second entry linked via Mouser SPN
        $this->assertSame($part2, $bom_entries[1]->getPart());
        $this->assertEquals('Capacitor 100nF', $bom_entries[1]->getName());

        // Clean up
        $this->entityManager->remove($orderdetail1);
        $this->entityManager->remove($orderdetail2);
        $this->entityManager->remove($part1);
        $this->entityManager->remove($part2);
        $this->entityManager->remove($lcscSupplier);
        $this->entityManager->remove($mouserSupplier);
        $this->entityManager->flush();
    }

    public function testStringToBOMEntriesKiCADSchematicWithNonMatchingSPN(): void
    {
        // Create test supplier
        $lcscSupplier = new Supplier();
        $lcscSupplier->setName('LCSC');
        $this->entityManager->persist($lcscSupplier);
        $this->entityManager->flush();

        // Import CSV with LCSC SPN that doesn't match any orderdetail
        $input = <<<CSV
        "Reference","Value","LCSC SPN","Quantity"
        "R1","10k","C999999","1"
        CSV;

        $field_mapping = [
            'Reference' => 'Designator',
            'Value' => 'Value',
            'LCSC SPN' => 'LCSC SPN',
            'Quantity' => 'Quantity'
        ];

        $bom_entries = $this->service->stringToBOMEntries($input, [
            'type' => 'kicad_schematic',
            'field_mapping' => $field_mapping,
            'delimiter' => ','
        ]);

        $this->assertCount(1, $bom_entries);

        // Verify that no part is linked (SPN not found)
        $this->assertNull($bom_entries[0]->getPart());
        $this->assertEquals('10k', $bom_entries[0]->getName()); // Should use Value as name
        $this->assertStringContainsString('LCSC SPN: C999999', $bom_entries[0]->getComment());

        // Clean up
        $this->entityManager->remove($lcscSupplier);
        $this->entityManager->flush();
    }

    private function getDefaultCategory(EntityManagerInterface $entityManager)
    {
        // Get the first available category or create a default one
        $categoryRepo = $entityManager->getRepository(Category::class);
        $categories = $categoryRepo->findAll();

        if (empty($categories)) {
            // Create a default category if none exists
            $category = new Category();
            $category->setName('Default Category');
            $entityManager->persist($category);
            $entityManager->flush();
            return $category;
        }

        return $categories[0];
    }
}
