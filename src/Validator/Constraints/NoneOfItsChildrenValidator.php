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

/**
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 * Copyright (C) 2019 Jan Böhmer (https://github.com/jbtronics)
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA
 */

namespace App\Validator\Constraints;

use App\Entity\Base\AbstractStructuralDBElement;
use Symfony\Component\Form\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

/**
 * The validator for the NoneOfItsChildren annotation.
 */
class NoneOfItsChildrenValidator extends ConstraintValidator
{
    /**
     * Checks if the passed value is valid.
     *
     * @param mixed      $value      The value that should be validated
     * @param Constraint $constraint The constraint for the validation
     */
    public function validate($value, Constraint $constraint): void
    {
        if (!$constraint instanceof NoneOfItsChildren) {
            throw new UnexpectedTypeException($constraint, NoneOfItsChildren::class);
        }

        // custom constraints should ignore null and empty values to allow
        // other constraints (NotBlank, NotNull, etc.) take care of that
        if (null === $value || '' === $value) {
            return;
        }

        //Check type of value. Validating only works for StructuralDBElements
        if (!$value instanceof AbstractStructuralDBElement) {
            throw new UnexpectedValueException($value, 'StructuralDBElement');
        }

        //Check if the object is assigned to itself
        /** @var AbstractStructuralDBElement $entity */
        $entity = $this->context->getObject();
        /** @var AbstractStructuralDBElement $value */

        // Check if the targeted parent is the object itself:
        $entity_id = $entity->getID();
        if (null !== $entity_id && $entity_id === $value->getID()) {
            //Set the entity to a valid state
            $entity->setParent(null);
            $this->context->buildViolation($constraint->self_message)->addViolation();
            //The other things can not happen.
            return;
        }

        // Check if the targeted parent is a child object
        if ($value->isChildOf($entity)) {
            //Set the entity to a valid state
            $entity->setParent(null);
            $this->context->buildViolation($constraint->children_message)->addViolation();
        }
    }
}
