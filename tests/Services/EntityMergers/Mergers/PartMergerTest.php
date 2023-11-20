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

namespace App\Tests\Services\EntityMergers\Mergers;

use App\Entity\Parts\Category;
use App\Entity\Parts\Footprint;
use App\Entity\Parts\Manufacturer;
use App\Entity\Parts\MeasurementUnit;
use App\Entity\Parts\Part;
use App\Entity\Parts\PartLot;
use App\Services\EntityMergers\Mergers\PartMerger;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class PartMergerTest extends KernelTestCase
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

        $part1 = (new Part())
            ->setCategory($category)
            ->setManufacturer($manufacturer1);

        $part2 = (new Part())
            ->setFootprint($footprint)
            ->setManufacturer($manufacturer2)
            ->setPartUnit($unit);

        $merged = $this->merger->merge($part1, $part2);
        $this->assertSame($merged, $part1);
        $this->assertSame($category, $merged->getCategory());
        $this->assertSame($footprint, $merged->getFootprint());
        $this->assertSame($manufacturer1, $merged->getManufacturer());
        $this->assertSame($unit, $merged->getPartUnit());
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

    /**
     * This test also functions as test for EntityMergerHelperTrait::mergeCollections() so its pretty long.
     * @return void
     */
    public function testMergeOfPartLots(): void
    {
        $lot1 = (new PartLot())->setAmount(2)->setNeedsRefill(true);
        $lot2 = (new PartLot())->setInstockUnknown(true)->setVendorBarcode('test');
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
        $this->assertSame($merged, $clone3->getPart());
        $this->assertSame($merged, $clone4->getPart());

    }

    public function testSupports()
    {
        $this->assertFalse($this->merger->supports(new \stdClass(), new \stdClass()));
        $this->assertFalse($this->merger->supports(new \stdClass(), new Part()));
        $this->assertTrue($this->merger->supports(new Part(), new Part()));
    }
}
