<?php
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

namespace App\Tests\Helpers\Projects;

use App\Entity\Parts\MeasurementUnit;
use App\Entity\Parts\Part;
use App\Entity\Parts\PartLot;
use App\Entity\ProjectSystem\Project;
use App\Entity\ProjectSystem\ProjectBOMEntry;
use App\Helpers\Projects\ProjectBuildRequest;
use PHPUnit\Framework\TestCase;

class ProjectBuildRequestTest extends TestCase
{

    /** @var MeasurementUnit $float_unit */
    private MeasurementUnit $float_unit;

    /** @var Project */
    private Project $project1;
    /** @var ProjectBOMEntry */
    private ProjectBOMEntry $bom_entry1a;
    /** @var ProjectBOMEntry */
    private ProjectBOMEntry $bom_entry1b;
    /** @var ProjectBOMEntry */
    private ProjectBOMEntry $bom_entry1c;

    private PartLot $lot1a;
    private PartLot $lot1b;
    private PartLot $lot2;

    /** @var Part */
    private Part $part1;
    /** @var Part */
    private Part $part2;


    public function setUp(): void
    {
        $this->float_unit = new MeasurementUnit();
        $this->float_unit->setName('float');
        $this->float_unit->setUnit('f');
        $this->float_unit->setIsInteger(false);
        $this->float_unit->setUseSIPrefix(true);

        //Setup some example parts and part lots
        $this->part1 = new Part();
        $this->part1->setName('Part 1');
        $this->lot1a = new class extends PartLot {
            public function getID(): ?int
            {
                return 1;
            }
        };
        $this->part1->addPartLot($this->lot1a);
        $this->lot1a->setAmount(10);
        $this->lot1a->setDescription('Lot 1a');

        $this->lot1b = new class extends PartLot {
            public function getID(): ?int
            {
                return 2;
            }
        };
        $this->part1->addPartLot($this->lot1b);
        $this->lot1b->setAmount(20);
        $this->lot1b->setDescription('Lot 1b');

        $this->part2 = new Part();

        $this->part2->setName('Part 2');
        $this->part2->setPartUnit($this->float_unit);
        $this->lot2 = new PartLot();
        $this->part2->addPartLot($this->lot2);
        $this->lot2->setAmount(2.5);
        $this->lot2->setDescription('Lot 2');

        $this->bom_entry1a = new ProjectBOMEntry();
        $this->bom_entry1a->setPart($this->part1);
        $this->bom_entry1a->setQuantity(2);

        $this->bom_entry1b = new ProjectBOMEntry();
        $this->bom_entry1b->setPart($this->part2);
        $this->bom_entry1b->setQuantity(1.5);

        $this->bom_entry1c = new ProjectBOMEntry();
        $this->bom_entry1c->setName('Non-part BOM entry');
        $this->bom_entry1c->setQuantity(4);


        $this->project1 = new Project();
        $this->project1->setName('Project 1');
        $this->project1->addBomEntry($this->bom_entry1a);
        $this->project1->addBomEntry($this->bom_entry1b);
        $this->project1->addBomEntry($this->bom_entry1c);
    }

    public function testInitialization(): void
    {
        //The values should be already prefilled correctly
        $request = new ProjectBuildRequest($this->project1, 10);
        //We need totally 20: Take 10 from the first (maximum 10) and 10 from the second (maximum 20)
        $this->assertEquals(10, $request->getLotWithdrawAmount($this->lot1a));
        $this->assertEquals(10, $request->getLotWithdrawAmount($this->lot1b));

        //If the needed amount is higher than the maximum, we should get the maximum
        $this->assertEquals(2.5, $request->getLotWithdrawAmount($this->lot2));
    }

    public function testGetNumberOfBuilds(): void
    {
        $build_request = new ProjectBuildRequest($this->project1, 5);
        $this->assertEquals(5, $build_request->getNumberOfBuilds());
    }

    public function testGetProject(): void
    {
        $build_request = new ProjectBuildRequest($this->project1, 5);
        $this->assertEquals($this->project1, $build_request->getProject());
    }

    public function testGetNeededAmountForBOMEntry(): void
    {
        $build_request = new ProjectBuildRequest($this->project1, 5);
        $this->assertEquals(10, $build_request->getNeededAmountForBOMEntry($this->bom_entry1a));
        $this->assertEquals(7.5, $build_request->getNeededAmountForBOMEntry($this->bom_entry1b));
        $this->assertEquals(20, $build_request->getNeededAmountForBOMEntry($this->bom_entry1c));
    }

    public function testGetSetLotWithdrawAmount(): void
    {
        $build_request = new ProjectBuildRequest($this->project1, 5);

        //We can set the amount for a lot either via the lot object or via the ID
        $build_request->setLotWithdrawAmount($this->lot1a, 2);
        $build_request->setLotWithdrawAmount($this->lot1b->getID(), 3);

        //And it should be possible to get the amount via the lot object or via the ID
        $this->assertEquals(2, $build_request->getLotWithdrawAmount($this->lot1a->getID()));
        $this->assertEquals(3, $build_request->getLotWithdrawAmount($this->lot1b));
    }

    public function testGetWithdrawAmountSum(): void
    {
        //The sum of all withdraw amounts for an BOM entry (over all lots of the associated part) should be correct
        $build_request = new ProjectBuildRequest($this->project1, 5);

        $build_request->setLotWithdrawAmount($this->lot1a, 2);
        $build_request->setLotWithdrawAmount($this->lot1b, 3);

        $this->assertEquals(5, $build_request->getWithdrawAmountSum($this->bom_entry1a));
        $build_request->setLotWithdrawAmount($this->lot2, 1.5);
        $this->assertEquals(1.5, $build_request->getWithdrawAmountSum($this->bom_entry1b));
    }


}
