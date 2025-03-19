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
namespace App\Tests\Services\AssemblySystem;

use App\Entity\AssemblySystem\Assembly;
use App\Entity\AssemblySystem\AssemblyBOMEntry;
use App\Entity\Parts\Part;
use App\Entity\Parts\PartLot;
use App\Services\AssemblySystem\AssemblyBuildHelper;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class AssemblyBuildHelperTest extends WebTestCase
{
    /** @var AssemblyBuildHelper */
    protected $service;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->service = self::getContainer()->get(AssemblyBuildHelper::class);
    }

    public function testGetMaximumBuildableCountForBOMEntryNonPartBomEntry(): void
    {
        $bom_entry = new AssemblyBOMEntry();
        $bom_entry->setPart(null);
        $bom_entry->setQuantity(10);
        $bom_entry->setName('Test');

        $this->expectException(\InvalidArgumentException::class);
        $this->service->getMaximumBuildableCountForBOMEntry($bom_entry);
    }

    public function testGetMaximumBuildableCountForBOMEntry(): void
    {
        $assembly_bom_entry = new AssemblyBOMEntry();
        $assembly_bom_entry->setQuantity(10);

        $part = new Part();
        $lot1 = new PartLot();
        $lot1->setAmount(120);
        $lot2 = new PartLot();
        $lot2->setAmount(5);
        $part->addPartLot($lot1);
        $part->addPartLot($lot2);

        $assembly_bom_entry->setPart($part);

        //We have 125 parts in stock, so we can build 12 times the assembly (125 / 10 = 12.5)
        $this->assertSame(12, $this->service->getMaximumBuildableCountForBOMEntry($assembly_bom_entry));


        $lot1->setAmount(0);
        //We have 5 parts in stock, so we can build 0 times the assembly (5 / 10 = 0.5)
        $this->assertSame(0, $this->service->getMaximumBuildableCountForBOMEntry($assembly_bom_entry));
    }

    public function testGetMaximumBuildableCount(): void
    {
        $assembly = new Assembly();

        $assembly_bom_entry1 = new AssemblyBOMEntry();
        $assembly_bom_entry1->setQuantity(10);
        $part = new Part();
        $lot1 = new PartLot();
        $lot1->setAmount(120);
        $lot2 = new PartLot();
        $lot2->setAmount(5);
        $part->addPartLot($lot1);
        $part->addPartLot($lot2);
        $assembly_bom_entry1->setPart($part);
        $assembly->addBomEntry($assembly_bom_entry1);

        $assembly_bom_entry2 = new AssemblyBOMEntry();
        $assembly_bom_entry2->setQuantity(5);
        $part2 = new Part();
        $lot3 = new PartLot();
        $lot3->setAmount(10);
        $part2->addPartLot($lot3);
        $assembly_bom_entry2->setPart($part2);
        $assembly->addBomEntry($assembly_bom_entry2);

        $assembly->addBomEntry((new AssemblyBOMEntry())->setName('Non part entry')->setQuantity(1));

        //Restricted by the few parts in stock of part2
        $this->assertSame(2, $this->service->getMaximumBuildableCount($assembly));

        $lot3->setAmount(1000);
        //Now the build count is restricted by the few parts in stock of part1
        $this->assertSame(12, $this->service->getMaximumBuildableCount($assembly));

        $lot3->setAmount(0);
        //Now the build count must be 0, as we have no parts in stock
        $this->assertSame(0, $this->service->getMaximumBuildableCount($assembly));

    }
}
