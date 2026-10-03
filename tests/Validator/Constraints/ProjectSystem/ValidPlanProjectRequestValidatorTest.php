<?php

declare(strict_types=1);

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
namespace App\Tests\Validator\Constraints\ProjectSystem;

use App\Entity\Parts\Part;
use App\Entity\Parts\PartLot;
use App\Entity\ProjectSystem\PartLotReservation;
use App\Entity\ProjectSystem\PlannedProject;
use App\Entity\ProjectSystem\Project;
use App\Entity\ProjectSystem\ProjectBOMEntry;
use App\Helpers\Projects\PlanProjectRequest;
use App\Validator\Constraints\ProjectSystem\ValidPlanProjectRequest;
use App\Validator\Constraints\ProjectSystem\ValidPlanProjectRequestValidator;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

class TestValidatorPartLot extends PartLot
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

final class ValidPlanProjectRequestValidatorTest extends ConstraintValidatorTestCase
{
    protected function createValidator(): ValidPlanProjectRequestValidator
    {
        return new ValidPlanProjectRequestValidator();
    }

    /**
     * @return array{0: PlanProjectRequest, 1: PartLot}
     */
    private function makeRequest(float $lotAmount, float $quantity): array
    {
        $part = new Part();
        $lot = new TestValidatorPartLot(301);
        $lot->setAmount($lotAmount);
        $part->addPartLot($lot);

        $project = new Project();
        $bomEntry = new ProjectBOMEntry();
        $bomEntry->setPart($part);
        $bomEntry->setQuantity($quantity);
        $project->addBomEntry($bomEntry);

        $request = new PlanProjectRequest($project, 1);

        return [$request, $lot];
    }

    public function testExactlyEnoughAvailableStockRaisesNoViolation(): void
    {
        //3 needed, 5 available -> auto-allocation reserves exactly 3, which is valid
        [$request] = $this->makeRequest(5, 3);

        $this->validator->validate($request, new ValidPlanProjectRequest());

        $this->assertNoViolation();
    }

    public function testInsufficientAvailableStockIsAllowedAsPartialReservation(): void
    {
        //10 needed, only 4 available -> planning is still allowed, it just reserves whatever is available (4)
        //instead of the full needed amount. The shortfall shows up on the plan/missing-parts report, not as a
        //blocking validation error.
        [$request, $lot] = $this->makeRequest(4, 10);

        $this->assertEqualsWithDelta(4.0, $request->getLotReservationAmount($lot), PHP_FLOAT_EPSILON);

        $this->validator->validate($request, new ValidPlanProjectRequest());

        $this->assertNoViolation();
    }

    public function testAlreadyReservedStockIsNotOfferedAgain(): void
    {
        $part = new Part();
        $lot = new TestValidatorPartLot(301);
        $lot->setAmount(10);
        $part->addPartLot($lot);

        $project = new Project();
        $bomEntry = new ProjectBOMEntry();
        $bomEntry->setPart($part);
        $bomEntry->setQuantity(10);
        $project->addBomEntry($bomEntry);

        //8 of the 10 in stock are already reserved by another plan, leaving only 2 truly available
        $otherPlan = new PlannedProject();
        $otherReservation = new PartLotReservation($otherPlan, $bomEntry, $lot, 8.0);
        $lot->addReservation($otherReservation);

        //Needing all 10, only the truly available 2 get reserved (not the raw, already-partially-reserved 10) -
        //and that partial reservation is still valid, not a violation.
        $request = new PlanProjectRequest($project, 1);
        $this->assertEqualsWithDelta(2.0, $request->getLotReservationAmount($lot), PHP_FLOAT_EPSILON);

        $this->validator->validate($request, new ValidPlanProjectRequest());

        $this->assertNoViolation();
    }

    public function testReservingMoreThanAvailableRaisesViolation(): void
    {
        //Manually override the auto-allocation to ask for more than the lot has available - this must still
        //be rejected, since it would violate the Total = Available + Reserved invariant.
        [$request, $lot] = $this->makeRequest(4, 10);
        $request->setLotReservationAmount($lot, 5.0);

        $this->validator->validate($request, new ValidPlanProjectRequest());

        $this->buildViolation('validator.project_plan.lot_must_not_bigger_than_available')
            ->atPath('property.path.lot_301')
            ->setParameter('{{ lot }}', $lot->getName())
            ->assertRaised();
    }

    public function testReservingMoreThanNeededRaisesViolation(): void
    {
        //10 available, but the BOM entry only needs 3 - reserving all 10 would over-allocate stock that isn't
        //actually required for this plan.
        [$request, $lot] = $this->makeRequest(10, 3);
        $request->setLotReservationAmount($lot, 10.0);

        $this->validator->validate($request, new ValidPlanProjectRequest());

        $this->buildViolation('validator.project_plan.lot_bigger_than_needed')
            ->atPath('property.path.lot_301')
            ->setParameter('{{ lot }}', $lot->getName())
            ->assertRaised();
    }
}
