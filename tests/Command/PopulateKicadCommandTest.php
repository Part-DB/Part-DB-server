<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Command\PopulateKicadCommand;
use App\Entity\Parts\Category;
use App\Entity\Parts\Footprint;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class PopulateKicadCommandTest extends KernelTestCase
{
    private CommandTester $commandTester;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::bootKernel();
        $application = new Application(self::$kernel);

        $command = $application->find('partdb:kicad:populate');
        $this->commandTester = new CommandTester($command);
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
    }

    public function testListOption(): void
    {
        $this->commandTester->execute(['--list' => true]);

        $output = $this->commandTester->getDisplay();

        // Should show footprints and categories tables
        $this->assertStringContainsString('Current Footprint KiCad Values', $output);
        $this->assertStringContainsString('Current Category KiCad Values', $output);
        $this->assertStringContainsString('ID', $output);
        $this->assertStringContainsString('Name', $output);

        $this->assertEquals(0, $this->commandTester->getStatusCode());
    }

    public function testDryRunDoesNotModifyDatabase(): void
    {
        // Create a test footprint without KiCad value
        $footprint = new Footprint();
        $footprint->setName('SOT-23');
        $this->entityManager->persist($footprint);
        $this->entityManager->flush();

        $footprintId = $footprint->getId();

        // Run in dry-run mode
        $this->commandTester->execute(['--dry-run' => true, '--footprints' => true]);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('DRY RUN MODE', $output);
        $this->assertStringContainsString('SOT-23', $output);

        // Clear entity manager to force reload from DB
        $this->entityManager->clear();

        // Verify footprint was NOT updated in the database
        $reloadedFootprint = $this->entityManager->find(Footprint::class, $footprintId);
        $this->assertNull($reloadedFootprint->getEdaInfo()->getKicadFootprint());

        // Cleanup
        $this->entityManager->remove($reloadedFootprint);
        $this->entityManager->flush();
    }

    public function testFootprintMappingUpdatesCorrectly(): void
    {
        // Create test footprints
        $footprint1 = new Footprint();
        $footprint1->setName('SOT-23');

        $footprint2 = new Footprint();
        $footprint2->setName('0805');

        $footprint3 = new Footprint();
        $footprint3->setName('DIP-8');

        $this->entityManager->persist($footprint1);
        $this->entityManager->persist($footprint2);
        $this->entityManager->persist($footprint3);
        $this->entityManager->flush();

        $ids = [$footprint1->getId(), $footprint2->getId(), $footprint3->getId()];

        // Run the command
        $this->commandTester->execute(['--footprints' => true]);

        $output = $this->commandTester->getDisplay();
        $this->assertEquals(0, $this->commandTester->getStatusCode());

        // Clear and reload
        $this->entityManager->clear();

        // Verify mappings were applied
        $reloaded1 = $this->entityManager->find(Footprint::class, $ids[0]);
        $this->assertEquals('Package_TO_SOT_SMD:SOT-23', $reloaded1->getEdaInfo()->getKicadFootprint());

        $reloaded2 = $this->entityManager->find(Footprint::class, $ids[1]);
        $this->assertEquals('Resistor_SMD:R_0805_2012Metric', $reloaded2->getEdaInfo()->getKicadFootprint());

        $reloaded3 = $this->entityManager->find(Footprint::class, $ids[2]);
        $this->assertEquals('Package_DIP:DIP-8_W7.62mm', $reloaded3->getEdaInfo()->getKicadFootprint());

        // Cleanup
        $this->entityManager->remove($reloaded1);
        $this->entityManager->remove($reloaded2);
        $this->entityManager->remove($reloaded3);
        $this->entityManager->flush();
    }

    public function testSkipsExistingValuesWithoutForce(): void
    {
        // Create footprint with existing value
        $footprint = new Footprint();
        $footprint->setName('SOT-23');
        $footprint->getEdaInfo()->setKicadFootprint('Custom:MyFootprint');
        $this->entityManager->persist($footprint);
        $this->entityManager->flush();

        $footprintId = $footprint->getId();

        // Run without --force
        $this->commandTester->execute(['--footprints' => true]);

        $this->entityManager->clear();

        // Should keep original value
        $reloaded = $this->entityManager->find(Footprint::class, $footprintId);
        $this->assertEquals('Custom:MyFootprint', $reloaded->getEdaInfo()->getKicadFootprint());

        // Cleanup
        $this->entityManager->remove($reloaded);
        $this->entityManager->flush();
    }

    public function testForceOptionOverwritesExistingValues(): void
    {
        // Create footprint with existing value
        $footprint = new Footprint();
        $footprint->setName('SOT-23');
        $footprint->getEdaInfo()->setKicadFootprint('Custom:MyFootprint');
        $this->entityManager->persist($footprint);
        $this->entityManager->flush();

        $footprintId = $footprint->getId();

        // Run with --force
        $this->commandTester->execute(['--footprints' => true, '--force' => true]);

        $this->entityManager->clear();

        // Should overwrite with mapped value
        $reloaded = $this->entityManager->find(Footprint::class, $footprintId);
        $this->assertEquals('Package_TO_SOT_SMD:SOT-23', $reloaded->getEdaInfo()->getKicadFootprint());

        // Cleanup
        $this->entityManager->remove($reloaded);
        $this->entityManager->flush();
    }

    public function testCategoryMappingUpdatesCorrectly(): void
    {
        // Create test categories
        $category1 = new Category();
        $category1->setName('Resistors');

        $category2 = new Category();
        $category2->setName('LED Indicators');

        $category3 = new Category();
        $category3->setName('Zener Diodes');

        $this->entityManager->persist($category1);
        $this->entityManager->persist($category2);
        $this->entityManager->persist($category3);
        $this->entityManager->flush();

        $ids = [$category1->getId(), $category2->getId(), $category3->getId()];

        // Run the command
        $this->commandTester->execute(['--categories' => true]);

        $this->assertEquals(0, $this->commandTester->getStatusCode());

        // Clear and reload
        $this->entityManager->clear();

        // Verify mappings were applied (using pattern matching)
        $reloaded1 = $this->entityManager->find(Category::class, $ids[0]);
        $this->assertEquals('Device:R', $reloaded1->getEdaInfo()->getKicadSymbol());

        $reloaded2 = $this->entityManager->find(Category::class, $ids[1]);
        $this->assertEquals('Device:LED', $reloaded2->getEdaInfo()->getKicadSymbol());

        $reloaded3 = $this->entityManager->find(Category::class, $ids[2]);
        $this->assertEquals('Device:D_Zener', $reloaded3->getEdaInfo()->getKicadSymbol());

        // Cleanup
        $this->entityManager->remove($reloaded1);
        $this->entityManager->remove($reloaded2);
        $this->entityManager->remove($reloaded3);
        $this->entityManager->flush();
    }

    public function testUnmappedFootprintsAreListed(): void
    {
        // Create footprint with no mapping
        $footprint = new Footprint();
        $footprint->setName('CustomPackage-XYZ');
        $this->entityManager->persist($footprint);
        $this->entityManager->flush();

        $footprintId = $footprint->getId();

        // Run the command
        $this->commandTester->execute(['--footprints' => true]);

        $output = $this->commandTester->getDisplay();

        // Should list the unmapped footprint
        $this->assertStringContainsString('No mapping found', $output);
        $this->assertStringContainsString('CustomPackage-XYZ', $output);

        // Cleanup
        $this->entityManager->clear();
        $reloaded = $this->entityManager->find(Footprint::class, $footprintId);
        $this->entityManager->remove($reloaded);
        $this->entityManager->flush();
    }

    public function testMappingFileOverridesDefaults(): void
    {
        // Create a footprint that has a built-in mapping (SOT-23 -> Package_TO_SOT_SMD:SOT-23)
        $footprint = new Footprint();
        $footprint->setName('SOT-23');
        $this->entityManager->persist($footprint);
        $this->entityManager->flush();

        $footprintId = $footprint->getId();

        // Create a temporary JSON mapping file that overrides SOT-23
        $mappingFile = sys_get_temp_dir() . '/partdb_test_mappings_' . uniqid() . '.json';
        file_put_contents($mappingFile, json_encode([
            'footprints' => [
                'SOT-23' => 'Custom_Library:Custom_SOT-23',
            ],
        ]));

        try {
            // Run with mapping file
            $this->commandTester->execute(['--footprints' => true, '--mapping-file' => $mappingFile]);

            $output = $this->commandTester->getDisplay();
            $this->assertEquals(0, $this->commandTester->getStatusCode());
            $this->assertStringContainsString('custom footprint mappings', $output);

            $this->entityManager->clear();

            // Should use the custom mapping, not the built-in one
            $reloaded = $this->entityManager->find(Footprint::class, $footprintId);
            $this->assertEquals('Custom_Library:Custom_SOT-23', $reloaded->getEdaInfo()->getKicadFootprint());

            // Cleanup
            $this->entityManager->remove($reloaded);
            $this->entityManager->flush();
        } finally {
            @unlink($mappingFile);
        }
    }

    public function testMappingFileInvalidJsonReturnsFailure(): void
    {
        $mappingFile = sys_get_temp_dir() . '/partdb_test_invalid_' . uniqid() . '.json';
        file_put_contents($mappingFile, 'not valid json{{{');

        try {
            $this->commandTester->execute(['--mapping-file' => $mappingFile]);

            $output = $this->commandTester->getDisplay();
            $this->assertEquals(1, $this->commandTester->getStatusCode());
            $this->assertStringContainsString('Invalid JSON', $output);
        } finally {
            @unlink($mappingFile);
        }
    }

    public function testMappingFileNotFoundReturnsFailure(): void
    {
        $this->commandTester->execute(['--mapping-file' => '/nonexistent/path/mappings.json']);

        $this->assertEquals(1, $this->commandTester->getStatusCode());
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Mapping file not found', $output);
    }

    public function testFootprintAlternativeNameMatching(): void
    {
        // Create a footprint with a primary name that has no mapping,
        // but an alternative name that does
        $footprint = new Footprint();
        $footprint->setName('MyCustomSOT23');
        $footprint->setAlternativeNames('SOT-23, SOT23-3L');
        $this->entityManager->persist($footprint);
        $this->entityManager->flush();

        $footprintId = $footprint->getId();

        $this->commandTester->execute(['--footprints' => true]);

        $this->entityManager->clear();

        // Should match via alternative name "SOT-23"
        $reloaded = $this->entityManager->find(Footprint::class, $footprintId);
        $this->assertEquals('Package_TO_SOT_SMD:SOT-23', $reloaded->getEdaInfo()->getKicadFootprint());

        // Cleanup
        $this->entityManager->remove($reloaded);
        $this->entityManager->flush();
    }

    public function testCategoryAlternativeNameMatching(): void
    {
        // Create a category with a primary name that has no mapping,
        // but an alternative name that matches a pattern
        $category = new Category();
        $category->setName('SMD Components');
        $category->setAlternativeNames('Resistor SMD, Chip Resistors');
        $this->entityManager->persist($category);
        $this->entityManager->flush();

        $categoryId = $category->getId();

        $this->commandTester->execute(['--categories' => true]);

        $this->entityManager->clear();

        // Should match via alternative name "Resistor SMD" matching pattern "Resistor"
        $reloaded = $this->entityManager->find(Category::class, $categoryId);
        $this->assertEquals('Device:R', $reloaded->getEdaInfo()->getKicadSymbol());

        // Cleanup
        $this->entityManager->remove($reloaded);
        $this->entityManager->flush();
    }

    public function testBothFootprintsAndCategoriesUpdatedByDefault(): void
    {
        // Create one of each
        $footprint = new Footprint();
        $footprint->setName('TO-220');
        $this->entityManager->persist($footprint);

        $category = new Category();
        $category->setName('Capacitors');
        $this->entityManager->persist($category);

        $this->entityManager->flush();

        $footprintId = $footprint->getId();
        $categoryId = $category->getId();

        // Run without specific options (should do both)
        $this->commandTester->execute([]);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Updating Footprint Entities', $output);
        $this->assertStringContainsString('Updating Category Entities', $output);

        $this->entityManager->clear();

        // Both should be updated
        $reloadedFootprint = $this->entityManager->find(Footprint::class, $footprintId);
        $this->assertEquals('Package_TO_SOT_THT:TO-220-3_Vertical', $reloadedFootprint->getEdaInfo()->getKicadFootprint());

        $reloadedCategory = $this->entityManager->find(Category::class, $categoryId);
        $this->assertEquals('Device:C', $reloadedCategory->getEdaInfo()->getKicadSymbol());

        // Cleanup
        $this->entityManager->remove($reloadedFootprint);
        $this->entityManager->remove($reloadedCategory);
        $this->entityManager->flush();
    }

    public function testMappingFileWithBothFootprintsAndCategories(): void
    {
        $footprint = new Footprint();
        $footprint->setName('CustomPkg');
        $this->entityManager->persist($footprint);

        $category = new Category();
        $category->setName('CustomType');
        $this->entityManager->persist($category);

        $this->entityManager->flush();

        $footprintId = $footprint->getId();
        $categoryId = $category->getId();

        $mappingFile = sys_get_temp_dir() . '/partdb_test_both_' . uniqid() . '.json';
        file_put_contents($mappingFile, json_encode([
            'footprints' => [
                'CustomPkg' => 'Custom:Footprint',
            ],
            'categories' => [
                'CustomType' => 'Custom:Symbol',
            ],
        ]));

        try {
            $this->commandTester->execute(['--mapping-file' => $mappingFile]);

            $output = $this->commandTester->getDisplay();
            $this->assertEquals(0, $this->commandTester->getStatusCode());
            $this->assertStringContainsString('custom footprint mappings', $output);
            $this->assertStringContainsString('custom category mappings', $output);

            $this->entityManager->clear();

            $reloadedFp = $this->entityManager->find(Footprint::class, $footprintId);
            $this->assertEquals('Custom:Footprint', $reloadedFp->getEdaInfo()->getKicadFootprint());

            $reloadedCat = $this->entityManager->find(Category::class, $categoryId);
            $this->assertEquals('Custom:Symbol', $reloadedCat->getEdaInfo()->getKicadSymbol());

            // Cleanup
            $this->entityManager->remove($reloadedFp);
            $this->entityManager->remove($reloadedCat);
            $this->entityManager->flush();
        } finally {
            @unlink($mappingFile);
        }
    }

    public function testMappingFileWithOnlyCategoriesSection(): void
    {
        $category = new Category();
        $category->setName('OnlyCatType');
        $this->entityManager->persist($category);
        $this->entityManager->flush();

        $categoryId = $category->getId();

        $mappingFile = sys_get_temp_dir() . '/partdb_test_catonly_' . uniqid() . '.json';
        file_put_contents($mappingFile, json_encode([
            'categories' => [
                'OnlyCatType' => 'Custom:CatSymbol',
            ],
        ]));

        try {
            $this->commandTester->execute(['--categories' => true, '--mapping-file' => $mappingFile]);

            $output = $this->commandTester->getDisplay();
            $this->assertEquals(0, $this->commandTester->getStatusCode());
            $this->assertStringContainsString('custom category mappings', $output);
            // Should NOT mention footprint mappings since they weren't in the file
            $this->assertStringNotContainsString('custom footprint mappings', $output);

            $this->entityManager->clear();

            $reloaded = $this->entityManager->find(Category::class, $categoryId);
            $this->assertEquals('Custom:CatSymbol', $reloaded->getEdaInfo()->getKicadSymbol());

            $this->entityManager->remove($reloaded);
            $this->entityManager->flush();
        } finally {
            @unlink($mappingFile);
        }
    }
}
