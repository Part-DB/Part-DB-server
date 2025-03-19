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
namespace App\Validator\Constraints\AssemblySystem;

use App\Entity\Parts\PartLot;
use App\Helpers\Assemblies\AssemblyBuildRequest;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Violation\ConstraintViolationBuilderInterface;

class ValidAssemblyBuildRequestValidator extends ConstraintValidator
{
    private function buildViolationForLot(PartLot $partLot, string $message): ConstraintViolationBuilderInterface
    {
        return $this->context->buildViolation($message)
            ->atPath('lot_' . $partLot->getID())
            ->setParameter('{{ lot }}', $partLot->getName());
    }

    public function validate($value, Constraint $constraint): void
    {
        if (!$constraint instanceof ValidAssemblyBuildRequest) {
            throw new UnexpectedTypeException($constraint, ValidAssemblyBuildRequest::class);
        }

        if (null === $value || '' === $value) {
            return;
        }

        if (!$value instanceof AssemblyBuildRequest) {
            throw new UnexpectedTypeException($value, AssemblyBuildRequest::class);
        }

        foreach ($value->getPartBomEntries() as $bom_entry) {
            $withdraw_sum = $value->getWithdrawAmountSum($bom_entry);
            $needed_amount = $value->getNeededAmountForBOMEntry($bom_entry);

            foreach ($value->getPartLotsForBOMEntry($bom_entry) as $lot) {
                $withdraw_amount = $value->getLotWithdrawAmount($lot);

                if ($withdraw_amount < 0) {
                   $this->buildViolationForLot($lot, 'validator.assembly_build.lot_must_not_smaller_0')
                        ->addViolation();
                }

                if ($withdraw_amount > $lot->getAmount()) {
                    $this->buildViolationForLot($lot, 'validator.assembly_build.lot_must_not_bigger_than_stock')
                        ->addViolation();
                }

                if ($withdraw_sum > $needed_amount && $value->isDontCheckQuantity() === false) {
                    $this->buildViolationForLot($lot, 'validator.assembly_build.lot_bigger_than_needed')
                        ->addViolation();
                }

                if ($withdraw_sum < $needed_amount && $value->isDontCheckQuantity() === false) {
                    $this->buildViolationForLot($lot, 'validator.assembly_build.lot_smaller_than_needed')
                        ->addViolation();
                }
            }
        }
    }
}
