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

use App\Entity\Parts\Part;
use App\Entity\Parts\PartLot;
use App\Entity\ProjectSystem\PartLotReservation;
use App\Entity\ProjectSystem\PlannedProject;
use App\Entity\ProjectSystem\Project;
use App\Entity\ProjectSystem\ProjectBOMEntry;
use DateTime;
use PHPUnit\Framework\TestCase;

final class PartLotTest extends TestCase
{
    public function testIsExpired(): void
    {
        $lot = new PartLot();
        $this->assertNull($lot->isExpired(), 'Lot must be return null when no Expiration date is set!');


        $lot->setExpirationDate(new \DateTimeImmutable('+1 hour'));
        $this->assertFalse($lot->isExpired(), 'Lot with expiration date in the future must not be expired!');

        $lot->setExpirationDate(new \DateTimeImmutable('-1 hour'));
        $this->assertTrue($lot->isExpired(), 'Lot with expiration date in the past must be expired!');
    }

    private function makeReservation(PartLot $lot, float $amount): PartLotReservation
    {
        $plannedProject = new PlannedProject();
        $project = new Project();
        $bomEntry = new ProjectBOMEntry();
        $bomEntry->setProject($project);

        return new PartLotReservation($plannedProject, $bomEntry, $lot, $amount);
    }

    public function testGetReservedAmountIsZeroWithoutReservations(): void
    {
        $lot = new PartLot();
        $lot->setAmount(10);

        $this->assertSame(0.0, $lot->getReservedAmount());
        $this->assertSame(10.0, $lot->getAvailableAmount());
    }

    public function testGetReservedAmountSumsAllReservations(): void
    {
        $lot = new PartLot();
        $lot->setPart(new Part());
        $lot->setAmount(10);

        $lot->addReservation($this->makeReservation($lot, 3.0));
        $lot->addReservation($this->makeReservation($lot, 2.0));

        $this->assertEqualsWithDelta(5.0, $lot->getReservedAmount(), PHP_FLOAT_EPSILON);
        $this->assertEqualsWithDelta(5.0, $lot->getAvailableAmount(), PHP_FLOAT_EPSILON);
    }

    public function testGetAvailableAmountCanBecomeNegativeAfterStocktakeBelowReserved(): void
    {
        //A stocktake can legitimately reduce a lot's amount below what is still reserved for a planned project
        $lot = new PartLot();
        $lot->setPart(new Part());
        $lot->setAmount(2);

        $lot->addReservation($this->makeReservation($lot, 5.0));

        $this->assertEqualsWithDelta(-3.0, $lot->getAvailableAmount(), PHP_FLOAT_EPSILON);
    }
}
