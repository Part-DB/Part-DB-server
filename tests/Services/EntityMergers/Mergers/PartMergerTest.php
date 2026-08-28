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
namespace App\Tests\Services\EntityMergers\Mergers;

use App\Entity\Attachments\AttachmentType;
use App\Entity\Attachments\PartAttachment;
use App\Entity\Parts\AssociationType;
use App\Entity\Parts\Category;
use App\Entity\Parts\Footprint;
use App\Entity\Parts\Manufacturer;
use App\Entity\Parts\MeasurementUnit;
use App\Entity\Parts\Part;
use App\Entity\Parts\PartAssociation;
use App\Entity\Parts\PartCustomState;
use App\Entity\Parts\PartLot;
use App\Entity\PriceInformations\Orderdetail;
use App\Entity\ProjectSystem\Project;
use App\Entity\ProjectSystem\ProjectBOMEntry;
use App\Services\EntityMergers\Mergers\PartMerger;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class PartMergerTest extends KernelTestCase
{

    /** @var PartMerger|null  */
    protected ?PartMerger $merger = null;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->merger = self::getContainer()->get(PartMerger::class);
    }

    public function testMergeOfEntityRelations(): void
    {
        $category = new Category();
        $footprint = new Footprint();
        $manufacturer1 = new Manufacturer();
        $manufacturer2 = new Manufacturer();
        $unit = new MeasurementUnit();
        $customState = new PartCustomState();

        $part1 = (new Part())
            ->setCategory($category)
            ->setManufacturer($manufacturer1);

        $part2 = (new Part())
            ->setFootprint($footprint)
            ->setManufacturer($manufacturer2)
            ->setPartUnit($unit)
            ->setPartCustomState($customState);

        $merged = $this->merger->merge($part1, $part2);
        $this->assertSame($merged, $part1);
        $this->assertSame($category, $merged->getCategory());
        $this->assertSame($footprint, $merged->getFootprint());
        $this->assertSame($manufacturer1, $merged->getManufacturer());
        $this->assertSame($unit, $merged->getPartUnit());
        $this->assertSame($customState, $merged->getPartCustomState());
    }

    public function testMergeOfTags(): void
    {
        $part1 = (new Part())
            ->setTags('tag1,tag2,tag3');

        $part2 = (new Part())
            ->setTags('tag2,tag3,tag4');

        $merged = $this->merger->merge($part1, $part2);
        $this->assertSame($merged, $part1);
        $this->assertSame('tag1,tag2,tag3,tag4', $merged->getTags());
    }

    public function testMergeOfBoolFields(): void
    {
        $part1 = (new Part())
            ->setFavorite(false)
            ->setNeedsReview(true);

        $part2 = (new Part())
            ->setFavorite(true)
            ->setNeedsReview(false);

        $merged = $this->merger->merge($part1, $part2);
        //Favorite and needs review should be true, as it is true in one of the parts
        $this->assertTrue($merged->isFavorite());
        $this->assertTrue($merged->isNeedsReview());
    }

    public function testMergeOfAssociatedPartsAsOther(): void
    {
        //Part1 is associated with part2 and part3:
        $part1 = (new Part())
            ->setName('part1');
        $part2 = (new Part())
            ->setName('part2');
        $part3 = (new Part())
            ->setName('part3');

        $association1 = (new PartAssociation())
            ->setOther($part2)
            ->setType(AssociationType::COMPATIBLE);

        $association2 = (new PartAssociation())
            ->setOther($part2)
            ->setType(AssociationType::SUPERSEDES);

        $association3 = (new PartAssociation())
            ->setOther($part3)
            ->setType(AssociationType::SUPERSEDES);

        $part1->addAssociatedPartsAsOwner($association1);
        $part1->addAssociatedPartsAsOwner($association2);
        $part1->addAssociatedPartsAsOwner($association3);
        //Fill the other side of the association manually, as we have no entity manager
        $part2->getAssociatedPartsAsOther()->add($association1);
        $part2->getAssociatedPartsAsOther()->add($association2);
        $part3->getAssociatedPartsAsOther()->add($association3);

        //Now we merge part2 into part3:
        $merged = $this->merger->merge($part3, $part2);
        $this->assertSame($merged, $part3);

        //Now part1 should have 4 associations, 2 with part2 and 2 with part3
        $this->assertCount(4, $part1->getAssociatedPartsAsOwner());
        $this->assertCount(2, $part1->getAssociatedPartsAsOwner()->filter(fn(PartAssociation $a) => $a->getOther() === $part2));
        $this->assertCount(2, $part1->getAssociatedPartsAsOwner()->filter(fn(PartAssociation $a) => $a->getOther() === $part3));
    }

    /**
     * This test also functions as test for EntityMergerHelperTrait::mergeCollections() so its pretty long.
     * @return void
     */
    public function testMergeOfPartLots(): void
    {
        $lot1 = (new PartLot())->setAmount(2)->setNeedsRefill(true);
        $lot2 = (new PartLot())->setInstockUnknown(true)->setUserBarcode('test');
        $lot3 = (new PartLot())->setDescription('lot3')->setAmount(3);
        $lot4 = (new PartLot())->setDescription('lot4')->setComment('comment');

        $part1 = (new Part())
            ->setName('Part 1')
            ->addPartLot($lot1)
            ->addPartLot($lot2);

        $part2 = (new Part())
            ->setName('Part 2')
            ->addPartLot($lot3)
            ->addPartLot($lot4);

        $merged = $this->merger->merge($part1, $part2);

        $this->assertInstanceOf(Part::class, $merged);
        //We should now have all 4 lots
        $this->assertCount(4, $merged->getPartLots());

        //The existing lots should be the same instance as before
        $this->assertSame($lot1, $merged->getPartLots()->get(0));
        $this->assertSame($lot2, $merged->getPartLots()->get(1));
        //While the new lots should be new instances
        $this->assertNotSame($lot3, $merged->getPartLots()->get(2));
        $this->assertNotSame($lot4, $merged->getPartLots()->get(3));

        //But the new lots, should be assigned to the target part and contain the same info
        $clone3 = $merged->getPartLots()->get(2);
        $clone4 = $merged->getPartLots()->get(3);
        $this->assertInstanceOf(PartLot::class, $clone3);
        $this->assertSame($merged, $clone3->getPart());
        $this->assertInstanceOf(PartLot::class, $clone4);
        $this->assertSame($merged, $clone4->getPart());

    }
    
    public function testMergeOfProjectBomEntries(): void
    {
        $projectA = (new Project())->setName('Project A');
        $projectB = (new Project())->setName('Project B');
        $projectC = (new Project())->setName('Project C');

        $part1 = (new Part())->setName('Part 1');
        $part2 = (new Part())->setName('Part 2');

        //Part 1 is used in project A
        $entryA = (new ProjectBOMEntry())->setQuantity(2.0);
        $projectA->addBomEntry($entryA);
        $part1->addProjectBomEntry($entryA);

        //Part 2 is used in project B and project C
        $entryB = (new ProjectBOMEntry())->setQuantity(3.0);
        $entryC = (new ProjectBOMEntry())->setQuantity(5.0);
        $projectB->addBomEntry($entryB);
        $projectC->addBomEntry($entryC);
        $part2->addProjectBomEntry($entryB);
        $part2->addProjectBomEntry($entryC);

        $merged = $this->merger->merge($part1, $part2);
        $this->assertSame($merged, $part1);

        //The merged part should now be used in all 3 projects
        $this->assertCount(3, $merged->getProjectBomEntries());
        //Project A was already using the target part, so its entry is untouched
        $this->assertSame($entryA, $merged->getProjectBomEntries()->get(0));
        $this->assertCount(1, $projectA->getBomEntries());
        $this->assertSame($entryA, $projectA->getBomEntries()->first());

        //The project B/C BOM entries are not cloned, they are just re-pointed to the target part in place
        $this->assertSame($entryB, $merged->getProjectBomEntries()->get(1));
        $this->assertSame($entryC, $merged->getProjectBomEntries()->get(2));
        $this->assertSame($projectB, $entryB->getProject());
        $this->assertSame($projectC, $entryC->getProject());
        $this->assertSame($merged, $entryB->getPart());
        $this->assertSame($merged, $entryC->getPart());

        //The projects' BOM entry collections are untouched, since the entry itself now just points to the new part
        $this->assertCount(1, $projectB->getBomEntries());
        $this->assertSame($entryB, $projectB->getBomEntries()->first());

        $this->assertCount(1, $projectC->getBomEntries());
        $this->assertSame($entryC, $projectC->getBomEntries()->first());

        //The other part must no longer reference the migrated entries in memory, so a subsequent deletion of it
        //(via the part-deletion listener) doesn't unlink/rename them again
        $this->assertCount(0, $part2->getProjectBomEntries());
    }

    public function testMergeOfProjectBomEntriesSameProjectQuantitiesAreSummed(): void
    {
        $project = (new Project())->setName('Shared project');

        $part1 = (new Part())->setName('Part 1');
        $part2 = (new Part())->setName('Part 2');

        $entry1 = (new ProjectBOMEntry())->setQuantity(2.0)
            ->setName('name1')->setMountnames('U1,U2')->setComment('comment1');
        $entry2 = (new ProjectBOMEntry())->setQuantity(4.0)
            ->setName('name2')->setMountnames('U3,U4')->setComment('comment2');
        $project->addBomEntry($entry1);
        $project->addBomEntry($entry2);
        $part1->addProjectBomEntry($entry1);
        $part2->addProjectBomEntry($entry2);

        $merged = $this->merger->merge($part1, $part2);

        //The old, now-redundant BOM entry should have been removed from the project
        $this->assertCount(1, $project->getBomEntries());
        $this->assertSame($entry1, $project->getBomEntries()->first());

        //Both entries reference the same project, so they should be merged into a single entry with summed quantity
        $this->assertCount(1, $merged->getProjectBomEntries());
        $mergedEntry = $merged->getProjectBomEntries()->get(0);
        $this->assertSame(6.0, $mergedEntry->getQuantity());
        //The names, mountnames and comments should be merged too, so no information is lost
        $this->assertSame('name1 / name2', $mergedEntry->getName());
        $this->assertSame('U1,U2,U3,U4', $mergedEntry->getMountnames());
        $this->assertSame("comment1 / comment2", $mergedEntry->getComment());
    }

    public function testMergeOfAttachmentsWithExternalPath(): void
    {
        $attachmentType = new AttachmentType();

        $existingExternalAttachment = (new PartAttachment())
            ->setName('datasheet')
            ->setAttachmentType($attachmentType)
            ->setExternalPath('https://example.invalid/datasheet.pdf')
            // Simulate the generated local path of a downloaded attachment.
            ->setInternalPath('%MEDIA%/part/1/datasheet-old-random.pdf');

        $existingLocalAttachment = (new PartAttachment())
            ->setName('local-file')
            ->setAttachmentType($attachmentType)
            ->setInternalPath('%MEDIA%/part/1/local-file.pdf');

        $part1 = (new Part())
            ->addAttachment($existingExternalAttachment)
            ->addAttachment($existingLocalAttachment);

        $updatedExternalAttachment = (new PartAttachment())
            ->setName('datasheet')
            ->setAttachmentType($attachmentType)
            ->setExternalPath('https://example.invalid/datasheet.pdf')
            // A different generated path must not create a duplicate.
            ->setInternalPath('%MEDIA%/part/1/datasheet-new-random.pdf');

        $sameLocalAttachment = (new PartAttachment())
            ->setName('local-file')
            ->setAttachmentType($attachmentType)
            ->setInternalPath('%MEDIA%/part/1/local-file.pdf');

        $differentLocalAttachment = (new PartAttachment())
            ->setName('different-local-file')
            ->setAttachmentType($attachmentType)
            ->setInternalPath('%MEDIA%/part/1/different-local-file.pdf');

        $differentNameAttachment = (new PartAttachment())
            ->setName('manual')
            ->setAttachmentType($attachmentType)
            ->setExternalPath('https://example.invalid/datasheet.pdf')
            ->setInternalPath('%MEDIA%/part/1/manual-random.pdf');

        $part2 = (new Part())
            ->addAttachment($updatedExternalAttachment)
            ->addAttachment($sameLocalAttachment)
            ->addAttachment($differentLocalAttachment)
            ->addAttachment($differentNameAttachment);

        $merged = $this->merger->merge($part1, $part2);

        // The matching external and local attachments must not be duplicated.
        $this->assertCount(4, $merged->getAttachments());

        $this->assertSame(
            $existingExternalAttachment,
            $merged->getAttachments()->get(0)
        );
        $this->assertSame(
            $existingLocalAttachment,
            $merged->getAttachments()->get(1)
        );

        // Non-matching attachments must still be added.
        $this->assertSame(
            $differentLocalAttachment->getInternalPath(),
            $merged->getAttachments()->get(2)->getInternalPath()
        );
        $this->assertSame(
            $differentNameAttachment->getExternalPath(),
            $merged->getAttachments()->get(3)->getExternalPath()
        );
    }

    public function testSupports()
    {
        $this->assertFalse($this->merger->supports(new \stdClass(), new \stdClass()));
        $this->assertFalse($this->merger->supports(new \stdClass(), new Part()));
        $this->assertTrue($this->merger->supports(new Part(), new Part()));
    }
}
