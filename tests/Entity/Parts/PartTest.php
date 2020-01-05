<?php

declare(strict_types=1);

/**
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 * Copyright (C) 2019 Jan Böhmer (https://github.com/jbtronics)
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA
 */

namespace App\Tests\Entity\Parts;

use App\Entity\Parts\MeasurementUnit;
use App\Entity\Parts\Part;
use App\Entity\Parts\PartLot;
use DateTime;
use Doctrine\Common\Collections\Collection;
use PHPUnit\Framework\TestCase;

class PartTest extends TestCase
{
    public function testAddRemovePartLot(): void
    {
        $part = new Part();
        $this->assertInstanceOf(Collection::class, $part->getPartLots());
        $this->assertTrue($part->getPartLots()->isEmpty());

        //Add element
        $lot = new PartLot();
        $part->addPartLot($lot);
        $this->assertSame($part, $lot->getPart());
        $this->assertSame(1, $part->getPartLots()->count());

        //Remove element
        $part->removePartLot($lot);
        $this->assertTrue($part->getPartLots()->isEmpty());
    }

    public function testGetSetMinamount(): void
    {
        $part = new Part();
        $measurement_unit = new MeasurementUnit();

        //Without an set measurement unit the part must return an int
        $part->setMinAmount(1.345);
        $this->assertSame(1.0, $part->getMinAmount());

        //If an non int-based unit is assigned, an float is returned
        $part->setPartUnit($measurement_unit);
        $this->assertSame(1.345, $part->getMinAmount());

        //If an int-based unit is assigned an int is returned
        $measurement_unit->setIsInteger(true);
        $this->assertSame(1.0, $part->getMinAmount());
    }

    public function testUseFloatAmount(): void
    {
        $part = new Part();
        $measurement_unit = new MeasurementUnit();

        //Without an measurement unit int should be used
        $this->assertFalse($part->useFloatAmount());

        $part->setPartUnit($measurement_unit);
        $this->assertTrue($part->useFloatAmount());

        $measurement_unit->setIsInteger(true);
        $this->assertFalse($part->useFloatAmount());
    }

    public function testGetAmountSum(): void
    {
        $part = new Part();
        $measurement_unit = new MeasurementUnit();
        $datetime = new DateTime();

        $this->assertSame(0.0, $part->getAmountSum());

        $part->addPartLot((new PartLot())->setAmount(3.141));
        $part->addPartLot((new PartLot())->setAmount(10.0));
        $part->addPartLot((new PartLot())->setAmount(5)->setInstockUnknown(true));
        $part->addPartLot(
            (new PartLot())
                ->setAmount(6)
                ->setExpirationDate($datetime->setTimestamp(strtotime('now -1 hour')))
        );

        $this->assertSame(13.0, $part->getAmountSum());

        $part->setPartUnit($measurement_unit);
        $this->assertSame(13.141, $part->getAmountSum());

        //1 billion part lot
        $part->addPartLot((new PartLot())->setAmount(1000000000));
        $this->assertSame(1000000013.141, $part->getAmountSum());
        $measurement_unit->setIsInteger(true);
        $this->assertSame(1000000013.0, $part->getAmountSum());
    }
}
