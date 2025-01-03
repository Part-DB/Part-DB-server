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

use App\Entity\Base\AbstractStructuralDBElement;
use Symfony\Component\Form\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

/**
 * The validator for the Selectable constraint.
 * @see \App\Tests\Validator\Constraints\SelectableValidatorTest
 */
class SelectableValidator extends ConstraintValidator
{
    /**
     * Checks if the passed value is valid.
     *
     * @param mixed      $value      The value that should be validated
     * @param Constraint $constraint The constraint for the validation
     */
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof Selectable) {
            throw new UnexpectedTypeException($constraint, Selectable::class);
        }

        // custom constraints should ignore null and empty values to allow
        // other constraints (NotBlank, NotNull, etc.) take care of that
        if (null === $value || '' === $value) {
            return;
        }

        //Check type of value. Validating only works for StructuralDBElements
        if (!$value instanceof AbstractStructuralDBElement) {
            throw new UnexpectedValueException($value, AbstractStructuralDBElement::class);
        }

        //Check if the value is not selectable -> show error message then.
        if ($value->isNotSelectable()) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ name }}', $value->getName())
                ->setParameter('{{ full_path }}', $value->getFullPath())
                ->addViolation();
        }
    }
}
