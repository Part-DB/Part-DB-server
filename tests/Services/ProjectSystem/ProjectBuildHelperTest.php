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
namespace App\Tests\Services\ProjectSystem;

use App\Entity\Parts\Part;
use App\Entity\Parts\PartLot;
use App\Entity\ProjectSystem\Project;
use App\Entity\ProjectSystem\ProjectBOMEntry;
use App\Entity\PriceInformations\Orderdetail;
use App\Entity\PriceInformations\Pricedetail;
use App\Services\ProjectSystem\ProjectBuildHelper;
use Brick\Math\BigDecimal;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ProjectBuildHelperTest extends WebTestCase
{
    protected ProjectBuildHelper $service;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->service = self::getContainer()->get(ProjectBuildHelper::class);
    }

    public function testGetMaximumBuildableCountForBOMEntryNonPartBomEntry(): void
    {
        $bom_entry = new ProjectBOMEntry();
        $bom_entry->setPart(null);
        $bom_entry->setQuantity(10);
        $bom_entry->setName('Test');

        $this->expectException(\InvalidArgumentException::class);
        $this->service->getMaximumBuildableCountForBOMEntry($bom_entry);
    }

    public function testGetMaximumBuildableCountForBOMEntry(): void
    {
        $project_bom_entry = new ProjectBOMEntry();
        $project_bom_entry->setQuantity(10);

        $part = new Part();
        $lot1 = new PartLot();
        $lot1->setAmount(120);
        $lot2 = new PartLot();
        $lot2->setAmount(5);
        $part->addPartLot($lot1);
        $part->addPartLot($lot2);

        $project_bom_entry->setPart($part);

        //We have 125 parts in stock, so we can build 12 times the project (125 / 10 = 12.5)
        $this->assertSame(12, $this->service->getMaximumBuildableCountForBOMEntry($project_bom_entry));


        $lot1->setAmount(0);
        //We have 5 parts in stock, so we can build 0 times the project (5 / 10 = 0.5)
        $this->assertSame(0, $this->service->getMaximumBuildableCountForBOMEntry($project_bom_entry));
    }

    public function testGetMaximumBuildableCount(): void
    {
        $project = new Project();

        $project_bom_entry1 = new ProjectBOMEntry();
        $project_bom_entry1->setQuantity(10);
        $part = new Part();
        $lot1 = new PartLot();
        $lot1->setAmount(120);
        $lot2 = new PartLot();
        $lot2->setAmount(5);
        $part->addPartLot($lot1);
        $part->addPartLot($lot2);
        $project_bom_entry1->setPart($part);
        $project->addBomEntry($project_bom_entry1);

        $project_bom_entry2 = new ProjectBOMEntry();
        $project_bom_entry2->setQuantity(5);
        $part2 = new Part();
        $lot3 = new PartLot();
        $lot3->setAmount(10);
        $part2->addPartLot($lot3);
        $project_bom_entry2->setPart($part2);
        $project->addBomEntry($project_bom_entry2);

        $project->addBomEntry((new ProjectBOMEntry())->setName('Non part entry')->setQuantity(1));

        //Restricted by the few parts in stock of part2
        $this->assertSame(2, $this->service->getMaximumBuildableCount($project));

        $lot3->setAmount(1000);
        //Now the build count is restricted by the few parts in stock of part1
        $this->assertSame(12, $this->service->getMaximumBuildableCount($project));

        $lot3->setAmount(0);
        //Now the build count must be 0, as we have no parts in stock
        $this->assertSame(0, $this->service->getMaximumBuildableCount($project));

    }

    public function testGetMaximumBuildableCountEmpty(): void
    {
        $project = new Project();

        $this->assertSame(0, $this->service->getMaximumBuildableCount($project));
    }

    public function testGetMaximumBuildableCountAsString(): void
    {
        $project = new Project();
        $bom_entry1 = new ProjectBOMEntry();
        $bom_entry1->setName("Test");
        $project->addBomEntry($bom_entry1);

        $this->assertSame('∞', $this->service->getMaximumBuildableCountAsString($project));
    }

    // --- Build price tests ---

    private function makePartWithPrice(float $pricePerPiece, float $minQty = 1.0): Part
    {
        $part = new Part();
        $orderdetail = new Orderdetail();
        $pricedetail = (new Pricedetail())
            ->setMinDiscountQuantity($minQty)
            ->setPrice(BigDecimal::of((string) $pricePerPiece));
        $orderdetail->addPricedetail($pricedetail);
        $part->addOrderdetail($orderdetail);
        return $part;
    }

    public function testCalculateTotalBuildPriceEmptyProject(): void
    {
        $project = new Project();
        $this->assertNull($this->service->calculateTotalBuildPrice($project));
    }

    public function testCalculateTotalBuildPriceNoPricingData(): void
    {
        $project = new Project();
        // Part with no orderdetails — no pricing
        $entry = (new ProjectBOMEntry())->setPart(new Part())->setQuantity(2);
        $project->addBomEntry($entry);

        $this->assertNull($this->service->calculateTotalBuildPrice($project));
    }

    public function testCalculateTotalBuildPriceNonPartEntry(): void
    {
        $project = new Project();
        $entry = new ProjectBOMEntry();
        $entry->setName('Custom wire');
        $entry->setQuantity(3);
        $entry->setPrice(BigDecimal::of('2.00'));
        $project->addBomEntry($entry);

        // 3 × 2.00 = 6.00 for 1 build
        $result = $this->service->calculateTotalBuildPrice($project, 1);
        $this->assertNotNull($result);
        $this->assertTrue(BigDecimal::of('6.00')->isEqualTo($result));
    }

    public function testCalculateTotalBuildPriceNonPartEntryMultipleBuilds(): void
    {
        $project = new Project();
        $entry = new ProjectBOMEntry();
        $entry->setName('Custom wire');
        $entry->setQuantity(3);
        $entry->setPrice(BigDecimal::of('2.00'));
        $project->addBomEntry($entry);

        // 3 × 2.00 × 5 = 30.00 for 5 builds
        $result = $this->service->calculateTotalBuildPrice($project, 5);
        $this->assertNotNull($result);
        $this->assertTrue(BigDecimal::of('30.00')->isEqualTo($result));
    }

    public function testCalculateTotalBuildPriceWithPart(): void
    {
        $project = new Project();
        $entry = new ProjectBOMEntry();
        $entry->setPart($this->makePartWithPrice(1.50));
        $entry->setQuantity(4);
        $project->addBomEntry($entry);

        // 4 × 1.50 = 6.00 for 1 build
        $result = $this->service->calculateTotalBuildPrice($project, 1);
        $this->assertNotNull($result);
        $this->assertTrue(BigDecimal::of('6.00')->isEqualTo($result));
    }

    public function testCalculateUnitBuildPriceEqualsTotal(): void
    {
        $project = new Project();
        $entry = new ProjectBOMEntry();
        $entry->setName('Screw');
        $entry->setQuantity(10);
        $entry->setPrice(BigDecimal::of('0.10'));
        $project->addBomEntry($entry);

        // unit = 10 × 0.10 = 1.00; total for 3 builds = 3.00
        $unit = $this->service->calculateUnitBuildPrice($project, 3);
        $total = $this->service->calculateTotalBuildPrice($project, 3);
        $this->assertNotNull($unit);
        $this->assertNotNull($total);
        $this->assertTrue($total->isEqualTo($unit->multipliedBy(3)));
    }

    public function testRoundedTotalBuildPriceRoundsUp(): void
    {
        $project = new Project();
        $entry = new ProjectBOMEntry();
        $entry->setName('Tiny part');
        $entry->setQuantity(1);
        $entry->setPrice(BigDecimal::of('0.001'));
        $project->addBomEntry($entry);

        // 0.001 rounded up to 2dp = 0.01
        $result = $this->service->roundedTotalBuildPrice($project, 1);
        $this->assertNotNull($result);
        $this->assertTrue(BigDecimal::of('0.01')->isEqualTo($result));
    }

    public function testCalculateTotalBuildPriceMixedEntries(): void
    {
        $project = new Project();

        // Part entry: 2 × 3.00 = 6.00
        $partEntry = new ProjectBOMEntry();
        $partEntry->setPart($this->makePartWithPrice(3.00));
        $partEntry->setQuantity(2);
        $project->addBomEntry($partEntry);

        // Non-part entry with price: 5 × 1.00 = 5.00
        $nonPartEntry = new ProjectBOMEntry();
        $nonPartEntry->setName('Solder');
        $nonPartEntry->setQuantity(5);
        $nonPartEntry->setPrice(BigDecimal::of('1.00'));
        $project->addBomEntry($nonPartEntry);

        // Total = 11.00
        $result = $this->service->calculateTotalBuildPrice($project, 1);
        $this->assertNotNull($result);
        $this->assertTrue(BigDecimal::of('11.00')->isEqualTo($result));
    }

    public function testGetEntryUnitPriceReturnsZeroForNoPricingData(): void
    {
        $entry = new ProjectBOMEntry();
        $entry->setPart(new Part()); // part with no orderdetails
        $entry->setQuantity(5);

        $result = $this->service->getEntryUnitPrice($entry);
        $this->assertTrue(BigDecimal::zero()->isEqualTo($result));
    }

    public function testGetEntryUnitPriceNonPartEntry(): void
    {
        $entry = new ProjectBOMEntry();
        $entry->setName('Wire');
        $entry->setQuantity(2);
        $entry->setPrice(BigDecimal::of('1.25'));

        $result = $this->service->getEntryUnitPrice($entry);
        $this->assertTrue(BigDecimal::of('1.25')->isEqualTo($result));
    }

    public function testGetEntryUnitPriceWithPart(): void
    {
        $entry = new ProjectBOMEntry();
        $entry->setPart($this->makePartWithPrice(2.00));
        $entry->setQuantity(3);

        $result = $this->service->getEntryUnitPrice($entry);
        $this->assertTrue(BigDecimal::of('2.00')->isEqualTo($result));
    }

    public function testCalculateTotalBuildPriceRespectsMinOrderAmount(): void
    {
        $project = new Project();
        // Part has a minimum order quantity of 10 at 0.50/piece
        $entry = new ProjectBOMEntry();
        $entry->setPart($this->makePartWithPrice(0.50, 10.0));
        $entry->setQuantity(1); // BOM only needs 1, but MOQ is 10
        $project->addBomEntry($entry);

        // Price lookup uses qty=10 (MOQ), returns 0.50. Cost = 1 × 0.50 = 0.50
        $result = $this->service->calculateTotalBuildPrice($project, 1);
        $this->assertNotNull($result);
        $this->assertTrue(BigDecimal::of('0.50')->isEqualTo($result));
    }
}
