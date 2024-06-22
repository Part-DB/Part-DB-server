<?php
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

declare(strict_types=1);

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

        //Without a set measurement unit the part must return an int
        $part->setMinAmount(1.345);
        $this->assertEqualsWithDelta(1.0, $part->getMinAmount(), PHP_FLOAT_EPSILON);

        //If a non-int-based unit is assigned, a float is returned
        $part->setPartUnit($measurement_unit);
        $this->assertEqualsWithDelta(1.345, $part->getMinAmount(), PHP_FLOAT_EPSILON);

        //If an int-based unit is assigned an int is returned
        $measurement_unit->setIsInteger(true);
        $this->assertEqualsWithDelta(1.0, $part->getMinAmount(), PHP_FLOAT_EPSILON);
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

        $this->assertEqualsWithDelta(0.0, $part->getAmountSum(), PHP_FLOAT_EPSILON);

        $part->addPartLot((new PartLot())->setAmount(3.141));
        $part->addPartLot((new PartLot())->setAmount(10.0));
        $part->addPartLot((new PartLot())->setAmount(5)->setInstockUnknown(true));
        $part->addPartLot(
            (new PartLot())
                ->setAmount(6)
                ->setExpirationDate(new \DateTimeImmutable('-1 hour'))
        );

        $this->assertEqualsWithDelta(13.0, $part->getAmountSum(), PHP_FLOAT_EPSILON);

        $part->setPartUnit($measurement_unit);
        $this->assertEqualsWithDelta(13.141, $part->getAmountSum(), PHP_FLOAT_EPSILON);

        //1 billion part lot
        $part->addPartLot((new PartLot())->setAmount(1_000_000_000));
        $this->assertEqualsWithDelta(1_000_000_013.141, $part->getAmountSum(), PHP_FLOAT_EPSILON);
        $measurement_unit->setIsInteger(true);
        $this->assertEqualsWithDelta(1_000_000_013.0, $part->getAmountSum(), PHP_FLOAT_EPSILON);
    }
}
