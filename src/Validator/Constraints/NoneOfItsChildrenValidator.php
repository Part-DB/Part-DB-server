<?php
/**
 *
 * part-db version 0.1
 * Copyright (C) 2005 Christoph Lechner
 * http://www.cl-projects.de/
 *
 * part-db version 0.2+
 * Copyright (C) 2009 K. Jacobs and others (see authors.php)
 * http://code.google.com/p/part-db/
 *
 * Part-DB Version 0.4+
 * Copyright (C) 2016 - 2019 Jan Böhmer
 * https://github.com/jbtronics
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
 *
 */

namespace App\Validator\Constraints;


use App\Entity\Base\StructuralDBElement;
use Symfony\Bundle\MakerBundle\Str;
use Symfony\Component\Form\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * The validator for the NoneOfItsChildren annotation.
 * @package App\Validator\Constraints
 */
class NoneOfItsChildrenValidator extends ConstraintValidator
{
    /**
     * Checks if the passed value is valid.
     *
     * @param mixed $value The value that should be validated
     * @param Constraint $constraint The constraint for the validation
     */
    public function validate($value, Constraint $constraint)
    {
        if (!$constraint instanceof NoneOfItsChildren) {
            throw new UnexpectedTypeException($constraint, NoneOfItsChildren::class);
        }

        // custom constraints should ignore null and empty values to allow
        // other constraints (NotBlank, NotNull, etc.) take care of that
        if (null === $value || '' === $value) {
            return;
        }

        //Check if the object is assigned to itself
        /** @var StructuralDBElement $entity */
        $entity = $this->context->getObject();
        /** @var StructuralDBElement $value */

        // Check if the targeted parent is the object itself:
        $entity_id = $entity->getID();
        if ($entity_id !== null && $entity_id === $value->getID()) {
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
            return;
        }
    }
}