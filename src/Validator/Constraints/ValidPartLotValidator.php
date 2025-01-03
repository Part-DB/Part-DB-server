<?php
/**
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 * Copyright (C) 2019 - 2022 Jan Böhmer (https://github.com/jbtronics)
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

namespace App\Validator\Constraints;

use App\Entity\Parts\PartLot;
use App\Entity\Parts\StorageLocation;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class ValidPartLotValidator extends ConstraintValidator
{
    public function __construct(protected EntityManagerInterface $em)
    {
    }

    /**
     * Checks if the passed value is valid.
     *
     * @param mixed      $value      The value that should be validated
     * @param Constraint $constraint The constraint for the validation
     */
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof ValidPartLot) {
            throw new UnexpectedTypeException($constraint, ValidPartLot::class);
        }

        if (!$value instanceof PartLot) {
            throw new UnexpectedTypeException($value, PartLot::class);
        }

        //We can only validate the values if we know the storelocation
        if ($value->getStorageLocation() instanceof StorageLocation) {
            $repo = $this->em->getRepository(StorageLocation::class);
            //We can only determine associated parts, if the part have an ID
            //When the storage location is new (no ID), we can just assume there are no other parts
            if (null !== $value->getID() && $value->getStorageLocation()->getID()) {
                $parts = new ArrayCollection($repo->getParts($value->getStorageLocation()));
            } else {
                $parts = new ArrayCollection([]);
            }

            //Check for isFull() attribute
            if ($value->getStorageLocation()->isFull()) {
                //Compare with saved amount value
                $db_lot = $this->em->getUnitOfWork()->getOriginalEntityData($value);

                //Amount increasment is not allowed
                if ($db_lot && $value->getAmount() > $db_lot['amount']) {
                    $this->context->buildViolation('validator.part_lot.location_full.no_increase')
                        ->setParameter('{{ old_amount }}', (string) $db_lot['amount'])
                        ->atPath('amount')->addViolation();
                }

                if (!$parts->contains($value->getPart())) {
                    $this->context->buildViolation('validator.part_lot.location_full')
                        ->atPath('storage_location')->addViolation();
                }
            }

            //Check for onlyExisting
            if ($value->getStorageLocation()->isLimitToExistingParts() && !$parts->contains($value->getPart())) {
                $this->context->buildViolation('validator.part_lot.only_existing')
                    ->atPath('storage_location')->addViolation();
            }

            //Check for only single part
            if ($value->getStorageLocation()->isOnlySinglePart() && ($parts->count() > 0) && !$parts->contains(
                    $value->getPart()
                )) {
                $this->context->buildViolation('validator.part_lot.single_part')
                        ->atPath('storage_location')->addViolation();
            }
        }
    }
}
