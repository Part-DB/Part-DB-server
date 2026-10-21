<?php

declare(strict_types=1);

namespace App\Tests\Entity\ProjectSystem;

use App\Entity\Parts\MeasurementUnit;
use App\Entity\Parts\Part;
use App\Entity\Parts\PartLot;
use App\Entity\ProjectSystem\PartLotReservation;
use App\Entity\ProjectSystem\PlannedProject;
use App\Entity\ProjectSystem\PlannedProjectStatus;
use App\Entity\ProjectSystem\ProjectBOMEntry;
use PHPUnit\Framework\TestCase;

final class PlannedProjectTest extends TestCase
{
    public function testDefaults(): void
    {
        $plannedProject = new PlannedProject();

        $this->assertSame(PlannedProjectStatus::PLANNED, $plannedProject->getStatus());
        $this->assertSame(1, $plannedProject->getNumberOfBuilds());
        $this->assertCount(0, $plannedProject->getReservations());
        //Only a built plan has a record of what was consumed
        $this->assertNull($plannedProject->getBuiltParts());
    }

    public function testRequiredAmountIsRoundedForIntegerParts(): void
    {
        $plannedProject = new PlannedProject();
        $plannedProject->setNumberOfBuilds(3);

        $entry = new ProjectBOMEntry();
        $entry->setPart(new Part());
        $entry->setQuantity(1.5);

        //No unit: only whole numbers of parts exist, so 3 x 1.5 = 4.5 is rounded
        $this->assertEqualsWithDelta(5.0, $plannedProject->getRequiredAmountForBOMEntry($entry), PHP_FLOAT_EPSILON);

        $unit = new MeasurementUnit();
        $unit->setIsInteger(false);
        $float_part = new Part();
        $float_part->setPartUnit($unit);
        $entry->setPart($float_part);
        $this->assertEqualsWithDelta(4.5, $plannedProject->getRequiredAmountForBOMEntry($entry), PHP_FLOAT_EPSILON);
    }

    public function testReservedAmountIsSummedPerBomEntry(): void
    {
        $plannedProject = new PlannedProject();
        $part = new Part();

        $entry1 = new ProjectBOMEntry();
        $entry1->setPart($part);
        $entry2 = new ProjectBOMEntry();
        $entry2->setPart($part);

        $lot1 = new PartLot();
        $lot2 = new PartLot();

        $plannedProject->addReservation(new PartLotReservation($plannedProject, $entry1, $lot1, 2.0));
        $plannedProject->addReservation(new PartLotReservation($plannedProject, $entry1, $lot2, 3.0));
        $plannedProject->addReservation(new PartLotReservation($plannedProject, $entry2, $lot1, 7.0));

        $this->assertEqualsWithDelta(5.0, $plannedProject->getReservedAmountForBOMEntry($entry1), PHP_FLOAT_EPSILON);
        $this->assertEqualsWithDelta(7.0, $plannedProject->getReservedAmountForBOMEntry($entry2), PHP_FLOAT_EPSILON);
        $this->assertEqualsWithDelta(0.0, $plannedProject->getReservedAmountForBOMEntry(new ProjectBOMEntry()), PHP_FLOAT_EPSILON);
    }

    public function testBuiltPartsSnapshot(): void
    {
        $plannedProject = new PlannedProject();
        $snapshot = [[
            'part_id' => 5,
            'part_name' => 'Resistor',
            'bom_entry_name' => null,
            'mountnames' => 'R1,R2',
            'needed' => 2.0,
            'lots' => [['name' => 'Box', 'storage_location' => 'Shelf', 'amount' => 2.0]],
        ]];

        $plannedProject->setBuiltParts($snapshot);
        $this->assertSame($snapshot, $plannedProject->getBuiltParts());
    }
}
