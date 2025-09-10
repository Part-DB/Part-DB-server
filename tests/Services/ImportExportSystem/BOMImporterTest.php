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

use App\Entity\Parts\Part;
use App\Entity\Parts\Supplier;
use App\Entity\ProjectSystem\Project;
use App\Entity\ProjectSystem\ProjectBOMEntry;
use App\Services\ImportExportSystem\BOMImporter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\File;

class BOMImporterTest extends WebTestCase
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

        $bom = $this->service->stringToBOMEntries($input, ['type' => 'kicad_pcbnew']);

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

        $this->service->stringToBOMEntries($input, ['type' => 'kicad_pcbnew']);
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

        $bom_entries = $this->service->stringToBOMEntries($input, [
            'type' => 'kicad_schematic',
            'field_mapping' => $field_mapping,
            'delimiter' => ','
        ]);

        $this->assertContainsOnlyInstancesOf(ProjectBOMEntry::class, $bom_entries);
        $this->assertCount(2, $bom_entries);

        // Check first entry
        $this->assertEquals('R1,R2', $bom_entries[0]->getMountnames());
        $this->assertEquals(2.0, $bom_entries[0]->getQuantity());
        $this->assertEquals('CRCW080510K0FKEA (R_0805_2012Metric)', $bom_entries[0]->getName());
        $this->assertStringContainsString('Value: 10k', $bom_entries[0]->getComment());
        $this->assertStringContainsString('MPN: CRCW080510K0FKEA', $bom_entries[0]->getComment());
        $this->assertStringContainsString('Manf: Vishay', $bom_entries[0]->getComment());

        // Check second entry
        $this->assertEquals('C1', $bom_entries[1]->getMountnames());
        $this->assertEquals(1.0, $bom_entries[1]->getQuantity());
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

        $bom_entries = $this->service->stringToBOMEntries($input, [
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

        $bom_entries = $this->service->stringToBOMEntries($input, [
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

        $bom_entries = $this->service->stringToBOMEntries($input, [
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

        $bom_entries = $this->service->stringToBOMEntries($input, [
            'type' => 'kicad_schematic',
            'field_mapping' => $field_mapping,
            'delimiter' => ','
        ]);

        $this->assertContainsOnlyInstancesOf(ProjectBOMEntry::class, $bom_entries);
        $this->assertCount(1, $bom_entries); // Should merge into one entry

        $this->assertEquals('R1,R2', $bom_entries[0]->getMountnames());
        $this->assertEquals(2.0, $bom_entries[0]->getQuantity());
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

        $this->service->stringToBOMEntries($input, [
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

        $this->service->stringToBOMEntries($input, [
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

        $bom_entries = $this->service->stringToBOMEntries($input, [
            'type' => 'kicad_schematic',
            'field_mapping' => $field_mapping,
            'delimiter' => ','
        ]);

        $this->assertContainsOnlyInstancesOf(ProjectBOMEntry::class, $bom_entries);
        $this->assertCount(1, $bom_entries);
        $this->assertEquals('R1,R2', $bom_entries[0]->getMountnames());
    }

    private function getDefaultCategory(EntityManagerInterface $entityManager)
    {
        // Get the first available category or create a default one
        $categoryRepo = $entityManager->getRepository(\App\Entity\Parts\Category::class);
        $categories = $categoryRepo->findAll();

        if (empty($categories)) {
            // Create a default category if none exists
            $category = new \App\Entity\Parts\Category();
            $category->setName('Default Category');
            $entityManager->persist($category);
            $entityManager->flush();
            return $category;
        }

        return $categories[0];
    }
}
