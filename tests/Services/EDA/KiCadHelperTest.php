<?php
/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2024 Jan Böhmer (https://github.com/jbtronics)
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

namespace App\Tests\Services\EDA;

use App\Entity\Attachments\AttachmentType;
use App\Entity\Attachments\PartAttachment;
use App\Entity\Parameters\PartParameter;
use App\Entity\Parts\Category;
use App\Entity\Parts\Part;
use App\Entity\Parts\PartLot;
use App\Entity\Parts\StorageLocation;
use App\Services\EDA\KiCadHelper;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group('DB')]
final class KiCadHelperTest extends KernelTestCase
{
    private KiCadHelper $helper;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->helper = self::getContainer()->get(KiCadHelper::class);
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
    }

    /**
     * Part 1 (from fixtures) has no stock lots. Stock should be 0.
     */
    public function testPartWithoutStockHasZeroStock(): void
    {
        $part = $this->em->find(Part::class, 1);
        $result = $this->helper->getKiCADPart($part);

        self::assertArrayHasKey('Stock', $result['fields']);
        self::assertSame('0', $result['fields']['Stock']['value']);
    }

    /**
     * Part 3 (from fixtures) has a lot with amount=1.0 in StorageLocation 1.
     */
    public function testPartWithStockShowsCorrectQuantity(): void
    {
        $part = $this->em->find(Part::class, 3);
        $result = $this->helper->getKiCADPart($part);

        self::assertArrayHasKey('Stock', $result['fields']);
        self::assertSame('1', $result['fields']['Stock']['value']);
    }

    /**
     * Part 3 has a lot with amount > 0 in StorageLocation "Node 1".
     */
    public function testPartWithStorageLocationShowsLocation(): void
    {
        $part = $this->em->find(Part::class, 3);
        $result = $this->helper->getKiCADPart($part);

        self::assertArrayHasKey('Storage Location', $result['fields']);
        self::assertSame('Node 1', $result['fields']['Storage Location']['value']);
    }

    /**
     * Part 1 has no stock lots, so no storage location should be shown.
     */
    public function testPartWithoutStorageLocationOmitsField(): void
    {
        $part = $this->em->find(Part::class, 1);
        $result = $this->helper->getKiCADPart($part);

        self::assertArrayNotHasKey('Storage Location', $result['fields']);
    }

    /**
     * All parts should have a "Part-DB URL" field pointing to the part info page.
     */
    public function testPartDbUrlFieldIsPresent(): void
    {
        $part = $this->em->find(Part::class, 1);
        $result = $this->helper->getKiCADPart($part);

        self::assertArrayHasKey('Part-DB URL', $result['fields']);
        self::assertStringContainsString('/part/1/info', $result['fields']['Part-DB URL']['value']);
    }

    /**
     * Part 1 has no attachments, so the datasheet should fall back to the Part-DB page URL.
     */
    public function testDatasheetFallbackToPartUrlWhenNoAttachments(): void
    {
        $part = $this->em->find(Part::class, 1);
        $result = $this->helper->getKiCADPart($part);

        // With no attachments, datasheet should equal Part-DB URL
        self::assertSame(
            $result['fields']['Part-DB URL']['value'],
            $result['fields']['datasheet']['value']
        );
    }

    /**
     * Part 3 has attachments but none named "datasheet" and none are PDFs,
     * so the datasheet should fall back to the Part-DB page URL.
     */
    public function testDatasheetFallbackWhenNoMatchingAttachments(): void
    {
        $part = $this->em->find(Part::class, 3);
        $result = $this->helper->getKiCADPart($part);

        // "TestAttachment" (url: www.foo.bar) and "Test2" (internal: invalid) don't match datasheet patterns
        self::assertSame(
            $result['fields']['Part-DB URL']['value'],
            $result['fields']['datasheet']['value']
        );
    }

    /**
     * Test that an attachment with type name containing "Datasheet" is found.
     */
    public function testDatasheetFoundByAttachmentTypeName(): void
    {
        $category = $this->em->find(Category::class, 1);

        // Create an attachment type named "Datasheets"
        $datasheetType = new AttachmentType();
        $datasheetType->setName('Datasheets');
        $this->em->persist($datasheetType);

        // Create a part with a datasheet attachment
        $part = new Part();
        $part->setName('Part with Datasheet Type');
        $part->setCategory($category);

        $attachment = new PartAttachment();
        $attachment->setName('Component Spec');
        $attachment->setURL('https://example.com/spec.pdf');
        $attachment->setAttachmentType($datasheetType);
        $part->addAttachment($attachment);

        $this->em->persist($part);
        $this->em->flush();

        $result = $this->helper->getKiCADPart($part);

        self::assertSame('https://example.com/spec.pdf', $result['fields']['datasheet']['value']);
    }

    /**
     * Test that an attachment named "Datasheet" is found (regardless of type).
     */
    public function testDatasheetFoundByAttachmentName(): void
    {
        $category = $this->em->find(Category::class, 1);
        $attachmentType = $this->em->find(AttachmentType::class, 1);

        $part = new Part();
        $part->setName('Part with Named Datasheet');
        $part->setCategory($category);

        $attachment = new PartAttachment();
        $attachment->setName('Datasheet BC547');
        $attachment->setURL('https://example.com/bc547-datasheet.pdf');
        $attachment->setAttachmentType($attachmentType);
        $part->addAttachment($attachment);

        $this->em->persist($part);
        $this->em->flush();

        $result = $this->helper->getKiCADPart($part);

        self::assertSame('https://example.com/bc547-datasheet.pdf', $result['fields']['datasheet']['value']);
    }

    /**
     * Test that a PDF attachment is used as fallback when no "datasheet" match exists.
     */
    public function testDatasheetFallbackToFirstPdfAttachment(): void
    {
        $category = $this->em->find(Category::class, 1);
        $attachmentType = $this->em->find(AttachmentType::class, 1);

        $part = new Part();
        $part->setName('Part with PDF');
        $part->setCategory($category);

        // Non-PDF attachment first
        $attachment1 = new PartAttachment();
        $attachment1->setName('Photo');
        $attachment1->setURL('https://example.com/photo.jpg');
        $attachment1->setAttachmentType($attachmentType);
        $part->addAttachment($attachment1);

        // PDF attachment second
        $attachment2 = new PartAttachment();
        $attachment2->setName('Specifications');
        $attachment2->setURL('https://example.com/specs.pdf');
        $attachment2->setAttachmentType($attachmentType);
        $part->addAttachment($attachment2);

        $this->em->persist($part);
        $this->em->flush();

        $result = $this->helper->getKiCADPart($part);

        // Should find the .pdf file as fallback
        self::assertSame('https://example.com/specs.pdf', $result['fields']['datasheet']['value']);
    }

    /**
     * Test that a "data sheet" variant (with space) is also matched by name.
     */
    public function testDatasheetMatchesDataSheetWithSpace(): void
    {
        $category = $this->em->find(Category::class, 1);
        $attachmentType = $this->em->find(AttachmentType::class, 1);

        $part = new Part();
        $part->setName('Part with Data Sheet');
        $part->setCategory($category);

        $attachment = new PartAttachment();
        $attachment->setName('Data Sheet v1.2');
        $attachment->setURL('https://example.com/data-sheet.pdf');
        $attachment->setAttachmentType($attachmentType);
        $part->addAttachment($attachment);

        $this->em->persist($part);
        $this->em->flush();

        $result = $this->helper->getKiCADPart($part);

        self::assertSame('https://example.com/data-sheet.pdf', $result['fields']['datasheet']['value']);
    }

    /**
     * Test stock calculation excludes expired lots.
     */
    public function testStockExcludesExpiredLots(): void
    {
        $category = $this->em->find(Category::class, 1);

        $part = new Part();
        $part->setName('Part with Expired Stock');
        $part->setCategory($category);

        // Active lot
        $lot1 = new PartLot();
        $lot1->setAmount(10.0);
        $part->addPartLot($lot1);

        // Expired lot
        $lot2 = new PartLot();
        $lot2->setAmount(5.0);
        $lot2->setExpirationDate(new \DateTimeImmutable('-1 day'));
        $part->addPartLot($lot2);

        $this->em->persist($part);
        $this->em->flush();

        $result = $this->helper->getKiCADPart($part);

        // Only the active lot should be counted
        self::assertSame('10', $result['fields']['Stock']['value']);
    }

    /**
     * Test stock calculation excludes lots with unknown stock.
     */
    public function testStockExcludesUnknownLots(): void
    {
        $category = $this->em->find(Category::class, 1);

        $part = new Part();
        $part->setName('Part with Unknown Stock');
        $part->setCategory($category);

        // Known lot
        $lot1 = new PartLot();
        $lot1->setAmount(7.0);
        $part->addPartLot($lot1);

        // Unknown lot
        $lot2 = new PartLot();
        $lot2->setInstockUnknown(true);
        $part->addPartLot($lot2);

        $this->em->persist($part);
        $this->em->flush();

        $result = $this->helper->getKiCADPart($part);

        self::assertSame('7', $result['fields']['Stock']['value']);
    }

    /**
     * Test stock sums across multiple lots.
     */
    public function testStockSumsMultipleLots(): void
    {
        $category = $this->em->find(Category::class, 1);
        $location1 = $this->em->find(StorageLocation::class, 1);
        $location2 = $this->em->find(StorageLocation::class, 2);

        $part = new Part();
        $part->setName('Part in Multiple Locations');
        $part->setCategory($category);

        $lot1 = new PartLot();
        $lot1->setAmount(15.0);
        $lot1->setStorageLocation($location1);
        $part->addPartLot($lot1);

        $lot2 = new PartLot();
        $lot2->setAmount(25.0);
        $lot2->setStorageLocation($location2);
        $part->addPartLot($lot2);

        $this->em->persist($part);
        $this->em->flush();

        $result = $this->helper->getKiCADPart($part);

        self::assertSame('40', $result['fields']['Stock']['value']);
        self::assertArrayHasKey('Storage Location', $result['fields']);
        // Both locations should be listed
        self::assertStringContainsString('Node 1', $result['fields']['Storage Location']['value']);
        self::assertStringContainsString('Node 2', $result['fields']['Storage Location']['value']);
    }

    /**
     * Test that the Stock field visibility is "False" (not visible in schematic by default).
     */
    public function testStockFieldIsNotVisible(): void
    {
        $part = $this->em->find(Part::class, 1);
        $result = $this->helper->getKiCADPart($part);

        self::assertSame('False', $result['fields']['Stock']['visible']);
    }

    /**
     * Test that a parameter with kicad_export=true appears in the KiCad fields.
     */
    public function testParameterWithKicadExportAppearsInFields(): void
    {
        $category = $this->em->find(Category::class, 1);

        $part = new Part();
        $part->setName('Part with Exported Parameter');
        $part->setCategory($category);

        $param = new PartParameter();
        $param->setName('Voltage Rating');
        $param->setValueTypical(3.3);
        $param->setUnit('V');
        $param->setKicadExport(true);
        $part->addParameter($param);

        $this->em->persist($part);
        $this->em->flush();

        $result = $this->helper->getKiCADPart($part);

        self::assertArrayHasKey('Voltage Rating', $result['fields']);
        self::assertSame('3.3 V', $result['fields']['Voltage Rating']['value']);
    }

    /**
     * Test that a parameter with kicad_export=false does NOT appear in the KiCad fields.
     */
    public function testParameterWithoutKicadExportDoesNotAppear(): void
    {
        $category = $this->em->find(Category::class, 1);

        $part = new Part();
        $part->setName('Part with Non-exported Parameter');
        $part->setCategory($category);

        $param = new PartParameter();
        $param->setName('Internal Note');
        $param->setValueText('for testing only');
        $param->setKicadExport(false);
        $part->addParameter($param);

        $this->em->persist($part);
        $this->em->flush();

        $result = $this->helper->getKiCADPart($part);

        self::assertArrayNotHasKey('Internal Note', $result['fields']);
    }

    /**
     * Test that an exported parameter named "description" does NOT overwrite the hardcoded description field.
     */
    public function testExportedParameterDoesNotOverwriteHardcodedField(): void
    {
        $category = $this->em->find(Category::class, 1);

        $part = new Part();
        $part->setName('Part with Conflicting Parameter');
        $part->setDescription('The real description');
        $part->setCategory($category);

        $param = new PartParameter();
        $param->setName('description');
        $param->setValueText('should not overwrite');
        $param->setKicadExport(true);
        $part->addParameter($param);

        $this->em->persist($part);
        $this->em->flush();

        $result = $this->helper->getKiCADPart($part);

        // The hardcoded description should win
        self::assertSame('The real description', $result['fields']['description']['value']);
    }
}
