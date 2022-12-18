<?php
/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2022 Jan Böhmer (https://github.com/jbtronics)
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

/**
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 * Copyright (C) 2019 - 2020 Jan Böhmer (https://github.com/jbtronics)
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

namespace App\Tests\Services\LabelSystem\PlaceholderProviders;

use App\Entity\Parts\Category;
use App\Entity\Parts\Footprint;
use App\Entity\Parts\Part;
use App\Services\LabelSystem\PlaceholderProviders\PartProvider;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * @group DB
 */
class PartProviderTest extends WebTestCase
{
    /**
     * @var PartProvider
     */
    protected $service;

    protected $target;

    /**
     * @var \Doctrine\ORM\EntityManager
     */
    protected $em;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->service = self::getContainer()->get(PartProvider::class);
        $this->target = new Part();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);

        $this->target->setCategory($this->em->find(Category::class, 6));
        $this->target->setFootprint($this->em->find(Footprint::class, 6));
        $this->target->setManufacturer(null);

        $this->target->setMass(1234.2);
        $this->target->setTags('SMD, Tag1, Tag2');
        $this->target->setManufacturerProductNumber('MPN123');
        $this->target->setManufacturingStatus('active');

        $this->target->setDescription('<b>Bold</b> *Italic*');
        $this->target->setComment('<b>Bold</b> *Italic*');
    }

    public function dataProvider(): array
    {
        return [
            ['Node 2.1', '[[CATEGORY]]'],
            ['Node 2 → Node 2.1', '[[CATEGORY_FULL]]'],
            ['Node 2.1', '[[FOOTPRINT]]'],
            ['Node 2 → Node 2.1', '[[FOOTPRINT_FULL]]'],
            ['', '[[MANUFACTURER]]'],
            ['', '[[MANUFACTURER_FULL]]'],

            ['1.2 kg', '[[MASS]]'],
            ['MPN123', '[[MPN]]'],
            ['SMD, Tag1, Tag2', '[[TAGS]]'],
            ['Active', '[[M_STATUS]]'],
            ['<b>Bold</b> <em>Italic</em>', '[[DESCRIPTION]]'],
            ['Bold Italic', '[[DESCRIPTION_T]]'],
            ['<b>Bold</b> <em>Italic</em>', '[[COMMENT]]'],
            ['Bold Italic', '[[COMMENT_T]]'],
        ];
    }

    /**
     * @dataProvider dataProvider
     */
    public function testReplace(string $expected, string $placeholder): void
    {
        $this->assertSame($expected, $this->service->replace($placeholder, $this->target));
    }
}
