<?php

declare(strict_types=1);

namespace App\Tests\Helpers\Projects;

use App\Entity\Parts\Part;
use App\Entity\Parts\PartLot;
use App\Entity\ProjectSystem\PartLotReservation;
use App\Entity\ProjectSystem\PlannedProject;
use App\Entity\ProjectSystem\Project;
use App\Entity\ProjectSystem\ProjectBOMEntry;
use App\Helpers\Projects\PlanProjectRequest;
use PHPUnit\Framework\TestCase;

final class PlanProjectRequestTest extends TestCase
{
    private function createLot(int $id, float $amount): PartLot
    {
        $lot = new class ($id) extends PartLot {
            public function __construct(private readonly int $fakeId)
            {
                parent::__construct();
            }

            public function getID(): ?int
            {
                return $this->fakeId;
            }
        };
        $lot->setAmount($amount);

        return $lot;
    }

    public function testNumberOfBuildsMustBePositive(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new PlanProjectRequest(new Project(), 0);
    }

    public function testStockIsReservedInOrderAndLimitedToWhatIsAvailable(): void
    {
        $part = new Part();
        $lot1 = $this->createLot(1, 2);
        $lot2 = $this->createLot(2, 10);
        $part->addPartLot($lot1);
        $part->addPartLot($lot2);

        $project = new Project();
        $entry = new ProjectBOMEntry();
        $entry->setPart($part);
        $entry->setQuantity(3);
        $project->addBomEntry($entry);

        $request = new PlanProjectRequest($project, 2);

        //6 are needed: 2 from the first lot, the remaining 4 from the second one
        $this->assertEqualsWithDelta(6.0, $request->getNeededAmountForBOMEntry($entry), PHP_FLOAT_EPSILON);
        $this->assertEqualsWithDelta(2.0, $request->getLotReservationAmount($lot1), PHP_FLOAT_EPSILON);
        $this->assertEqualsWithDelta(4.0, $request->getLotReservationAmount($lot2), PHP_FLOAT_EPSILON);
        $this->assertEqualsWithDelta(6.0, $request->getReservationAmountSum($entry), PHP_FLOAT_EPSILON);
    }

    public function testStockThatIsAlreadyReservedIsNotReservedAgain(): void
    {
        $part = new Part();
        $lot = $this->createLot(1, 5);
        $part->addPartLot($lot);

        $project = new Project();
        $entry = new ProjectBOMEntry();
        $entry->setPart($part);
        $entry->setQuantity(5);
        $project->addBomEntry($entry);

        //4 of the 5 are already reserved by another plan
        $other_plan = new PlannedProject();
        $lot->addReservation(new PartLotReservation($other_plan, $entry, $lot, 4.0));

        $request = new PlanProjectRequest($project, 1);
        $this->assertEqualsWithDelta(1.0, $request->getLotReservationAmount($lot), PHP_FLOAT_EPSILON);
    }

    public function testPartWithoutLotsHasNothingToReserve(): void
    {
        $project = new Project();
        $entry = new ProjectBOMEntry();
        $entry->setPart(new Part());
        $entry->setQuantity(1);
        $project->addBomEntry($entry);

        $request = new PlanProjectRequest($project, 1);

        $this->assertSame([], $request->getPartLotsForBOMEntry($entry));
        $this->assertEqualsWithDelta(0.0, $request->getReservationAmountSum($entry), PHP_FLOAT_EPSILON);
        $this->assertEqualsWithDelta(1.0, $request->getNeededAmountForBOMEntry($entry), PHP_FLOAT_EPSILON);
    }

    public function testLotsWithUnknownInstockAreNotUsed(): void
    {
        $part = new Part();
        $lot = $this->createLot(1, 5);
        $lot->setInstockUnknown(true);
        $part->addPartLot($lot);

        $project = new Project();
        $entry = new ProjectBOMEntry();
        $entry->setPart($part);
        $entry->setQuantity(1);
        $project->addBomEntry($entry);

        $request = new PlanProjectRequest($project, 1);
        $this->assertSame([], array_values($request->getPartLotsForBOMEntry($entry)));
    }
}
