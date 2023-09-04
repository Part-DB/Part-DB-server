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

use App\Entity\Parts\Part;
use App\Entity\Parts\PartLot;
use App\Entity\Parts\StorageLocation;
use App\Entity\UserSystem\User;
use App\Services\LabelSystem\PlaceholderProviders\PartLotProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class PartLotProviderTest extends WebTestCase
{
    /**
     * @var PartLotProvider
     */
    protected PartLotProvider $service;

    protected PartLot $target;

    protected function setUp(): void
    {
        self::bootKernel();
        \Locale::setDefault('en');
        $this->service = self::getContainer()->get(PartLotProvider::class);
        $this->target = new PartLot();
        $this->target->setDescription('Lot description');
        $this->target->setComment('Lot comment');
        $this->target->setExpirationDate(new \DateTime('1999-04-13'));
        $this->target->setInstockUnknown(true);

        $location = new StorageLocation();
        $location->setName('Location');
        $location->setParent((new StorageLocation())->setName('Parent'));
        $this->target->setStorageLocation($location);

        $part = new Part();
        $part->setName('Part');
        $part->setDescription('Part description');
        $this->target->setPart($part);

        $user = new User();
        $user->setName('user');
        $user->setFirstName('John');
        $user->setLastName('Doe');
        $this->target->setOwner($user);
    }

    public function dataProvider(): \Iterator
    {
        yield ['unknown', '[[LOT_ID]]'];
        yield ['Lot description', '[[LOT_NAME]]'];
        yield ['Lot comment', '[[LOT_COMMENT]]'];
        yield ['4/13/99', '[[EXPIRATION_DATE]]'];
        yield ['?', '[[AMOUNT]]'];
        yield ['Location', '[[LOCATION]]'];
        yield ['Parent → Location', '[[LOCATION_FULL]]'];
        //Test part inheritance
        yield ['Part', '[[NAME]]'];
        yield ['Part description', '[[DESCRIPTION]]'];
        yield ['John Doe', '[[OWNER]]'];
        yield ['user', '[[OWNER_USERNAME]]'];
    }

    /**
     * @dataProvider dataProvider
     */
    public function testReplace(string $expected, string $placeholder): void
    {
        $this->assertSame($expected, $this->service->replace($placeholder, $this->target));
    }
}
