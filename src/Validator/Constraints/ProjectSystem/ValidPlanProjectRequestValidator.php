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
namespace App\Validator\Constraints\ProjectSystem;

use App\Entity\Parts\PartLot;
use App\Helpers\Projects\PlanProjectRequest;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Violation\ConstraintViolationBuilderInterface;

class ValidPlanProjectRequestValidator extends ConstraintValidator
{
    private function buildViolationForLot(PartLot $partLot, string $message): ConstraintViolationBuilderInterface
    {
        return $this->context->buildViolation($message)
            ->atPath('lot_' . $partLot->getID())
            ->setParameter('{{ lot }}', $partLot->getName());
    }

    public function validate($value, Constraint $constraint): void
    {
        if (!$constraint instanceof ValidPlanProjectRequest) {
            throw new UnexpectedTypeException($constraint, ValidPlanProjectRequest::class);
        }

        if (null === $value || '' === $value) {
            return;
        }

        if (!$value instanceof PlanProjectRequest) {
            throw new UnexpectedTypeException($value, PlanProjectRequest::class);
        }

        //Planning intentionally supports partial reservations: if there is not enough available stock to
        //cover a BOM entry's full needed amount, whatever is available gets reserved and the rest is simply
        //left unreserved (visible as a shortfall on the plan and in the "missing parts" report), rather than
        //blocking the whole plan from being created. The only things that must still be rejected are physically
        //impossible inputs: a negative amount, or reserving more than a lot actually has available, or
        //reserving more than the BOM entry actually needs (over-allocating stock that isn't required).
        foreach ($value->getPartBomEntries() as $bom_entry) {
            $reservation_sum = $value->getReservationAmountSum($bom_entry);
            $needed_amount = $value->getNeededAmountForBOMEntry($bom_entry);
            $lots = $value->getPartLotsForBOMEntry($bom_entry) ?? [];

            foreach ($lots as $lot) {
                $reservation_amount = $value->getLotReservationAmount($lot);

                if ($reservation_amount < 0) {
                    $this->buildViolationForLot($lot, 'validator.project_plan.lot_must_not_smaller_0')
                        ->addViolation();
                }

                if ($reservation_amount > $lot->getAvailableAmount()) {
                    $this->buildViolationForLot($lot, 'validator.project_plan.lot_must_not_bigger_than_available')
                        ->addViolation();
                }

                if ($reservation_sum > $needed_amount) {
                    $this->buildViolationForLot($lot, 'validator.project_plan.lot_bigger_than_needed')
                        ->addViolation();
                }
            }
        }
    }
}
