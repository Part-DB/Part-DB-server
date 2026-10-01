<?php

declare(strict_types=1);

namespace App\Tests\Services\ProjectSystem;

use App\Entity\Parts\Part;
use App\Entity\Parts\PartLot;
use App\Entity\ProjectSystem\PartLotReservation;
use App\Entity\ProjectSystem\PlannedProject;
use App\Entity\ProjectSystem\PlannedProjectStatus;
use App\Entity\ProjectSystem\Project;
use App\Entity\ProjectSystem\ProjectBOMEntry;
use App\Helpers\Projects\BuildPlannedProjectRequest;
use App\Helpers\Projects\PlanProjectRequest;
use App\Services\ProjectSystem\ProjectPlanningHelper;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * A PartLot subclass with a settable, distinct fake ID - needed because PlanProjectRequest keys its per-lot
 * reservation amounts by lot ID, so (unlike the plain TestPartLot used in PartLotWithdrawAddHelperTest, which
 * hardcodes a single shared ID) multiple lots in the same test need distinct IDs.
 */
class TestPlanningPartLot extends PartLot
{
    public function __construct(private readonly int $fakeId)
    {
        parent::__construct();
    }

    public function getID(): ?int
    {
        return $this->fakeId;
    }
}

final class ProjectPlanningHelperTest extends WebTestCase
{
    private ProjectPlanningHelper $service;

    private Part $part;
    private Project $project;
    private ProjectBOMEntry $bomEntry;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->service = self::getContainer()->get(ProjectPlanningHelper::class);

        $this->part = new Part();

