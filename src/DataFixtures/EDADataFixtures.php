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

declare(strict_types=1);


namespace App\DataFixtures;

use App\Entity\Parts\Category;
use App\Entity\Parts\Footprint;
use App\Entity\Parts\Part;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class EDADataFixtures extends Fixture implements DependentFixtureInterface
{

    public function getDependencies(): array
    {
        return [PartFixtures::class];
    }

    public function load(ObjectManager $manager): void
    {
        //Load elements from DB
        $category1 = $manager->find(Category::class, 1);
        $footprint1 = $manager->find(Footprint::class, 1);

        $part1 = $manager->find(Part::class, 1);

        //Put some data into category1 and foorprint1
        $category1?->getEdaInfo()
            ->setExcludeFromBoard(true)
            ->setKicadSymbol('Category:1')
            ->setReferencePrefix('C')
        ;

        $footprint1?->getEdaInfo()
            ->setKicadFootprint('Footprint:1')
            ;

        //Put some data into part1 (which overrides the data from category1 and footprint1 on part1)
        $part1?->getEdaInfo()
            ->setExcludeFromSim(false)
            ->setKicadSymbol('Part:1')
            ->setKicadFootprint('Part:1')
            ->setReferencePrefix('P')
            ;

        //Flush the changes
        $manager->flush();
    }
}