        $this->project = new Project();
        $this->bomEntry = new ProjectBOMEntry();
        $this->bomEntry->setPart($this->part);
        $this->bomEntry->setQuantity(3);
        $this->project->addBomEntry($this->bomEntry);
    }

    public function testCreatePlanReservesExactlyNeededAmountAcrossLots(): void
    {
        $lot1 = new TestPlanningPartLot(101);
        $lot1->setAmount(2);
        $this->part->addPartLot($lot1);

        $lot2 = new TestPlanningPartLot(102);
        $lot2->setAmount(5);
        $this->part->addPartLot($lot2);

        //Needed: quantity(3) * numberOfBuilds(1) = 3. Lot1 (available 2) is exhausted first, lot2 covers the rest (1).
        $request = new PlanProjectRequest($this->project, 1);
        $request->setName('Test plan');

        $plannedProject = $this->service->createPlan($request);

        $this->assertSame(PlannedProjectStatus::PLANNED, $plannedProject->getStatus());
        $this->assertSame('Test plan', $plannedProject->getName());
        $this->assertCount(2, $plannedProject->getReservations());

        $this->assertEqualsWithDelta(3.0, $plannedProject->getReservedAmountForBOMEntry($this->bomEntry), PHP_FLOAT_EPSILON);
        $this->assertEqualsWithDelta(2.0, $lot1->getReservedAmount(), PHP_FLOAT_EPSILON);
        $this->assertEqualsWithDelta(1.0, $lot2->getReservedAmount(), PHP_FLOAT_EPSILON);

        //Reserving stock must never change the physical amount - only what's "available" changes
        $this->assertEqualsWithDelta(2.0, $lot1->getAmount(), PHP_FLOAT_EPSILON);
        $this->assertEqualsWithDelta(5.0, $lot2->getAmount(), PHP_FLOAT_EPSILON);
        $this->assertEqualsWithDelta(0.0, $lot1->getAvailableAmount(), PHP_FLOAT_EPSILON);
        $this->assertEqualsWithDelta(4.0, $lot2->getAvailableAmount(), PHP_FLOAT_EPSILON);
    }

    public function testDoBuildConsumesRequiredStockAndKeepsPlanAsBuiltHistory(): void
    {
        $lot = new TestPlanningPartLot(201);
        $lot->setAmount(5);
        $this->part->addPartLot($lot);

        $plannedProject = new PlannedProject();
        $plannedProject->setName('Plan A');
        $plannedProject->setProject($this->project)->setNumberOfBuilds(1);

        //Needed: quantity(3) * numberOfBuilds(1) = 3 - fully reserved.
        $reservation = new PartLotReservation($plannedProject, $this->bomEntry, $lot, 3.0);
        $plannedProject->addReservation($reservation);
        $this->bomEntry->addReservation($reservation);
        $lot->addReservation($reservation);

        $this->service->doBuild(new BuildPlannedProjectRequest($plannedProject));

        //A built plan is kept around (not deleted) as a historical record - only its status changes.
        $this->assertSame(PlannedProjectStatus::BUILT, $plannedProject->getStatus());
        $this->assertEqualsWithDelta(2.0, $lot->getAmount(), PHP_FLOAT_EPSILON);

        //The reservations are released: the stock was consumed, so nothing is held back anymore
        $this->assertCount(0, $plannedProject->getReservations());
        $this->assertEqualsWithDelta(0.0, $lot->getReservedAmount(), PHP_FLOAT_EPSILON);
        $this->assertEqualsWithDelta(2.0, $lot->getAvailableAmount(), PHP_FLOAT_EPSILON);

        //...but what was needed and consumed stays on the plan, as a snapshot
        $snapshot = $plannedProject->getBuiltParts();
        $this->assertCount(1, $snapshot);
        $this->assertEqualsWithDelta(3.0, $snapshot[0]['needed'], PHP_FLOAT_EPSILON);
        $this->assertCount(1, $snapshot[0]['lots']);
        $this->assertEqualsWithDelta(3.0, $snapshot[0]['lots'][0]['amount'], PHP_FLOAT_EPSILON);
    }

    public function testDoBuildToppsUpShortfallFromStockAddedAfterPlanning(): void
    {
        $lot = new TestPlanningPartLot(204);
        //Only 2 available when planned, even though 3 are needed - planning allows this partial reservation.
        $lot->setAmount(2);
        $this->part->addPartLot($lot);

        $request = new PlanProjectRequest($this->project, 1);
        $request->setName('Partial plan');
        $plannedProject = $this->service->createPlan($request);

        $this->assertEqualsWithDelta(2.0, $plannedProject->getReservedAmountForBOMEntry($this->bomEntry), PHP_FLOAT_EPSILON);
        $this->assertNotEmpty($this->service->getMissingBomEntriesForPlan($plannedProject));

        //Stock up: now there is enough to cover the shortfall, even though it was never actually reserved.
        $lot->setAmount(10);
        $this->assertEmpty($this->service->getMissingBomEntriesForPlan($plannedProject));

        $this->service->doBuild(new BuildPlannedProjectRequest($plannedProject));

        $this->assertSame(PlannedProjectStatus::BUILT, $plannedProject->getStatus());
        $this->assertEqualsWithDelta(7.0, $lot->getAmount(), PHP_FLOAT_EPSILON); //10 - 3 needed
    }

    public function testDoBuildThrowsWhenNotEnoughStockIsUsableEvenCountingReservation(): void
    {
        $lot = new TestPlanningPartLot(203);
        //Fully reserved for 2 (all of the lot's stock at the time), but 3 are needed - and nothing else is
        //available to make up the difference.
        $lot->setAmount(2);
        $this->part->addPartLot($lot);

        $plannedProject = new PlannedProject();
        $plannedProject->setName('Plan C');
        $plannedProject->setProject($this->project)->setNumberOfBuilds(1);

        $reservation = new PartLotReservation($plannedProject, $this->bomEntry, $lot, 2.0);
        $plannedProject->addReservation($reservation);
        $this->bomEntry->addReservation($reservation);
        $lot->addReservation($reservation);

        $this->expectException(\RuntimeException::class);
        $this->service->doBuild(new BuildPlannedProjectRequest($plannedProject));
    }

    public function testDoBuildThrowsWhenReservedLotLostStockToStocktakeWithNothingElseAvailable(): void
    {
        $lot = new TestPlanningPartLot(205);
        $lot->setAmount(3);
        $this->part->addPartLot($lot);

        $plannedProject = new PlannedProject();
        $plannedProject->setName('Plan D');
        $plannedProject->setProject($this->project)->setNumberOfBuilds(1);

        //Fully reserved for the needed amount (3) at planning time...
        $reservation = new PartLotReservation($plannedProject, $this->bomEntry, $lot, 3.0);
        $plannedProject->addReservation($reservation);
        $this->bomEntry->addReservation($reservation);
        $lot->addReservation($reservation);

        //...but a stocktake since then reduced the lot's physical stock below what is reserved on it, and
        //there is no other lot to make up the difference - this must still block the build, even though the
        //reservation itself was never short.
        $lot->setAmount(1);

        $this->expectException(\RuntimeException::class);
        $this->service->doBuild(new BuildPlannedProjectRequest($plannedProject));
    }

    public function testDoBuildThrowsWhenPlanIsNotInPlannedStatus(): void
    {
        $plannedProject = new PlannedProject();
        $plannedProject->setStatus(PlannedProjectStatus::CANCELLED);

        $this->expectException(\RuntimeException::class);
        $this->service->doBuild(new BuildPlannedProjectRequest($plannedProject));
    }

    public function testPartWithoutLotsIsStillListedAndBuildableOnceItHasStock(): void
    {
        //A part without any lot: nothing can be reserved for it, but it still has to show up in the list
        $request = new PlanProjectRequest($this->project, 1);
        $request->setName('Plan without lots');
        $plannedProject = $this->service->createPlan($request);

        $this->assertCount(0, $plannedProject->getReservations());

        $rows = $this->service->getPartRows($plannedProject);
        $this->assertCount(1, $rows);
        $this->assertSame($this->part, $rows[0]->part);
        $this->assertEqualsWithDelta(3.0, $rows[0]->needed, PHP_FLOAT_EPSILON);
        $this->assertEqualsWithDelta(0.0, $rows[0]->reserved, PHP_FLOAT_EPSILON);
        $this->assertSame([], $rows[0]->lots);
        $this->assertTrue($rows[0]->missing);
        $this->assertNotEmpty($this->service->getMissingBomEntriesForPlan($plannedProject));

        //Later a lot is added: the part is still listed, and now the plan can be built from that stock
        $lot = new TestPlanningPartLot(301);
        $lot->setAmount(10);
        $this->part->addPartLot($lot);

        $rows = $this->service->getPartRows($plannedProject);
        $this->assertCount(1, $rows);
        $this->assertFalse($rows[0]->missing);
        $this->assertEmpty($this->service->getMissingBomEntriesForPlan($plannedProject));

        $this->service->doBuild(new BuildPlannedProjectRequest($plannedProject));
        $this->assertSame(PlannedProjectStatus::BUILT, $plannedProject->getStatus());
        $this->assertEqualsWithDelta(7.0, $lot->getAmount(), PHP_FLOAT_EPSILON);

        //The built plan keeps listing the part, now with the consumed amount from the lot
        $rows = $this->service->getPartRows($plannedProject);
        $this->assertCount(1, $rows);
        $this->assertEqualsWithDelta(3.0, $rows[0]->reserved, PHP_FLOAT_EPSILON);
        $this->assertCount(1, $rows[0]->lots);
    }

    public function testGetPartRowsCanBeSorted(): void
    {
        $project = new Project();
        $entries = [];
        foreach ([['Bravo', 5.0, 4.0], ['alpha', 1.0, 9.0], ['Charlie', 3.0, 1.0]] as [$name, $quantity, $stock]) {
            $part = new Part();
            $part->setName($name);
            $lot = new TestPlanningPartLot(400 + count($entries));
            $lot->setAmount($stock);
            $part->addPartLot($lot);

            $entry = new ProjectBOMEntry();
            $entry->setPart($part);
            $entry->setQuantity($quantity);
            $project->addBomEntry($entry);
            $entries[] = $entry;
        }

        $request = new PlanProjectRequest($project, 1);
        $request->setName('Sorting');
        $plannedProject = $this->service->createPlan($request);

        $names = static fn (array $rows): array => array_map(static fn ($row): string => $row->partName, $rows);

        $this->assertSame(['alpha', 'Bravo', 'Charlie'], $names($this->service->getPartRows($plannedProject, 'name')));
        $this->assertSame(['Charlie', 'Bravo', 'alpha'], $names($this->service->getPartRows($plannedProject, 'name', false)));
        $this->assertSame(['alpha', 'Charlie', 'Bravo'], $names($this->service->getPartRows($plannedProject, 'needed')));
        //Reserved: alpha 1, Charlie 1 (only 1 in stock), Bravo 4 (only 4 in stock) - equal values are ordered by
        //name, so descending is the exact reverse of ascending
        $this->assertSame(['alpha', 'Charlie', 'Bravo'], $names($this->service->getPartRows($plannedProject, 'reserved')));
        $this->assertSame(['Bravo', 'Charlie', 'alpha'], $names($this->service->getPartRows($plannedProject, 'reserved', false)));
        //Stock: Charlie 1, Bravo 4, alpha 9
        $this->assertSame(['Charlie', 'Bravo', 'alpha'], $names($this->service->getPartRows($plannedProject, 'amount')));
        //Unknown sort keys fall back to the name
        $this->assertSame(['alpha', 'Bravo', 'Charlie'], $names($this->service->getPartRows($plannedProject, 'bogus')));
    }

    public function testShortfallIsZeroWhenStockIsAvailable(): void
    {
        $lot = new TestPlanningPartLot(501);
        $lot->setAmount(100);
        $this->part->addPartLot($lot);

        $plannedProject = new PlannedProject();
        $plannedProject->setProject($this->project)->setNumberOfBuilds(1);

        $this->assertEqualsWithDelta(0.0, $this->service->getShortfallForBomEntry($plannedProject, $this->bomEntry), PHP_FLOAT_EPSILON);

        $lot->setAmount(1);
        $this->assertEqualsWithDelta(2.0, $this->service->getShortfallForBomEntry($plannedProject, $this->bomEntry), PHP_FLOAT_EPSILON);
    }

    public function testCancelReleasesReservationsWithoutChangingStock(): void
    {
        $lot = new TestPlanningPartLot(202);
        $lot->setAmount(5);
        $this->part->addPartLot($lot);

        $plannedProject = new PlannedProject();
        $plannedProject->setName('Plan B');
        $plannedProject->setProject($this->project)->setNumberOfBuilds(1);

        $reservation = new PartLotReservation($plannedProject, $this->bomEntry, $lot, 5.0);
        $plannedProject->addReservation($reservation);
        $this->bomEntry->addReservation($reservation);
        $lot->addReservation($reservation);

        $this->service->cancel($plannedProject);

        $this->assertSame(PlannedProjectStatus::CANCELLED, $plannedProject->getStatus());
        //Cancelling must restore inventory to exactly the pre-plan state - amount is untouched, only the
        //reservation is gone
        $this->assertEqualsWithDelta(5.0, $lot->getAmount(), PHP_FLOAT_EPSILON);
        $this->assertEqualsWithDelta(0.0, $lot->getReservedAmount(), PHP_FLOAT_EPSILON);
        $this->assertEqualsWithDelta(5.0, $lot->getAvailableAmount(), PHP_FLOAT_EPSILON);
        $this->assertCount(0, $plannedProject->getReservations());
    }

    public function testCancelThrowsWhenPlanIsNotInPlannedStatus(): void
    {
        $plannedProject = new PlannedProject();
        $plannedProject->setStatus(PlannedProjectStatus::BUILT);

        $this->expectException(\RuntimeException::class);
        $this->service->cancel($plannedProject);
    }
}
